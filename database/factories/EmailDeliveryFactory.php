<?php

namespace Database\Factories;

use App\Models\EmailDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailDelivery>
 */
class EmailDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => hash('sha256', fake()->uuid()),
            'notification_type' => 'test-notification',
            'status' => 'queued',
            'expires_at' => now()->addHours(23),
        ];
    }
}
