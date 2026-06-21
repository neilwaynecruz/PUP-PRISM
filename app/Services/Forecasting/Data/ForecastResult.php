<?php

namespace App\Services\Forecasting\Data;

use Carbon\CarbonImmutable;

class ForecastResult
{
    /**
     * @param  list<array{date: string, qty: int}>  $historicalDailyConsumption
     * @param  list<array{date: string, predicted_qty: float}>  $forecastDailyConsumption
     */
    public function __construct(
        public readonly int $productId,
        public readonly string $method,
        public readonly int $currentOnHandQty,
        public readonly int $reorderPointQty,
        public readonly float $predictedDailyConsumption,
        public readonly ?int $predictedDaysUntilStockout,
        public readonly ?CarbonImmutable $predictedStockoutDate,
        public readonly int $recommendedReorderQty,
        public readonly ?float $confidenceScore,
        public readonly array $historicalDailyConsumption,
        public readonly array $forecastDailyConsumption,
        public readonly int $lookbackDays,
        public readonly int $forecastHorizonDays,
        public readonly int $leadTimeDays,
        public readonly int $safetyStockDays,
        public readonly bool $hasSufficientHistory,
        public readonly CarbonImmutable $generatedAt,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toSnapshotPayload(): array
    {
        return [
            'product_id' => $this->productId,
            'forecast_date' => $this->generatedAt->toDateString(),
            'forecast_method' => $this->method,
            'current_on_hand_qty' => $this->currentOnHandQty,
            'reorder_point_qty' => $this->reorderPointQty,
            'predicted_daily_consumption' => $this->predictedDailyConsumption,
            'predicted_days_until_stockout' => $this->predictedDaysUntilStockout,
            'predicted_stockout_date' => $this->predictedStockoutDate?->toDateString(),
            'recommended_reorder_qty' => $this->recommendedReorderQty,
            'confidence_score' => $this->confidenceScore,
            'raw_data' => [
                'historical_daily' => $this->historicalDailyConsumption,
                'forecast_daily' => $this->forecastDailyConsumption,
                'summary' => [
                    'lookback_days' => $this->lookbackDays,
                    'forecast_horizon_days' => $this->forecastHorizonDays,
                    'lead_time_days' => $this->leadTimeDays,
                    'safety_stock_days' => $this->safetyStockDays,
                    'history_days' => count($this->historicalDailyConsumption),
                    'non_zero_history_days' => count(array_filter(
                        $this->historicalDailyConsumption,
                        fn (array $point) => $point['qty'] > 0,
                    )),
                    'has_sufficient_history' => $this->hasSufficientHistory,
                ],
            ],
            'generated_at' => $this->generatedAt,
        ];
    }
}
