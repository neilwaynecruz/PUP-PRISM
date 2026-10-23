<?php

namespace Database\Factories;

use App\Models\Origin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Origin>
 */
class OriginFactory extends Factory
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
        $institutionalSources = [
            'University General Fund',
            'Local Government Grant',
            'Commission on Higher Education Grant',
            'Research Program Fund',
            'Alumni Donation Program',
            'International Partnership Grant',
        ];
        $sourceName = $institutionalSources[($index - 1) % count($institutionalSources)];

        return [
            'name' => $index <= count($institutionalSources)
                ? $sourceName
                : "{$sourceName} Allocation {$index}",
            'is_active' => true,
        ];
    }
}
