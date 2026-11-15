<?php

namespace App\Console\Commands;

use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Services\Forecasting\Data\ForecastResult;
use App\Services\Forecasting\DemandForecaster;
use App\Services\NotificationService;
use App\Services\Optimization\InventoryOptimizationCache;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-demand-forecasts {--product= : Generate a forecast for a specific product id} {--date= : Override the forecast date (Y-m-d)}')]
#[Description('Generate demand forecasts for active consumable products')]
class GenerateDemandForecasts extends Command
{
    public function handle(
        DemandForecaster $forecaster,
        NotificationService $notificationService,
        InventoryOptimizationCache $optimizationCache,
    ): int {
        $productId = $this->option('product') !== null
            ? (int) $this->option('product')
            : null;
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $this->components->info("Generating demand forecasts for {$asOf->toDateString()}...");

        $results = $forecaster->forecastAll($asOf, $productId);

        $this->persistProfiles($results, $optimizationCache);
        $this->persistSnapshots($results);
        $this->syncForecastAlerts($results, $asOf, $productId, $notificationService);

        $this->components->info(sprintf('Generated %d demand forecast snapshot(s).', count($results)));

        SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_GENERATE_DEMAND_FORECASTS);

        return self::SUCCESS;
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function persistProfiles(array $results, InventoryOptimizationCache $optimizationCache): void
    {
        if ($results === []) {
            return;
        }

        $productIds = array_keys($results);
        $existingIds = ForecastProfile::query()
            ->whereIn('product_id', $productIds)
            ->pluck('product_id')
            ->flip();

        $now = CarbonImmutable::now()->toDateTimeString();
        $toInsert = [];

        foreach ($results as $productId => $result) {
            if ($existingIds->has($productId)) {
                continue;
            }

            $toInsert[] = [
                'product_id' => $productId,
                'method' => $result->method,
                'lookback_days' => $result->lookbackDays,
                'forecast_horizon_days' => $result->forecastHorizonDays,
                'lead_time_days' => $result->leadTimeDays,
                'safety_stock_days' => $result->safetyStockDays,
                'smoothing_factor' => $result->method === 'exponential_smoothing' ? 0.35 : null,
                'trend_factor' => $result->method === 'exponential_smoothing' ? 0.15 : null,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($toInsert === []) {
            return;
        }

        foreach (array_chunk($toInsert, 100) as $chunk) {
            ForecastProfile::query()->insert($chunk);
        }

        $optimizationCache->invalidate();
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function persistSnapshots(array $results): void
    {
        if ($results === []) {
            return;
        }

        $model = new ForecastSnapshot;
        $now = $model->fromDateTime(CarbonImmutable::now());
        $rows = [];

        foreach ($results as $result) {
            $payload = $result->toSnapshotPayload();
            $rows[] = [
                'product_id' => $result->productId,
                'forecast_date' => $model->fromDateTime($result->generatedAt->startOfDay()),
                'forecast_method' => $payload['forecast_method'],
                'current_on_hand_qty' => $payload['current_on_hand_qty'],
                'reorder_point_qty' => $payload['reorder_point_qty'],
                'predicted_daily_consumption' => $payload['predicted_daily_consumption'],
                'predicted_days_until_stockout' => $payload['predicted_days_until_stockout'],
                'predicted_stockout_date' => $result->predictedStockoutDate !== null
                    ? $model->fromDateTime($result->predictedStockoutDate->startOfDay())
                    : null,
                'recommended_reorder_qty' => $payload['recommended_reorder_qty'],
                'confidence_score' => $payload['confidence_score'],
                'raw_data' => json_encode($payload['raw_data'], JSON_THROW_ON_ERROR),
                'generated_at' => $model->fromDateTime($result->generatedAt),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            ForecastSnapshot::query()->upsert(
                $chunk,
                ['product_id', 'forecast_date'],
                [
                    'forecast_method',
                    'current_on_hand_qty',
                    'reorder_point_qty',
                    'predicted_daily_consumption',
                    'predicted_days_until_stockout',
                    'predicted_stockout_date',
                    'recommended_reorder_qty',
                    'confidence_score',
                    'raw_data',
                    'generated_at',
                    'updated_at',
                ],
            );
        }
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function syncForecastAlerts(
        array $results,
        CarbonImmutable $generatedAt,
        ?int $productId,
        NotificationService $notificationService,
    ): void {
        /** @var array<int, ForecastResult> $atRiskResults */
        $atRiskResults = [];

        foreach ($results as $result) {
            if (
                $result->predictedDaysUntilStockout === null
                || $result->predictedDaysUntilStockout > max(7, $result->leadTimeDays)
                || $result->recommendedReorderQty <= 0
            ) {
                continue;
            }

            $atRiskResults[$result->productId] = $result;
        }

        $atRiskProductIds = array_keys($atRiskResults);

        $existingAlerts = $atRiskProductIds !== []
            ? InventoryAlert::query()
                ->where('type', 'forecast_stockout')
                ->whereNull('stock_lot_id')
                ->whereNull('resolved_at')
                ->whereIn('product_id', $atRiskProductIds)
                ->get()
                ->keyBy('product_id')
            : collect();

        /** @var array<int, ForecastResult> $newlyAlerted */
        $newlyAlerted = [];

        foreach ($atRiskResults as $riskProductId => $result) {
            $message = sprintf(
                'Forecasted stockout in %d day(s). Reorder %d unit(s) before %s.',
                $result->predictedDaysUntilStockout,
                $result->recommendedReorderQty,
                $result->predictedStockoutDate?->toFormattedDateString() ?? 'the next review cycle',
            );

            /** @var InventoryAlert|null $existingAlert */
            $existingAlert = $existingAlerts->get($riskProductId);

            if ($existingAlert instanceof InventoryAlert) {
                $existingAlert->update([
                    'message' => $message,
                    'detected_at' => $generatedAt,
                ]);

                continue;
            }

            $alert = InventoryAlert::upsertUnresolved(
                [
                    'type' => 'forecast_stockout',
                    'product_id' => $riskProductId,
                    'stock_lot_id' => null,
                ],
                [
                    'message' => $message,
                    'detected_at' => $generatedAt,
                ],
            );

            if ($alert->wasRecentlyCreated) {
                $newlyAlerted[] = $result;
            }
        }

        if ($newlyAlerted !== []) {
            $newProductIds = array_map(
                static fn (ForecastResult $result): int => $result->productId,
                $newlyAlerted,
            );
            $productsById = Product::query()
                ->whereIn('id', $newProductIds)
                ->get()
                ->keyBy('id');

            $notificationItems = [];

            foreach ($newlyAlerted as $result) {
                $product = $productsById->get($result->productId);

                if ($product instanceof Product) {
                    $notificationItems[] = [
                        'product' => $product,
                        'days_until_stockout' => (int) $result->predictedDaysUntilStockout,
                        'recommended_reorder_qty' => $result->recommendedReorderQty,
                    ];
                }
            }

            $notificationService->procurementRecommendationsBatch($notificationItems);
        }

        $staleAlerts = InventoryAlert::query()
            ->where('type', 'forecast_stockout')
            ->whereNull('resolved_at');

        if ($productId !== null) {
            $staleAlerts->where('product_id', $productId);
        }

        if ($atRiskProductIds !== []) {
            $staleAlerts->whereNotIn('product_id', $atRiskProductIds);
        }

        $staleAlerts->update(['resolved_at' => $generatedAt]);
    }
}
