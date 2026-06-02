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
        return [
            'product_id' => Product::factory()->consumable(),
            'on_hand_qty' => fake()->numberBetween(0, 500),
        ];
    }
}
