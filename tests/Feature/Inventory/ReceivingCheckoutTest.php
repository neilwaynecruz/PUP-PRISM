<?php

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
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

// Checkout/sales flow removed; replaced by requisition issuance & digital handover.
