<?php

use App\Models\Asset;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductStock;
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

test('admin users see dashboard alerts, low stock, and asset details', function () {
    Role::findOrCreate('Admin');
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $lowStockProduct = Product::factory()->consumable()->create([
        'name' => 'Bond Paper',
        'reorder_threshold' => 10,
    ]);

    ProductStock::factory()
        ->for($lowStockProduct, 'product')
        ->create(['on_hand_qty' => 5]);

    InventoryAlert::query()->create([
        'type' => 'low_stock',
        'product_id' => $lowStockProduct->id,
        'message' => 'Bond Paper is low on stock.',
        'detected_at' => now(),
    ]);

    $assetProduct = Product::factory()->asset()->create([
        'name' => 'Department Laptop',
    ]);

    Asset::factory()
        ->for($assetProduct, 'product')
        ->create([
            'status' => 'Unserviceable',
            'tag_code' => 'AST-00000001',
        ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('alerts', 1)
            ->where('alerts.0.message', 'Bond Paper is low on stock.')
            ->has('lowStock', 1)
            ->where('lowStock.0.name', 'Bond Paper')
            ->where('lowStock.0.on_hand_qty', 5)
            ->where('lowStock.0.reorder_threshold', 10)
            ->has('unserviceableAssets', 1)
            ->where('unserviceableAssets.0.name', 'Department Laptop')
            ->where('unserviceableAssets.0.status', 'Unserviceable')
            ->where('unserviceableAssets.0.tag_code', 'AST-00000001')
            ->where('assetStatusCounts.labels', ['Unserviceable', 'Condemned'])
            ->where('assetStatusCounts.data', [1, 0]));
});
