<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderLine>
 */
class PurchaseOrderLineFactory extends Factory
{
    protected $model = PurchaseOrderLine::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qtyOrdered = fake()->numberBetween(1, 25);
        $unitPrice = fake()->randomFloat(2, 10, 1000);

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => Product::factory()->consumable(),
            'qty_ordered' => $qtyOrdered,
            'qty_received' => 0,
            'unit_price' => $unitPrice,
            'subtotal' => round($qtyOrdered * $unitPrice, 2),
        ];
    }
}
