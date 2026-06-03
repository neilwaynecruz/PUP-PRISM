<?php

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\User;

uses()->group('api-integration');

beforeEach(function () {
    (new \Database\Seeders\RoleSeeder)->run();
});

/* --------------------------------------------------------------------------
   Authentication
   -------------------------------------------------------------------------- */

it('rejects unauthenticated api requests', function () {
    $this->getJson('/api/products')
        ->assertUnauthorized();
});

it('rejects unauthorized users from products api', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products')
        ->assertForbidden();
});

/* --------------------------------------------------------------------------
   Products
   -------------------------------------------------------------------------- */

it('lists products with pagination for authorized user', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $origin = Origin::factory()->create();
    $category = Category::factory()->create();

    foreach (range(1, 5) as $i) {
        Product::factory()->create([
            'type' => ProductType::Consumable,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'SKU-LIST-' . $i,
        ]);
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products')
        ->assertOk();

    $response->assertJsonStructure([
        'data' => [
            '*' => ['id', 'sku', 'name', 'type', 'is_active', 'reorder_threshold', 'category', 'origin', 'on_hand_qty'],
        ],
        'meta' => ['current_page', 'last_page', 'per_page', 'total'],
    ]);

    expect($response->json('meta.total'))->toBe(5);
});

it('filters products by type', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $origin = Origin::factory()->create();
    $category = Category::factory()->create();

    foreach (range(1, 3) as $i) {
        Product::factory()->create([
            'type' => ProductType::Consumable,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'CONS-' . $i,
        ]);
    }

    foreach (range(1, 2) as $i) {
        Product::factory()->create([
            'type' => ProductType::Asset,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'AST-' . $i,
        ]);
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products?type=asset')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

it('shows a single product', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $product = Product::factory()->create([
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $product->id);
});

/* --------------------------------------------------------------------------
   Assets
   -------------------------------------------------------------------------- */

it('lists assets with pagination', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Asset,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    Asset::factory()->count(4)->create(['product_id' => $product->id]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/assets')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(4);
});

it('filters assets by status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Asset,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    Asset::factory()->count(2)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::CheckedOut,
    ]);
    Asset::factory()->count(3)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/assets?status=' . AssetStatus::CheckedOut->value)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

/* --------------------------------------------------------------------------
   Stock Movements
   -------------------------------------------------------------------------- */

it('lists stock movements with date filtering', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    StockMovement::factory()->count(3)->create([
        'product_id' => $product->id,
        'performed_at' => now()->subDays(2),
    ]);
    StockMovement::factory()->count(2)->create([
        'product_id' => $product->id,
        'performed_at' => now()->subDays(10),
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/stock-movements?from=' . now()->subDays(5)->toDateString())
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

/* --------------------------------------------------------------------------
   Requisitions
   -------------------------------------------------------------------------- */

it('lists requisitions', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    Requisition::factory()->count(3)->create(['status' => RequisitionStatus::Submitted]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/requisitions')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

it('creates a requisition via api', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $token = $user->createToken('test')->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'notes' => 'API test requisition',
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 5],
            ],
        ])
        ->assertCreated();

    $response->assertJsonPath('data.status', RequisitionStatus::Submitted->value);

    $this->assertDatabaseHas('requisitions', [
        'requester_id' => $user->id,
        'status' => RequisitionStatus::Submitted->value,
        'notes' => 'API test requisition',
    ]);

    $this->assertDatabaseHas('requisition_lines', [
        'product_id' => $product->id,
        'qty_requested' => 5,
    ]);
});

it('validates requisition api input', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $token = $user->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'lines' => [
                ['product_id' => 999999, 'qty_requested' => 0],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.product_id', 'lines.0.qty_requested']);
});
