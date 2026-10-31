<?php

namespace Database\Factories;

use App\Models\UnitOfMeasurement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitOfMeasurement>
 */
class UnitOfMeasurementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('unit-########'),
            'name' => fake()->unique()->bothify('Unit ####-????'),
            'abbreviation' => fake()->unique()->bothify('u####'),
            'is_active' => true,
        ];
    }
}
