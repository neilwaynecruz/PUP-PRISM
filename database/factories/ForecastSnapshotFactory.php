<?php

namespace Database\Factories;

use App\Models\ForecastSnapshot;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastSnapshot>
 */
class ForecastSnapshotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $forecastDate = now()->toDateString();
        $generatedAt = now();

        return [
            'product_id' => Product::factory()->consumable(),
            'forecast_date' => $forecastDate,
            'forecast_method' => 'exponential_smoothing',
            'current_on_hand_qty' => 18,
            'reorder_point_qty' => 42,
            'predicted_daily_consumption' => 3.5,
            'predicted_days_until_stockout' => 5,
            'predicted_stockout_date' => now()->addDays(5)->toDateString(),
            'recommended_reorder_qty' => 24,
            'confidence_score' => 81.5,
            'raw_data' => [
                'historical_daily' => [],
                'forecast_daily' => [],
                'summary' => [
                    'lookback_days' => 90,
                    'forecast_horizon_days' => 30,
                    'lead_time_days' => 14,
                    'safety_stock_days' => 7,
                    'history_days' => 90,
                    'non_zero_history_days' => 18,
                    'has_sufficient_history' => true,
                ],
            ],
            'generated_at' => $generatedAt,
        ];
    }
}
