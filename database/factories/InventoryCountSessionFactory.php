<?php

namespace Database\Factories;

use App\Enums\InventoryCountStatus;
use App\Models\InventoryCountSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryCountSession>
 */
class InventoryCountSessionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_no' => fake()->unique()->bothify('COUNT-########'),
            'location_id' => null,
            'created_by' => User::factory(),
            'assigned_to' => User::factory(),
            'approved_by' => null,
            'counting_date' => now()->toDateString(),
            'scope' => 'Selected inventory items',
            'status' => InventoryCountStatus::Draft,
            'snapshot_at' => now(),
            'movement_cutoff_id' => 0,
            'remarks' => null,
        ];
    }
}
