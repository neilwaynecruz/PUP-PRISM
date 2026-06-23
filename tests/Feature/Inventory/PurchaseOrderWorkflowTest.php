<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLot;
use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

test('supply head can create a draft purchase order', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'purchase-order-store-token';

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'unit_price' => 145.50,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.store', absolute: false), [
            '_token' => $csrfToken,
            'supplier_id' => $supplier->id,
            'tax' => 50,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'qty_ordered' => 12,
                    'unit_price' => 145.50,
                ],
            ],
        ])
        ->assertRedirect();

    $purchaseOrder = PurchaseOrder::query()->firstOrFail();
    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Draft)
        ->and($purchaseOrder->total_amount)->toBe(1796.0);

    expect(PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrder->id)->count())
        ->toBe(1);
});

test('admin can send a draft purchase order', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $csrfToken = 'purchase-order-send-token';

    $purchaseOrder = PurchaseOrder::factory()->create([
        'status' => PurchaseOrderStatus::Draft,
        'sent_at' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.purchase-orders.send', $purchaseOrder, absolute: false), [
            '_token' => $csrfToken,
        ])
        ->assertRedirect();

    $purchaseOrder->refresh();

    expect($purchaseOrder->status)->toBe(PurchaseOrderStatus::Sent)
        ->and($purchaseOrder->sent_at)->not->toBeNull()
        ->and($purchaseOrder->approved_by)->toBe($user->id);
});

test('supply head can receive a sent purchase order line', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'purchase-order-receive-token';

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
    ]);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 0,
    ]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 42,
        'subtotal' => 420,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.receive', $purchaseOrder, absolute: false), [
            '_token' => $csrfToken,
            'lines' => [
                [
                    'purchase_order_line_id' => $line->id,
                    'qty_received' => 6,
                    'reference_no' => 'DR-PO-2001',
                ],
            ],
        ])
        ->assertRedirect();

    $line->refresh();
    $purchaseOrder->refresh();

    expect($line->qty_received)->toBe(6)
        ->and($purchaseOrder->status)->toBe(PurchaseOrderStatus::Partial)
        ->and(StockLot::query()->where('product_id', $product->id)->exists())->toBeTrue();
});
