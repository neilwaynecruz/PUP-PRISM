<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cashier_id' => User::factory(),
            'sold_at' => fake()->dateTimeBetween('-30 days', 'now'),
            'total_amount' => fake()->optional()->randomFloat(2, 1, 10000),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
