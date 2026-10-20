<?php

namespace Database\Factories;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => null,
            'code' => fake()->unique()->bothify('LOC-####'),
            'name' => fake()->words(3, true),
            'building' => fake()->optional()->word(),
            'room' => fake()->optional()->bothify('Room ##'),
            'is_active' => true,
        ];
    }
}
