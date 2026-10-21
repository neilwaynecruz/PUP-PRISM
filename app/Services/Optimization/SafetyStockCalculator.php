<?php

namespace App\Services\Optimization;

use App\Models\ForecastProfile;
use App\Models\Product;
use App\Services\Forecasting\ConsumptionDataCollector;
use Carbon\CarbonImmutable;

class SafetyStockCalculator
{
    public function __construct(
        private readonly ConsumptionDataCollector $collector,
    ) {}

    /**
     * Calculate scientific safety stock and statistical reorder point.
     *
     * Formula:
     * Safety Stock = ceil(Z * sigma_d * sqrt(L))
     * Reorder Point = ceil(mean_daily_demand * L) + Safety Stock
     *
     * @param  Product  $product  The consumable product
     * @param  float  $serviceLevelZ  Standard normal Z-value (1.65 for 95%, 2.05 for 98%, 2.33 for 99%)
     * @param  int  $lookbackDays  Days of historical demand to analyze
     * @param  CarbonImmutable|null  $asOf  Snapshot timestamp
     * @return array{
     *     mean_daily_demand: float,
     *     demand_std_dev: float,
     *     lead_time_days: int,
     *     service_level_z: float,
     *     safety_stock: int,
     *     reorder_point: int,
     *     lookback_days: int,
     *     data_points_count: int,
     *     non_zero_days_count: int,
     * }
     */
    public function calculate(
        Product $product,
        float $serviceLevelZ = 1.65,
        int $lookbackDays = 90,
        ?CarbonImmutable $asOf = null,
    ): array {
        $asOf ??= CarbonImmutable::now()->endOfDay();

        $dailyData = $this->collector->getDailyConsumption($product, $lookbackDays, $asOf);
        $demands = array_map('floatval', array_column($dailyData, 'qty'));
        $n = count($demands);

        $nonZeroDays = count(array_filter($demands, fn (float $qty) => $qty > 0));
        $mean = $n > 0 ? array_sum($demands) / $n : 0.0;

        // Sample variance & standard deviation
        $variance = 0.0;
        if ($n > 1) {
            foreach ($demands as $d) {
                $variance += ($d - $mean) ** 2;
            }
            $stdDev = sqrt($variance / ($n - 1));
        } else {
            $stdDev = 0.0;
        }

        /** @var ForecastProfile|null $profile */
        $profile = $product->relationLoaded('forecastProfile')
            ? $product->forecastProfile
            : $product->forecastProfile()->first();

        $leadTime = max(1, (int) ($profile?->lead_time_days ?? 14));

        // Scientific safety stock = Z * sigma_d * sqrt(L)
        $safetyStock = (int) ceil($serviceLevelZ * $stdDev * sqrt($leadTime));

        // Reorder point = (average daily demand * lead time) + safety stock
        $leadTimeDemand = (int) ceil($mean * $leadTime);
        $reorderPoint = $leadTimeDemand + $safetyStock;

        return [
            'mean_daily_demand' => round($mean, 2),
            'demand_std_dev' => round($stdDev, 2),
            'lead_time_days' => $leadTime,
            'service_level_z' => $serviceLevelZ,
            'safety_stock' => $safetyStock,
            'reorder_point' => $reorderPoint,
            'lookback_days' => $lookbackDays,
            'data_points_count' => $n,
            'non_zero_days_count' => $nonZeroDays,
        ];
    }
}
