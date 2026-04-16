<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $performedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'movement_type' => 'adjust',
            'product_id' => Product::factory()->consumable(),
            'stock_lot_id' => null,
            'asset_id' => null,
            'sale_id' => null,
            'qty_delta' => fake()->numberBetween(-50, 50),
            'performed_by' => User::factory(),
            'performed_at' => $performedAt,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
