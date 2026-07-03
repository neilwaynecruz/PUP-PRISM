<?php

namespace App\Services\Forecasting;

use App\Models\ForecastSnapshot;
use App\Services\Forecasting\Data\ForecastResult;
use Carbon\CarbonImmutable;
use DateTimeInterface;

class ForecastPresenter
{
    public function formatDateString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toDateString();
        }

        return CarbonImmutable::parse((string) $value)->toDateString();
    }

    public function isSnapshotFromToday(ForecastSnapshot $snapshot): bool
    {
        $forecastDate = $this->formatDateString($snapshot->forecast_date);

        return $forecastDate === CarbonImmutable::now()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    public function fromSnapshot(ForecastSnapshot $snapshot): array
    {
        $rawData = is_array($snapshot->raw_data) ? $snapshot->raw_data : [];

        return [
            'method' => $snapshot->forecast_method,
            'source' => 'snapshot',
            'current_on_hand_qty' => $snapshot->current_on_hand_qty,
            'reorder_point_qty' => $snapshot->reorder_point_qty,
            'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
            'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout,
            'predicted_stockout_date' => $this->formatDateString($snapshot->predicted_stockout_date),
            'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
            'confidence_score' => $snapshot->confidence_score !== null
                ? round($snapshot->confidence_score, 2)
                : null,
            'generated_at' => $snapshot->generated_at?->toISOString(),
            'historical_daily' => data_get($rawData, 'historical_daily', []),
            'forecast_daily' => data_get($rawData, 'forecast_daily', []),
            'history_window_days' => (int) data_get($rawData, 'summary.lookback_days', 0),
            'forecast_horizon_days' => (int) data_get($rawData, 'summary.forecast_horizon_days', 0),
            'lead_time_days' => (int) data_get($rawData, 'summary.lead_time_days', 0),
            'safety_stock_days' => (int) data_get($rawData, 'summary.safety_stock_days', 0),
            'has_sufficient_history' => (bool) data_get($rawData, 'summary.has_sufficient_history', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fromResult(ForecastResult $result, string $source = 'live'): array
    {
        return [
            'method' => $result->method,
            'source' => $source,
            'current_on_hand_qty' => $result->currentOnHandQty,
            'reorder_point_qty' => $result->reorderPointQty,
            'predicted_daily_consumption' => $result->predictedDailyConsumption,
            'predicted_days_until_stockout' => $result->predictedDaysUntilStockout,
            'predicted_stockout_date' => $this->formatDateString($result->predictedStockoutDate),
            'recommended_reorder_qty' => $result->recommendedReorderQty,
            'confidence_score' => $result->confidenceScore,
            'generated_at' => $result->generatedAt->toISOString(),
            'historical_daily' => $result->historicalDailyConsumption,
            'forecast_daily' => $result->forecastDailyConsumption,
            'history_window_days' => $result->lookbackDays,
            'forecast_horizon_days' => $result->forecastHorizonDays,
            'lead_time_days' => $result->leadTimeDays,
            'safety_stock_days' => $result->safetyStockDays,
            'has_sufficient_history' => $result->hasSufficientHistory,
        ];
    }

    /**
     * @param  array<string, mixed>  $forecast
     */
    public function confidenceExplanation(array $forecast): string
    {
        $score = $forecast['confidence_score'] ?? null;
        $hasHistory = (bool) ($forecast['has_sufficient_history'] ?? false);

        if (! $hasHistory) {
            return __('The model has limited issuance history for this product. Confidence is capped until more daily consumption data is recorded.');
        }

        if ($score === null) {
            return __('Confidence could not be calculated because no consumption history is available yet.');
        }

        if ($score >= 75) {
            return __('Consumption patterns are relatively stable across the lookback window, so the forecast is suitable for replenishment planning.');
        }

        if ($score >= 50) {
            return __('Demand shows moderate variability. Use this forecast as guidance and review recent issuance trends before finalizing orders.');
        }

        return __('Demand is highly variable or sparse. Treat the reorder recommendation as directional and validate with the supply team.');
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function methodOptions(): array
    {
        return [
            ['value' => 'moving_average', 'label' => __('Moving average')],
            ['value' => 'exponential_smoothing', 'label' => __('Exponential smoothing')],
            ['value' => 'seasonal', 'label' => __('Seasonal pattern')],
        ];
    }
}
