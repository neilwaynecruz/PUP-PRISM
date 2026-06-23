<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'contact_person' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            'website' => fake()->optional()->url(),
            'payment_terms' => fake()->randomElement(['COD', 'Net 15', 'Net 30', 'Net 45']),
            'lead_time_days' => fake()->numberBetween(3, 21),
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
