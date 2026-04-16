<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requesterPosition = Position::factory();

        return [
            'asset_id' => Asset::factory(),
            'requester_id' => User::factory()->state(fn () => ['position_id' => $requesterPosition]),
            'requester_position_id' => $requesterPosition,
            'approver_id' => null,
            'approver_position_id' => null,
            'requested_ip_address' => fake()->ipv4(),
            'approved_ip_address' => null,
            'start_at' => fake()->dateTimeBetween('+1 day', '+5 days'),
            'end_at' => fake()->dateTimeBetween('+5 days', '+10 days'),
            'status' => 'Requested',
            'purpose' => fake()->sentence(),
        ];
    }
}
