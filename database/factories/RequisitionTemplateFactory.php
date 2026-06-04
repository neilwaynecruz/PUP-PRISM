<?php

namespace Database\Factories;

use App\Models\RequisitionTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisitionTemplate>
 */
class RequisitionTemplateFactory extends Factory
{
    protected $model = RequisitionTemplate::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->words(3, true),
            'notes' => fake()->optional()->sentence(),
            'lines' => [
                [
                    'sku' => fake()->bothify('SKU-####'),
                    'name' => fake()->words(2, true),
                    'qty_requested' => fake()->numberBetween(1, 5),
                ],
            ],
        ];
    }
}
