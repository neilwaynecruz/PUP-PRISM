<?php

use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

test('property custodian can view product index but cannot access create screen', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $this->actingAs($user)
        ->get(route('inventory.products.index', absolute: false))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('inventory.products.create', absolute: false))
        ->assertForbidden();
});

test('supply head can create a product', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    $category = Category::factory()->create();
    $origin = Origin::factory()->create();

    $payload = [
        'sku' => 'SKU-TEST-0001',
        'name' => 'Test Product',
        'type' => 'consumable',
        'category_id' => $category->id,
        'origin_id' => $origin->id,
        'reorder_threshold' => 10,
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->post(route('inventory.products.store', absolute: false), $payload)
        ->assertRedirect(route('inventory.products.index', absolute: false));

    expect(Product::query()->where('sku', 'SKU-TEST-0001')->exists())->toBeTrue();
});
