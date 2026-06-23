<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('receiving consumables increments stock and creates lot + movement', function () {
    $clerk = User::factory()->create();
    $clerk->assignRole('Admin');
    $csrfToken = 'receiving-store-token';

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-REC-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 0]);

    $this->actingAs($clerk)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.receiving.store', absolute: false), [
            '_token' => $csrfToken,
            'sku' => 'SKU-REC-001',
            'qty' => 5,
        ])
        ->assertRedirect();

    $stock = ProductStock::query()->where('product_id', $product->id)->firstOrFail();
    expect($stock->on_hand_qty)->toBe(5);

    expect(StockLot::query()->where('product_id', $product->id)->count())->toBe(1);
    expect(StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'receive')->count())
        ->toBe(1);
});

test('receiving can update a purchase order line when identifiers are provided', function () {
    $clerk = User::factory()->create();
    $clerk->assignRole('Admin');
    $csrfToken = 'receiving-po-token';

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-REC-PO-001',
        'supplier_id' => $supplier->id,
    ]);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 0]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'qty_ordered' => 8,
        'qty_received' => 0,
        'unit_price' => 50,
        'subtotal' => 400,
    ]);

    $this->actingAs($clerk)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.receiving.store', absolute: false), [
            '_token' => $csrfToken,
            'sku' => 'SKU-REC-PO-001',
            'qty' => 5,
            'purchase_order_id' => $purchaseOrder->id,
            'purchase_order_line_id' => $line->id,
        ])
        ->assertRedirect();

    $line->refresh();
    $purchaseOrder->refresh();

    expect($line->qty_received)->toBe(5)
        ->and($purchaseOrder->status->value)->toBe('partial');
});

// Checkout/sales flow removed; replaced by requisition issuance & digital handover.
