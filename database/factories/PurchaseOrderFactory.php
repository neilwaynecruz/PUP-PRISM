<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'po_number' => 'PO-'.fake()->unique()->numerify('########').'-'.fake()->numerify('####'),
            'status' => PurchaseOrderStatus::Draft,
            'subtotal' => 0,
            'tax' => 0,
            'total_amount' => 0,
            'requested_by' => User::factory(),
            'approved_by' => null,
            'expected_delivery_at' => fake()->optional()->dateTimeBetween('+3 days', '+14 days'),
            'sent_at' => null,
            'received_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
