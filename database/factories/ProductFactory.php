<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Supplier;
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
            'supplier_id' => Supplier::factory(),
            'type' => ProductType::Consumable,
            'reorder_threshold' => fake()->numberBetween(0, 50),
            'lead_time_days' => fake()->numberBetween(3, 30),
            'unit_price' => fake()->randomFloat(2, 10, 2000),
            'is_active' => true,
        ];
    }

    public function consumable(): static
    {
        return $this->state(fn () => ['type' => ProductType::Consumable]);
    }

    public function asset(): static
    {
        return $this->state(fn () => ['type' => ProductType::Asset]);
    }
}
