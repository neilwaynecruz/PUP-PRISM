<?php

use App\Enums\AssetStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Asset;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    $this->withoutVite();
});

function receivingUser(string $role): User
{
    $factory = User::factory();
    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }
    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('multi-line purchase order receiving is processed atomically', function () {
    $user = receivingUser('Supply Head');
    $supplier = Supplier::factory()->create();

    $consumable = Product::factory()->consumable()->create(['supplier_id' => $supplier->id]);
    $stock = ProductStock::factory()->create(['product_id' => $consumable->id, 'on_hand_qty' => 10]);

    $assetProduct = Product::factory()->asset()->create(['supplier_id' => $supplier->id]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line1 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $consumable->id,
        'qty_ordered' => 20,
        'qty_received' => 0,
        'unit_price' => 10,
        'subtotal' => 200,
    ]);

    $line2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $assetProduct->id,
        'qty_ordered' => 2,
        'qty_received' => 0,
        'unit_price' => 500,
        'subtotal' => 1000,
    ]);

    $csrfToken = 'csrf-token-receive-atomic';

    $response = $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.receive', $po, absolute: false), [
            '_token' => $csrfToken,
            'lines' => [
                [
                    'purchase_order_line_id' => $line1->id,
                    'qty_received' => 15,
                    'reference_no' => 'REC-LOT-001',
                ],
                [
                    'purchase_order_line_id' => $line2->id,
                    'tag_codes' => ['AST-RCV-001', 'AST-RCV-002'],
                ],
            ],
        ]);

    $response->assertRedirect();

    $line1->refresh();
    $line2->refresh();
    $po->refresh();
    $stock->refresh();

    expect($line1->qty_received)->toBe(15)
        ->and($line2->qty_received)->toBe(2)
        ->and($stock->on_hand_qty)->toBe(25)
        ->and(Asset::query()->where('product_id', $assetProduct->id)->count())->toBe(2)
        ->and($po->status)->toBe(PurchaseOrderStatus::Partial);
});

test('failure on a later purchase order line rolls back earlier lines completely', function () {
    $user = receivingUser('Supply Head');
    $supplier = Supplier::factory()->create();

    $prod1 = Product::factory()->consumable()->create(['supplier_id' => $supplier->id]);
    $stock1 = ProductStock::factory()->create(['product_id' => $prod1->id, 'on_hand_qty' => 5]);

    $prod2 = Product::factory()->asset()->create(['supplier_id' => $supplier->id]);

    // Create an existing asset with tag AST-EXISTING to force a collision on line 2
    Asset::factory()->create([
        'product_id' => $prod2->id,
        'tag_code' => 'AST-EXISTING',
        'status' => AssetStatus::Available,
    ]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line1 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $prod1->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $line2 = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $prod2->id,
        'qty_ordered' => 2,
        'qty_received' => 0,
    ]);

    $initialMovementsCount = StockMovement::query()->count();
    $initialLotsCount = StockLot::query()->count();

    $csrfToken = 'csrf-token-rollback-test';

    // Attempt multi-line receipt where Line 1 is valid, but Line 2 reuses AST-EXISTING
    $response = $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.receive', $po, absolute: false), [
            '_token' => $csrfToken,
            'lines' => [
                [
                    'purchase_order_line_id' => $line1->id,
                    'qty_received' => 8,
                ],
                [
                    'purchase_order_line_id' => $line2->id,
                    'tag_codes' => ['AST-EXISTING'], // Collision causes rollback
                ],
            ],
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('inertia.flash_data.toast', fn ($toast) => is_array($toast) && $toast['type'] === 'error');

    $line1->refresh();
    $line2->refresh();
    $stock1->refresh();
    $po->refresh();

    // Verify complete rollback: no changes to line 1 or line 2, no movements, no lots
    expect($line1->qty_received)->toBe(0)
        ->and($line2->qty_received)->toBe(0)
        ->and($stock1->on_hand_qty)->toBe(5)
        ->and($po->status)->toBe(PurchaseOrderStatus::Sent)
        ->and(StockMovement::query()->count())->toBe($initialMovementsCount)
        ->and(StockLot::query()->count())->toBe($initialLotsCount);
});

test('over-receiving is rejected and does not update line quantity', function () {
    $user = receivingUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create(['supplier_id' => $supplier->id]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 5,
        'qty_received' => 4, // 1 remaining
    ]);

    $csrfToken = 'csrf-token-overreceive';

    $response = $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.receive', $po, absolute: false), [
            '_token' => $csrfToken,
            'lines' => [
                [
                    'purchase_order_line_id' => $line->id,
                    'qty_received' => 2, // exceeds 1 remaining
                ],
            ],
        ]);

    $response->assertSessionHasErrors();
    $line->refresh();
    expect($line->qty_received)->toBe(4);
});

test('idempotency prevents duplicate stock increases on retried submissions', function () {
    $user = receivingUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create(['supplier_id' => $supplier->id]);
    $stock = ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 0]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $inventory = app(InventoryService::class);
    $linesPayload = [
        [
            'purchase_order_line_id' => $line->id,
            'qty_received' => 5,
            'reference_no' => 'IDEM-REF-1',
        ],
    ];

    // First submission
    $inventory->receivePurchaseOrder($user, $po, $linesPayload, idempotencyKey: 'idem-key-12345');

    $line->refresh();
    $stock->refresh();
    expect($line->qty_received)->toBe(5)
        ->and($stock->on_hand_qty)->toBe(5);

    // Immediate duplicate retry with same idempotency key
    $inventory->receivePurchaseOrder($user, $po, $linesPayload, idempotencyKey: 'idem-key-12345');

    $line->refresh();
    $stock->refresh();
    // Must remain 5, not double to 10
    expect($line->qty_received)->toBe(5)
        ->and($stock->on_hand_qty)->toBe(5);
});

test('partial receiving across separate submissions transitions to received when complete', function () {
    $user = receivingUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create(['supplier_id' => $supplier->id]);

    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
    ]);

    $inventory = app(InventoryService::class);

    // Submission 1: receive 6/10
    $inventory->receivePurchaseOrder($user, $po, [
        ['purchase_order_line_id' => $line->id, 'qty_received' => 6],
    ]);

    $po->refresh();
    $line->refresh();
    expect($line->qty_received)->toBe(6)
        ->and($po->status)->toBe(PurchaseOrderStatus::Partial);

    // Submission 2: receive remaining 4/10
    $inventory->receivePurchaseOrder($user, $po, [
        ['purchase_order_line_id' => $line->id, 'qty_received' => 4],
    ]);

    $po->refresh();
    $line->refresh();
    expect($line->qty_received)->toBe(10)
        ->and($po->status)->toBe(PurchaseOrderStatus::Received)
        ->and($po->received_at)->not->toBeNull();
});
