<?php

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

test('purchase order creation page pre-fills product, supplier, and recommended reorder quantity from query parameters', function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');

    $supplyHead = User::factory()->withTwoFactor()->create();
    $supplyHead->assignRole('Supply Head');

    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-RESTOCK-001',
        'supplier_id' => $supplier->id,
        'unit_price' => 125.50,
        'is_active' => true,
    ]);

    actingAs($supplyHead);
    $response = get(route('inventory.purchase-orders.create', [
        'product_id' => $product->id,
        'qty' => 45,
    ], absolute: false));

    $response->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('inventory/purchase-orders/Create')
        ->where('initialSupplierId', $supplier->id)
        ->where('initialLines', [
            [
                'product_id' => (string) $product->id,
                'qty_ordered' => '45',
                'unit_price' => '125.5',
            ],
        ])
    );
});
