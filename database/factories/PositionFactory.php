<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Position>
 */
class PositionFactory extends Factory
{
    protected static int $sequence = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = ++static::$sequence;

        return [
            'department_id' => Department::factory(),
            'title' => fake()->jobTitle(),
            'code' => sprintf('POS-%03d', $index),
            'is_active' => true,
        ];
    }
}
