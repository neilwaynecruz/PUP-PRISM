<?php

namespace App\Services\Forecasting;

use App\Enums\ProductType;
use App\Models\ForecastProfile;
use App\Models\Product;
use App\Services\Forecasting\Data\ForecastResult;
use App\Services\Forecasting\Methods\ExponentialSmoothingMethod;
use App\Services\Forecasting\Methods\MovingAverageMethod;
use App\Services\Forecasting\Methods\SeasonalMethod;
use Carbon\CarbonImmutable;
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
        $resolvedMethod = $this->resolveMethod($profile, $dailyData);
        $predictions = $this->forecastSeries($resolvedMethod, $profile, $dailyData);
        $predictedDailyConsumption = $predictions === []
            ? 0.0
            : round(array_sum(array_column($predictions, 'predicted_qty')) / count($predictions), 2);
        $currentOnHandQty = max(0, (int) ($product->stock?->on_hand_qty ?? 0));
        $reorderPointQty = (int) ceil($predictedDailyConsumption * ($profile->lead_time_days + $profile->safety_stock_days));
        $recommendedReorderQty = max(
            0,
            max($reorderPointQty, (int) ($product->reorder_threshold ?? 0)) - $currentOnHandQty,
        );
        $predictedDaysUntilStockout = $predictedDailyConsumption > 0
            ? (int) floor($currentOnHandQty / $predictedDailyConsumption)
            : null;
        $predictedStockoutDate = $predictedDaysUntilStockout === null
            ? null
            : $asOf->startOfDay()->addDays($predictedDaysUntilStockout);
        $confidenceScore = $this->calculateConfidenceScore($dailyData, $profile->lookback_days);
        $nonZeroHistoryDays = count(array_filter($dailyData, fn (array $point) => $point['qty'] > 0));
        $hasSufficientHistory = count($dailyData) >= min(14, $profile->lookback_days) && $nonZeroHistoryDays >= 3;

        if (! $hasSufficientHistory && $confidenceScore !== null) {
            $confidenceScore = min($confidenceScore, 35.0);
        }

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

        foreach ($query->lazyById(100) as $product) {
            try {
                $results[$product->id] = $this->forecast($product, $product->forecastProfile, $asOf);
            } catch (\Throwable $exception) {
                Log::warning("Forecast failed for product {$product->id}: {$exception->getMessage()}");
            }
        }

        return $results;
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
        $nonZeroCount = count(array_filter($values, fn (float $value) => $value > 0));
        $mean = array_sum($values) / max(1, $historyCount);

        if ($mean <= 0) {
            return 18.0;
        }

        $variance = array_sum(array_map(
            fn (float $value) => ($value - $mean) ** 2,
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
