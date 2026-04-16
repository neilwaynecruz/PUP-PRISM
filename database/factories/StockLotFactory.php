<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockLot>
 */
class StockLotFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qtyReceived = fake()->numberBetween(1, 500);
        $qtyRemaining = fake()->numberBetween(0, $qtyReceived);
        $receivedAt = fake()->dateTimeBetween('-30 days', 'now');

        return [
            'product_id' => Product::factory()->consumable(),
            'reference_no' => fake()->optional()->bothify('DEL-#####'),
            'received_at' => $receivedAt,
            'expires_at' => fake()->optional()->dateTimeBetween($receivedAt, '+180 days')?->format('Y-m-d'),
            'qty_received' => $qtyReceived,
            'qty_remaining' => $qtyRemaining,
        ];
    }
}
