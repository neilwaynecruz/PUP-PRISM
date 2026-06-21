<?php

namespace Database\Factories;

use App\Models\ForecastProfile;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ForecastProfile>
 */
class ForecastProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->consumable(),
            'method' => 'exponential_smoothing',
            'lookback_days' => 90,
            'forecast_horizon_days' => 30,
            'lead_time_days' => 14,
            'safety_stock_days' => 7,
            'smoothing_factor' => 0.35,
            'trend_factor' => 0.15,
            'is_active' => true,
        ];
    }
}
