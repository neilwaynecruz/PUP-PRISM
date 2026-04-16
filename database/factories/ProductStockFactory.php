<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $onHand = fake()->numberBetween(0, 500);
        $reserved = fake()->numberBetween(0, min(50, $onHand));

        return [
            'product_id' => Product::factory()->consumable(),
            'on_hand_qty' => $onHand,
            'reserved_qty' => $reserved,
        ];
    }
}
