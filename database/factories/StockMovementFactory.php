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
        $qtyDelta = fake()->numberBetween(1, 50);

        return [
            'movement_type' => 'receive',
            'product_id' => Product::factory()->consumable(),
            'stock_lot_id' => null,
            'asset_id' => null,
            'requisition_id' => null,
            'qty_delta' => $qtyDelta,
            'qty_before' => 0,
            'qty_after' => $qtyDelta,
            'performed_by' => User::factory(),
            'accountable_position_id' => null,
            'ip_address' => fake()->ipv4(),
            'performed_at' => $performedAt,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
