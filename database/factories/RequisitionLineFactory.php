<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequisitionLine>
 */
class RequisitionLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requested = fake()->numberBetween(1, 10);

        return [
            'requisition_id' => Requisition::factory(),
            'product_id' => Product::factory()->consumable(),
            'qty_requested' => $requested,
            'qty_issued' => 0,
        ];
    }
}
