<?php

namespace App\Console\Commands;

use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Services\Forecasting\Data\ForecastResult;
use App\Services\Forecasting\DemandForecaster;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-demand-forecasts {--product= : Generate a forecast for a specific product id} {--date= : Override the forecast date (Y-m-d)}')]
#[Description('Generate demand forecasts for active consumable products')]
class GenerateDemandForecasts extends Command
{
    public function handle(DemandForecaster $forecaster): int
    {
        $productId = $this->option('product') !== null
            ? (int) $this->option('product')
            : null;
        $asOf = $this->option('date')
            ? CarbonImmutable::parse((string) $this->option('date'))->endOfDay()
            : CarbonImmutable::now()->endOfDay();

        $this->components->info("Generating demand forecasts for {$asOf->toDateString()}...");

        $results = $forecaster->forecastAll($asOf, $productId);

        $this->persistProfiles($results);
        $this->persistSnapshots($results);
        $this->syncForecastAlerts($results, $asOf, $productId);

        $this->components->info(sprintf('Generated %d demand forecast snapshot(s).', count($results)));

        return self::SUCCESS;
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function persistProfiles(array $results): void
    {
        foreach ($results as $productId => $result) {
            ForecastProfile::query()->firstOrCreate(
                ['product_id' => $productId],
                [
                    'method' => $result->method,
                    'lookback_days' => $result->lookbackDays,
                    'forecast_horizon_days' => $result->forecastHorizonDays,
                    'lead_time_days' => $result->leadTimeDays,
                    'safety_stock_days' => $result->safetyStockDays,
                    'smoothing_factor' => $result->method === 'exponential_smoothing' ? 0.35 : null,
                    'trend_factor' => $result->method === 'exponential_smoothing' ? 0.15 : null,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function persistSnapshots(array $results): void
    {
        foreach ($results as $productId => $result) {
            ForecastSnapshot::query()->updateOrCreate(
                [
                    'product_id' => $productId,
                    'forecast_date' => $result->generatedAt->toDateString(),
                ],
                $result->toSnapshotPayload(),
            );
        }
    }

    /**
     * @param  array<int, ForecastResult>  $results
     */
    private function syncForecastAlerts(array $results, CarbonImmutable $generatedAt, ?int $productId = null): void
    {
        $atRiskProductIds = [];

        foreach ($results as $result) {
            if (
                $result->predictedDaysUntilStockout === null
                || $result->predictedDaysUntilStockout > max(7, $result->leadTimeDays)
                || $result->recommendedReorderQty <= 0
            ) {
                continue;
            }

            $atRiskProductIds[] = $result->productId;

            InventoryAlert::query()->updateOrCreate(
                [
                    'type' => 'forecast_stockout',
                    'product_id' => $result->productId,
                    'stock_lot_id' => null,
                    'resolved_at' => null,
                ],
                [
                    'message' => sprintf(
                        'Forecasted stockout in %d day(s). Reorder %d unit(s) before %s.',
                        $result->predictedDaysUntilStockout,
                        $result->recommendedReorderQty,
                        $result->predictedStockoutDate?->toFormattedDateString() ?? 'the next review cycle',
                    ),
                    'detected_at' => $generatedAt,
                ],
            );
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

        if ($atRiskProductIds === []) {
            $staleAlerts->update(['resolved_at' => $generatedAt]);

            return;
        }

        $staleAlerts->update(['resolved_at' => $generatedAt]);
    }
}
