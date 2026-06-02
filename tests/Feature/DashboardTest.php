<?php

use App\Models\Asset;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('admin dashboard aggregates unserviceable and condemned asset counts', function () {
    Role::findOrCreate('Admin');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->asset()->create();

    Asset::factory()->count(2)->recycle($product)->create(['status' => 'Unserviceable']);
    Asset::factory()->recycle($product)->create(['status' => 'Condemned']);
    Asset::factory()->recycle($product)->create(['status' => 'Available']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('assetStatusCounts.labels', ['Unserviceable', 'Condemned'])
            ->where('assetStatusCounts.data', [2, 1]));
});
