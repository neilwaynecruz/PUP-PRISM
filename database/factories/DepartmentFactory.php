<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
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
            'name' => fake()->unique()->company().' Department',
            'code' => sprintf('DEPT-%03d', $index),
            'is_active' => true,
        ];
    }
}
