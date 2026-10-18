<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Procurement\PurchaseOrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    $this->withoutVite();
});

function createPoUser(string $role): User
{
    $factory = User::factory();
    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }
    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('purchase orders receive sequential numbers on the same date', function () {
    $generator = app(PurchaseOrderNumberGenerator::class);
    $today = CarbonImmutable::parse('2026-10-18');

    $po1 = $generator->nextNumber($today);
    $po2 = $generator->nextNumber($today);
    $po3 = $generator->nextNumber($today);

    expect($po1)->toBe('PO-20261018-0001')
        ->and($po2)->toBe('PO-20261018-0002')
        ->and($po3)->toBe('PO-20261018-0003');
});

test('purchase order number generation respects soft-deleted purchase orders', function () {
    $supplier = Supplier::factory()->create();
    $user = createPoUser('Supply Head');

    $po1 = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'requested_by' => $user->id,
        'po_number' => 'PO-20261018-0001',
    ]);

    // Soft delete the first purchase order
    $po1->delete();
    expect($po1->trashed())->toBeTrue();

    // The generator must not produce PO-20261018-0001 again
    $generator = app(PurchaseOrderNumberGenerator::class);
    $nextPo = $generator->nextNumber(CarbonImmutable::parse('2026-10-18'));

    expect($nextPo)->toBe('PO-20261018-0002');
});

test('purchase order numbering preserves historical purchase orders without renumbering', function () {
    $supplier = Supplier::factory()->create();
    $user = createPoUser('Supply Head');

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'requested_by' => $user->id,
        'po_number' => 'PO-20260301-0005',
    ]);

    $generator = app(PurchaseOrderNumberGenerator::class);
    $nextPoHistoricalDate = $generator->nextNumber(CarbonImmutable::parse('2026-03-01'));

    expect($nextPoHistoricalDate)->toBe('PO-20260301-0006');
});

test('database enforces unique constraint on po_number', function () {
    $supplier = Supplier::factory()->create();
    $user = createPoUser('Supply Head');

    PurchaseOrder::create([
        'supplier_id' => $supplier->id,
        'requested_by' => $user->id,
        'po_number' => 'PO-20261018-9999',
        'status' => PurchaseOrderStatus::Draft,
        'subtotal' => 100,
        'tax' => 0,
        'total_amount' => 100,
    ]);

    expect(function () use ($supplier, $user) {
        PurchaseOrder::create([
            'supplier_id' => $supplier->id,
            'requested_by' => $user->id,
            'po_number' => 'PO-20261018-9999', // duplicate
            'status' => PurchaseOrderStatus::Draft,
            'subtotal' => 200,
            'tax' => 0,
            'total_amount' => 200,
        ]);
    })->toThrow(QueryException::class);
});

test('store endpoint uses atomic generator and increments sequence', function () {
    $user = createPoUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'unit_price' => 50.00,
    ]);

    $csrfToken = 'csrf-po-test-token';

    $response = $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.store', absolute: false), [
            '_token' => $csrfToken,
            'supplier_id' => $supplier->id,
            'tax' => 0,
            'lines' => [
                [
                    'product_id' => $product->id,
                    'qty_ordered' => 5,
                    'unit_price' => 50.00,
                ],
            ],
        ]);

    $response->assertRedirect();

    $createdPo = PurchaseOrder::query()->latest('id')->firstOrFail();
    $todayPrefix = now()->format('Ymd');
    expect($createdPo->po_number)->toStartWith("PO-{$todayPrefix}-");
});
