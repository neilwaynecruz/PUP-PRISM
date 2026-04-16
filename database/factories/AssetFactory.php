<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Position;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory()->asset(),
            'position_id' => null,
            'tag_code' => fake()->unique()->bothify('AST-########'),
            'status' => 'Available',
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['status' => 'Checked_Out']);
    }

    public function assignedToPosition(?Position $position = null): static
    {
        return $this->state(fn () => [
            'position_id' => $position?->id ?? Position::factory(),
        ]);
    }
}
