<?php

namespace Database\Factories;

use App\Enums\FacilityGroup;
use App\Enums\LocationType;
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
            'parent_location_id' => null,
            'code' => fake()->unique()->bothify('LOC-####'),
            'name' => fake()->words(3, true),
            'facility_group' => fake()->randomElement(FacilityGroup::cases()),
            'location_type' => fake()->randomElement(LocationType::cases()),
            'building' => fake()->optional()->word(),
            'room' => fake()->optional()->bothify('Room ##'),
            'is_bookable' => false,
            'capacity' => null,
            'is_active' => true,
        ];
    }
}
