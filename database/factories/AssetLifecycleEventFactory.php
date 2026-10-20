<?php

namespace Database\Factories;

use App\Enums\ApprovalStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetLifecycleEvent>
 */
class AssetLifecycleEventFactory extends Factory
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
            'event_type' => AssetLifecycleEventType::Retirement,
            'status' => ApprovalStatus::Pending,
            'from_lifecycle_status' => AssetLifecycleStatus::Active,
            'to_lifecycle_status' => AssetLifecycleStatus::Retired,
            'reason' => fake()->sentence(),
            'initiated_by' => User::factory(),
        ];
    }
}
