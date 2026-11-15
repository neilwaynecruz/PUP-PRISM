<?php

namespace App\Services\Forecasting;

use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\ForecastProfile;
use App\Models\Product;
use App\Models\PurchaseOrderLine;
use App\Models\RequisitionLine;
use App\Services\Forecasting\Data\ForecastResult;
use App\Services\Forecasting\Methods\ExponentialSmoothingMethod;
use App\Services\Forecasting\Methods\MovingAverageMethod;
use App\Services\Forecasting\Methods\SeasonalMethod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemandForecaster
{
    public function __construct(
        private readonly ConsumptionDataCollector $collector,
        private readonly MovingAverageMethod $movingAverage,
        private readonly ExponentialSmoothingMethod $exponentialSmoothing,
        private readonly SeasonalMethod $seasonalMethod,
    ) {}

    public function forecast(
        Product $product,
        ?ForecastProfile $profile = null,
        ?CarbonImmutable $asOf = null,
    ): ForecastResult {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $profile ??= $product->relationLoaded('forecastProfile') && $product->forecastProfile
            ? $product->forecastProfile
            : $this->defaultProfileFor($product);

        $dailyData = $this->collector->getDailyConsumption($product, $profile->lookback_days, $asOf);

        // Open purchase orders (incoming replenishment)
        $onOrderQty = (int) PurchaseOrderLine::query()
            ->where('product_id', $product->id)
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial]))
            ->sum(DB::raw('qty_ordered - qty_received'));

        // Pending unissued requisitions (committed department reservations)
        $committedQty = (int) RequisitionLine::query()
            ->where('product_id', $product->id)
            ->whereHas('requisition', fn ($q) => $q->whereIn('status', [RequisitionStatus::Approved, RequisitionStatus::PartiallyIssued, RequisitionStatus::Backordered]))
            ->sum(DB::raw('qty_requested - qty_issued'));

        return $this->forecastFromInputs(
            $product,
            $profile,
            $asOf,
            $dailyData,
            max(0, $onOrderQty),
            max(0, $committedQty),
        );
    }

    /**
     * @return array<int, ForecastResult>
     */
    public function forecastAll(?CarbonImmutable $asOf = null, ?int $productId = null): array
    {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $results = [];

        $query = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->with([
                'stock:id,product_id,on_hand_qty',
                'forecastProfile:id,product_id,method,lookback_days,forecast_horizon_days,lead_time_days,safety_stock_days,smoothing_factor,trend_factor,is_active',
            ]);

        if ($productId !== null) {
            $query->whereKey($productId);
        }

        $query->chunkById(100, function (Collection $products) use (&$results, $asOf): void {
            if ($products->isEmpty()) {
                return;
            }

            $productIds = $products->modelKeys();
            $profilesByProduct = [];
            $lookbackDaysByProduct = [];

            foreach ($products as $product) {
                $profile = $product->relationLoaded('forecastProfile') && $product->forecastProfile
                    ? $product->forecastProfile
                    : $this->defaultProfileFor($product);
                $profilesByProduct[$product->id] = $profile;
                $lookbackDaysByProduct[$product->id] = $profile->lookback_days;
            }

            $dailyDataByProduct = $this->collector->getDailyConsumptionBatch(
                $products,
                $lookbackDaysByProduct,
                $asOf,
            );

            $onOrderByProduct = PurchaseOrderLine::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial]))
                ->select('product_id', DB::raw('SUM(qty_ordered - qty_received) as total_on_order'))
                ->groupBy('product_id')
                ->pluck('total_on_order', 'product_id');

            $committedByProduct = RequisitionLine::query()
                ->whereIn('product_id', $productIds)
                ->whereHas('requisition', fn ($q) => $q->whereIn('status', [RequisitionStatus::Approved, RequisitionStatus::PartiallyIssued, RequisitionStatus::Backordered]))
                ->select('product_id', DB::raw('SUM(qty_requested - qty_issued) as total_committed'))
                ->groupBy('product_id')
                ->pluck('total_committed', 'product_id');

            foreach ($products as $product) {
                try {
                    $results[$product->id] = $this->forecastFromInputs(
                        $product,
                        $profilesByProduct[$product->id],
                        $asOf,
                        $dailyDataByProduct[$product->id] ?? [],
                        max(0, (int) ($onOrderByProduct->get($product->id) ?? 0)),
                        max(0, (int) ($committedByProduct->get($product->id) ?? 0)),
                    );
                } catch (\Throwable $exception) {
                    Log::warning("Forecast failed for product {$product->id}: {$exception->getMessage()}");
                }
            }
        });

        return $results;
    }

    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     */
    private function forecastFromInputs(
        Product $product,
        ForecastProfile $profile,
        CarbonImmutable $asOf,
        array $dailyData,
        int $onOrderQty,
        int $committedQty,
    ): ForecastResult {
        $resolvedMethod = $this->resolveMethod($profile, $dailyData);
        $nonZeroHistoryDays = count(array_filter($dailyData, fn (array $point): bool => $point['qty'] > 0));
        $hasSufficientHistory = count($dailyData) >= min(14, $profile->lookback_days) && $nonZeroHistoryDays >= 3;

        $predictions = $hasSufficientHistory
            ? $this->forecastSeries($resolvedMethod, $profile, $dailyData)
            : [];
        $predictedDailyConsumption = $predictions === []
            ? 0.0
            : round(array_sum(array_column($predictions, 'predicted_qty')) / count($predictions), 2);
        $currentOnHandQty = max(0, (int) ($product->stock?->on_hand_qty ?? 0));
        $reorderPointQty = (int) ceil($predictedDailyConsumption * ($profile->lead_time_days + $profile->safety_stock_days));

        $effectiveAvailableQty = max(0, $currentOnHandQty - $committedQty);
        $netInventoryPosition = $currentOnHandQty - $committedQty + $onOrderQty;
        $targetStock = max($reorderPointQty, (int) ($product->reorder_threshold ?? 0));
        $recommendedReorderQty = max(0, $targetStock - max(0, $netInventoryPosition));

        $predictedDaysUntilStockout = $predictedDailyConsumption > 0
            ? (int) floor($effectiveAvailableQty / $predictedDailyConsumption)
            : null;
        $predictedStockoutDate = $predictedDaysUntilStockout === null
            ? null
            : $asOf->startOfDay()->addDays($predictedDaysUntilStockout);
        $confidenceScore = $hasSufficientHistory
            ? $this->calculateConfidenceScore($dailyData, $profile->lookback_days)
            : ($nonZeroHistoryDays > 0
                ? min((float) ($this->calculateConfidenceScore($dailyData, $profile->lookback_days) ?? 18.0), 35.0)
                : null);

        return new ForecastResult(
            productId: $product->id,
            method: $resolvedMethod,
            currentOnHandQty: $currentOnHandQty,
            reorderPointQty: $reorderPointQty,
            predictedDailyConsumption: $predictedDailyConsumption,
            predictedDaysUntilStockout: $predictedDaysUntilStockout,
            predictedStockoutDate: $predictedStockoutDate,
            recommendedReorderQty: $recommendedReorderQty,
            confidenceScore: $confidenceScore,
            historicalDailyConsumption: $dailyData,
            forecastDailyConsumption: $predictions,
            lookbackDays: $profile->lookback_days,
            forecastHorizonDays: $profile->forecast_horizon_days,
            leadTimeDays: $profile->lead_time_days,
            safetyStockDays: $profile->safety_stock_days,
            hasSufficientHistory: $hasSufficientHistory,
            generatedAt: $asOf,
            pendingOnOrderQty: $onOrderQty,
            pendingCommittedQty: $committedQty,
            effectiveAvailableQty: $effectiveAvailableQty,
        );
    }

    private function defaultProfileFor(Product $product): ForecastProfile
    {
        return ForecastProfile::make([
            'product_id' => $product->id,
            'method' => 'exponential_smoothing',
            'lookback_days' => 90,
            'forecast_horizon_days' => 30,
            'lead_time_days' => 14,
            'safety_stock_days' => 7,
            'smoothing_factor' => 0.35,
            'trend_factor' => 0.15,
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     */
    private function resolveMethod(ForecastProfile $profile, array $dailyData): string
    {
        $hasSeasonalHistory = count($dailyData) >= 56;

        if ($profile->method === 'seasonal' && ! $hasSeasonalHistory) {
            return 'exponential_smoothing';
        }

        return $profile->method;
    }

    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     * @return list<array{date: string, predicted_qty: float}>
     */
    private function forecastSeries(string $method, ForecastProfile $profile, array $dailyData): array
    {
        return match ($method) {
            'moving_average' => $this->movingAverage->forecast(
                $dailyData,
                $profile->forecast_horizon_days,
            ),
            'seasonal' => $this->seasonalMethod->forecast(
                $dailyData,
                $profile->forecast_horizon_days,
            ),
            default => $this->exponentialSmoothing->forecast(
                $dailyData,
                $profile->forecast_horizon_days,
                $profile->smoothing_factor ?? 0.35,
                $profile->trend_factor ?? 0.15,
            ),
        };
    }

    /**
     * @param  list<array{date: string, qty: int}>  $dailyData
     */
    private function calculateConfidenceScore(array $dailyData, int $lookbackDays): ?float
    {
        if ($dailyData === []) {
            return null;
        }

        $values = array_map('floatval', array_column($dailyData, 'qty'));
        $historyCount = count($values);
        $nonZeroCount = count(array_filter($values, fn (float $value): bool => $value > 0));
        $mean = array_sum($values) / max(1, $historyCount);

        if ($mean <= 0) {
            return 18.0;
        }

        $variance = array_sum(array_map(
            fn (float $value): float => ($value - $mean) ** 2,
            $values,
        )) / max(1, $historyCount);
        $coefficientOfVariation = sqrt($variance) / $mean;
        $historyCoverage = min(1, $historyCount / max(14, min($lookbackDays, 60)));
        $activityCoverage = min(1, $nonZeroCount / max(5, min($historyCount, 21)));
        $stability = max(0, 1 - min($coefficientOfVariation, 2) / 2);

        return round(
            (($historyCoverage * 0.4) + ($activityCoverage * 0.3) + ($stability * 0.3)) * 100,
            2,
        );
    }
}
