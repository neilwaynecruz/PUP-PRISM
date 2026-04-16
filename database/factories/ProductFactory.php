<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->ean13(),
            'name' => fake()->words(nb: 3, asText: true),
            'category_id' => Category::factory(),
            'origin_id' => Origin::factory(),
            'type' => 'consumable',
            'reorder_threshold' => fake()->numberBetween(0, 50),
            'is_active' => true,
        ];
    }

    public function consumable(): static
    {
        return $this->state(fn () => ['type' => 'consumable']);
    }

    public function asset(): static
    {
        return $this->state(fn () => ['type' => 'asset']);
    }
}
