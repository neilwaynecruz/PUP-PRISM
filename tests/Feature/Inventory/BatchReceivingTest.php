<?php

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\User;

uses()->group('batch-receiving');

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
});

it('receives multiple consumables in a batch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $productA = Product::factory()->create([
        'sku' => 'SKU-A',
        'type' => ProductType::Consumable,
    ]);
    ProductStock::factory()->create(['product_id' => $productA->id, 'on_hand_qty' => 0]);

    $productB = Product::factory()->create([
        'sku' => 'SKU-B',
        'type' => ProductType::Consumable,
    ]);
    ProductStock::factory()->create(['product_id' => $productB->id, 'on_hand_qty' => 0]);

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                [
                    'sku' => 'SKU-A',
                    'qty' => 10,
                    'reference_no' => 'REF-001',
                ],
                [
                    'sku' => 'SKU-B',
                    'qty' => 5,
                    'reference_no' => 'REF-002',
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('stock_lots', [
        'product_id' => $productA->id,
        'reference_no' => 'REF-001',
        'qty_received' => 10,
    ]);

    $this->assertDatabaseHas('stock_lots', [
        'product_id' => $productB->id,
        'reference_no' => 'REF-002',
        'qty_received' => 5,
    ]);

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $productA->id,
        'on_hand_qty' => 10,
    ]);

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $productB->id,
        'on_hand_qty' => 5,
    ]);
});

it('receives assets in a batch', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->create([
        'sku' => 'AST-PROD',
        'type' => ProductType::Asset,
    ]);

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                [
                    'sku' => 'AST-PROD',
                    'tag_codes' => ['AST-001', 'AST-002'],
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('assets', [
        'product_id' => $product->id,
        'tag_code' => 'AST-001',
    ]);

    $this->assertDatabaseHas('assets', [
        'product_id' => $product->id,
        'tag_code' => 'AST-002',
    ]);
});

it('validates missing sku in batch line', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                ['qty' => 10],
            ],
        ])
        ->assertSessionHasErrors('lines.0.sku');
});

it('validates unknown sku in batch line', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                ['sku' => 'UNKNOWN', 'qty' => 10],
            ],
        ])
        ->assertSessionHasErrors('lines.0.sku');
});

it('validates consumable without qty', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Product::factory()->create([
        'sku' => 'CONS-001',
        'type' => ProductType::Consumable,
    ]);

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                ['sku' => 'CONS-001'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.qty');
});

it('validates asset without tag codes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    Product::factory()->create([
        'sku' => 'AST-001',
        'type' => ProductType::Asset,
    ]);

    $this->actingAs($admin)
        ->post(route('inventory.receiving.batch'), [
            'lines' => [
                ['sku' => 'AST-001'],
            ],
        ])
        ->assertSessionHasErrors('lines.0.tag_codes');
});
