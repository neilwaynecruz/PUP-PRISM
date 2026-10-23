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

test('supply head can view product label screen', function () {
    $user = User::factory()->withTwoFactor()->create();
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

test('property custodian cannot view product label screen', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertForbidden();
});

test('supply head can render bulk label sheet with selected products and grid layout', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    $productA = Product::factory()->create(['sku' => 'SKU-BATCH-A', 'name' => 'Batch Item Alpha']);
    $productB = Product::factory()->create(['sku' => 'SKU-BATCH-B', 'name' => 'Batch Item Beta']);

    $this->actingAs($user)
        ->get(route('inventory.products.labels.sheet', [
            'product_ids' => "{$productA->id},{$productB->id}",
            'copies' => 2,
            'grid_preset' => 'compact_30',
            'paper_size' => 'a4',
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/labels/BulkLabelSheet')
            ->where('total_products', 2)
            ->where('initial_config.copies', 2)
            ->where('initial_config.grid_preset', 'compact_30')
            ->has('labels', 2)
            ->where('labels.0.sku', 'SKU-BATCH-A')
            ->where('labels.1.sku', 'SKU-BATCH-B'));
});

test('supply head can download combined printable label sheet PDF', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    $productA = Product::factory()->create(['sku' => 'SKU-PDF-A', 'name' => 'PDF Item Alpha']);
    $productB = Product::factory()->create(['sku' => 'SKU-PDF-B', 'name' => 'PDF Item Beta']);

    $response = $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => [$productA->id, $productB->id],
            'copies' => 1,
            'grid_preset' => 'standard_24',
            'paper_size' => 'a4',
            'show_sku' => true,
            'show_name' => true,
        ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');
    expect($response->headers->get('content-disposition'))->toContain('pup-prism-labels-standard_24');
});

test('authorized users can search products via lookup endpoint', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    $p1 = Product::factory()->consumable()->create(['sku' => 'SKU-SEARCH-BALLPEN', 'name' => 'Black Ballpen 0.5mm']);
    $p2 = Product::factory()->consumable()->create(['sku' => 'SKU-SEARCH-PAPER', 'name' => 'A4 Bond Paper 80gsm']);

    // Lookup by partial query
    $response = $this->actingAs($user)
        ->getJson(route('inventory.products.lookup', ['query' => 'Ballpen'], absolute: false))
        ->assertOk();

    $data = $response->json('products');
    expect($data)->toBeArray()->toHaveCount(1);
    expect($data[0]['sku'])->toBe('SKU-SEARCH-BALLPEN');
    expect($data[0]['name'])->toBe('Black Ballpen 0.5mm');

    // Lookup by exact SKU
    $responseSku = $this->actingAs($user)
        ->getJson(route('inventory.products.lookup', ['sku' => 'SKU-SEARCH-PAPER'], absolute: false))
        ->assertOk();

    $dataSku = $responseSku->json('products');
    expect($dataSku)->toBeArray()->toHaveCount(1);
    expect($dataSku[0]['sku'])->toBe('SKU-SEARCH-PAPER');
});

test('product lookup is case insensitive and ignores repeated search spacing', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    Product::factory()->consumable()->create([
        'sku' => 'SKU-SEARCH-SPACED',
        'name' => 'Black Ballpen 0.5mm',
    ]);

    $this->actingAs($user)
        ->getJson(route('inventory.products.lookup', [
            'query' => '  black   BALLPEN ',
            'type' => 'consumable',
        ], absolute: false))
        ->assertOk()
        ->assertJsonPath('products.0.sku', 'SKU-SEARCH-SPACED');
});

test('bulk label requests require an explicit selection or filtered scope', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.labels.sheet', absolute: false))
        ->assertSessionHasErrors('selection_scope');
});

test('bulk label requests reject malformed, missing, and excessive product selections', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => ['not-an-id'],
        ])
        ->assertSessionHasErrors('product_ids.0');

    $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => [$product->id],
            'copies' => 501,
        ])
        ->assertSessionHasErrors('copies');

    $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => array_fill(0, 501, $product->id),
        ])
        ->assertSessionHasErrors('product_ids');
});

test('bulk labels reject requests that would generate more than five hundred labels', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $products = Product::factory()->count(11)->create([
        'category_id' => null,
        'origin_id' => null,
        'supplier_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => $products->modelKeys(),
            'copies' => 50,
        ])
        ->assertSessionHasErrors('copies');
});

test('letter label presets use heights that fit the printable area', function (string $preset, int $expectedHeight) {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.labels.sheet', [
            'product_ids' => (string) $product->id,
            'grid_preset' => $preset,
            'paper_size' => 'letter',
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('initial_config.layout.cell_height_mm', $expectedHeight));
})->with([
    ['compact_30', 25],
    ['standard_24', 30],
    ['medium_18', 41],
    ['large_12', 41],
]);

test('unauthorized users cannot access bulk label previews or downloads', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.products.labels.sheet', [
            'product_ids' => (string) $product->id,
        ], absolute: false))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => [$product->id],
        ])
        ->assertForbidden();
});

test('bulk label preview preserves every requested product in supported batch sizes', function (int $count) {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $products = Product::factory()->count($count)->create([
        'category_id' => null,
        'origin_id' => null,
        'supplier_id' => null,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.products.labels.sheet', [
            'product_ids' => $products->modelKeys(),
            'copies' => 1,
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('total_products', $count)
            ->has('labels', $count));
})->with([1, 10, 50, 100, 500]);

test('five hundred compact labels render into the expected number of PDF pages', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $products = Product::factory()->count(500)->create([
        'category_id' => null,
        'origin_id' => null,
        'supplier_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => $products->modelKeys(),
            'copies' => 1,
            'grid_preset' => 'compact_30',
            'paper_size' => 'a4',
        ])
        ->assertOk();

    $content = $response->getContent();
    preg_match_all('/\/Type\s*\/Page\b/', $content, $matches);

    expect($matches[0])->toHaveCount(17);
});

test('a full letter sheet stays on one PDF page for every layout', function (string $preset, int $productsPerPage) {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');
    $products = Product::factory()->count($productsPerPage)->create([
        'category_id' => null,
        'origin_id' => null,
        'supplier_id' => null,
    ]);

    $response = $this->actingAs($user)
        ->post(route('inventory.products.labels.pdf', absolute: false), [
            'product_ids' => $products->modelKeys(),
            'copies' => 1,
            'grid_preset' => $preset,
            'paper_size' => 'letter',
        ])
        ->assertOk();

    preg_match_all('/\/Type\s*\/Page\b/', $response->getContent(), $matches);

    expect($matches[0])->toHaveCount(1);
})->with([
    ['compact_30', 30],
    ['standard_24', 24],
    ['medium_18', 18],
    ['large_12', 12],
    ['single_1', 1],
]);
