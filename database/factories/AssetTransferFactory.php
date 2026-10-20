<?php

namespace Database\Factories;

use App\Enums\AssetTransferStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetTransfer>
 */
class AssetTransferFactory extends Factory
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
            'from_location_id' => null,
            'to_location_id' => Location::factory(),
            'status' => AssetTransferStatus::Pending,
            'reason' => fake()->sentence(),
            'transfer_date' => now()->toDateString(),
            'initiated_by' => User::factory(),
        ];
    }
}
