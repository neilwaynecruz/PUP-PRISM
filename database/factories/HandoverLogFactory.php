<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HandoverLog>
 */
class HandoverLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $fromPosition = Position::factory();
        $toPosition = Position::factory();

        return [
            'asset_id' => Asset::factory()->state(fn () => ['position_id' => $fromPosition]),
            'from_user_id' => User::factory()->state(fn () => ['position_id' => $fromPosition]),
            'to_user_id' => User::factory()->state(fn () => ['position_id' => $toPosition]),
            'from_position_id' => $fromPosition,
            'to_position_id' => $toPosition,
            'initiated_by' => User::factory()->state(fn () => ['position_id' => $fromPosition]),
            'initiated_at' => fake()->dateTimeBetween('-5 days', '-1 day'),
            'verified_at' => null,
            'verified_by' => null,
            'verification_token_hash' => fake()->sha256(),
            'ip_address' => fake()->ipv4(),
            'verified_ip_address' => null,
            'signature_png' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
