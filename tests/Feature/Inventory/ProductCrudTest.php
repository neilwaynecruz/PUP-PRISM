<?php

use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
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

test('verified users without inventory roles are forbidden from product routes by policy', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.index', absolute: false))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('inventory.products.show', $product, absolute: false))
        ->assertForbidden();
});

test('property custodian can view product show screen and supply head can open edit screen', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Property Custodian');

    $editor = User::factory()->create();
    $editor->assignRole('Supply Head');

    $product = Product::factory()->create([
        'sku' => 'SKU-SHOW-0001',
        'name' => 'Showcase Product',
    ]);

    $this->actingAs($viewer)
        ->get(route('inventory.products.show', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/products/Show')
            ->where('product.sku', 'SKU-SHOW-0001')
            ->where('product.name', 'Showcase Product'));

    $this->actingAs($editor)
        ->get(route('inventory.products.edit', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/products/Edit')
            ->where('product.id', $product->id));
});

test('supply head can create a product', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'product-test-token';

    $category = Category::factory()->create();
    $origin = Origin::factory()->create();

    $payload = [
        '_token' => $csrfToken,
        'sku' => 'SKU-TEST-0001',
        'name' => 'Test Product',
        'type' => 'consumable',
        'category_id' => $category->id,
        'origin_id' => $origin->id,
        'reorder_threshold' => 10,
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.products.store', absolute: false), $payload)
        ->assertRedirect(route('inventory.products.index', absolute: false));

    expect(Product::query()->where('sku', 'SKU-TEST-0001')->exists())->toBeTrue();
});

test('property custodian cannot store, update or destroy products', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');
    $csrfToken = 'product-test-token';

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.products.store', absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.products.update', $product, absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.products.destroy', $product, absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();
});

test('supply head can update but cannot destroy products', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'product-test-token';

    $category = Category::factory()->create();
    $origin = Origin::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.products.update', $product, absolute: false), [
            '_token' => $csrfToken,
            'sku' => 'SKU-UPDATED-0001',
            'name' => 'Updated Product',
            'type' => 'consumable',
            'category_id' => $category->id,
            'origin_id' => $origin->id,
            'reorder_threshold' => 5,
            'is_active' => true,
        ])
        ->assertRedirect(route('inventory.products.edit', $product, absolute: false));

    expect(Product::query()->where('sku', 'SKU-UPDATED-0001')->exists())->toBeTrue();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.products.destroy', $product, absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();
});

test('admin can destroy products', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $csrfToken = 'product-test-token';

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.products.destroy', $product, absolute: false), ['_token' => $csrfToken])
        ->assertRedirect(route('inventory.products.index', absolute: false));

    expect(Product::query()->whereKey($product->getKey())->exists())->toBeFalse();
});
