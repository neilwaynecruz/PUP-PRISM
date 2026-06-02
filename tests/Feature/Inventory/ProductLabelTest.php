<?php

use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('inventory clerk can view product label screen', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    $product = Product::factory()->create([
        'sku' => 'SKU-LABEL-0001',
        'name' => 'Label Product',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/labels/ProductLabel')
            ->where('product.sku', 'SKU-LABEL-0001')
            ->where('product.name', 'Label Product')
            ->where('qr_svg', fn (string $svg) => str_contains($svg, '<svg') && str_contains($svg, 'path')));
});

test('cashier cannot view product label screen', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertForbidden();
});
