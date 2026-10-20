<?php

namespace Database\Factories;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetMaintenance>
 */
class AssetMaintenanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'maintenance_type' => MaintenanceType::Inspection,
            'status' => MaintenanceStatus::Reported,
            'reported_issue' => fake()->sentence(),
            'description' => fake()->optional()->sentence(),
            'reported_by' => User::factory(),
            'reported_date' => now()->toDateString(),
            'assigned_to' => null,
            'repair_cost' => null,
        ];
    }
}
