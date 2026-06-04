<?php

use App\Enums\ProductType;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Property Custodian');
});

test('product show page exposes enriched stock movement history', function () {
    $position = Position::factory()->create([
        'title' => 'Supply Office',
    ]);
    $position->load('department');

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'type' => ProductType::Consumable,
    ]);

    ProductStock::query()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 12,
    ]);

    $requisition = Requisition::factory()->create();

    $lot = StockLot::query()->create([
        'product_id' => $product->id,
        'reference_no' => 'PO-2026-001',
        'received_at' => now()->subDay(),
        'expires_at' => null,
        'qty_received' => 12,
        'qty_remaining' => 7,
    ]);

    StockMovement::query()->create([
        'movement_type' => 'issue',
        'product_id' => $product->id,
        'stock_lot_id' => $lot->id,
        'asset_id' => null,
        'requisition_id' => $requisition->id,
        'qty_delta' => -5,
        'qty_before' => 12,
        'qty_after' => 7,
        'performed_by' => $user->id,
        'accountable_position_id' => $position->id,
        'ip_address' => '127.0.0.1',
        'performed_at' => now(),
        'notes' => 'Issued to the laboratory',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.products.show', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/products/Show')
            ->has('stockMovements', 1)
            ->where('stockMovements.0.source', 'Requisition issuance')
            ->where('stockMovements.0.reference', "Requisition #{$requisition->id}")
            ->where('stockMovements.0.qty_before', 12)
            ->where('stockMovements.0.qty_after', 7)
            ->where(
                'stockMovements.0.accountable_position',
                trim("Supply Office, {$position->department?->name}"),
            ));
});
