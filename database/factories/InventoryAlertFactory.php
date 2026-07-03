<?php

namespace Database\Factories;

use App\Models\InventoryAlert;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryAlert>
 */
class InventoryAlertFactory extends Factory
{
    protected $model = InventoryAlert::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'low_stock',
            'product_id' => Product::factory()->consumable(),
            'stock_lot_id' => null,
            'message' => 'Low stock warning.',
            'detected_at' => now(),
            'resolved_at' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (): array => [
            'resolved_at' => now(),
        ]);
    }
}
