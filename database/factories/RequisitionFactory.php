<?php

namespace Database\Factories;

use App\Models\Position;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requisition>
 */
class RequisitionFactory extends Factory
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
            'requester_id' => User::factory()->state(fn () => ['position_id' => $requesterPosition]),
            'requester_position_id' => $requesterPosition,
            'approver_id' => null,
            'approver_position_id' => null,
            'requested_ip_address' => fake()->ipv4(),
            'approved_ip_address' => null,
            'approved_at' => null,
            'issued_by' => null,
            'issued_position_id' => null,
            'issued_ip_address' => null,
            'issued_at' => null,
            'status' => 'Submitted',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
