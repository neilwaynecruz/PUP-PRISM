<?php

namespace Database\Factories;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
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
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'status' => AssetStatus::Available,
            'lifecycle_status' => AssetLifecycleStatus::Active,
            'condition_status' => AssetConditionStatus::Serviceable,
            'acquisition_date' => fake()->dateTimeBetween('-4 years', '-1 month'),
            'acquisition_cost' => fake()->randomFloat(2, 1000, 100000),
            'warranty_expires_at' => fake()->dateTimeBetween('+1 month', '+3 years'),
            'installed_at' => null,
            'maintenance_interval_days' => null,
        ];
    }

    public function checkedOut(): static
    {
        return $this->state(fn () => ['status' => AssetStatus::CheckedOut]);
    }

    public function assignedToPosition(?Position $position = null): static
    {
        return $this->state(fn () => [
            'position_id' => $position?->id ?? Position::factory(),
        ]);
    }
}
