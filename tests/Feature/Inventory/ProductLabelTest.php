<?php

use App\Models\Product;
use App\Models\User;
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

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertOk();
});

test('cashier cannot view product label screen', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertForbidden();
});
