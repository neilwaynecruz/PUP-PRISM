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
use Database\Seeders\RoleSeeder;

uses()->group('api-integration');

beforeEach(function () {
    (new RoleSeeder)->run();
});

function apiRoleUser(string $role, array $attributes = []): User
{
    $factory = User::factory();

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create($attributes);
    $user->assignRole($role);

    return $user;
}

/* --------------------------------------------------------------------------
   Authentication
   -------------------------------------------------------------------------- */

it('rejects unauthenticated api requests', function () {
    $this->getJson('/api/products')
        ->assertUnauthorized();
});

it('rejects unauthorized users from products api', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products')
        ->assertForbidden();
});

it('rejects unverified users from api routes', function () {
    $user = User::factory()->unverified()->withTwoFactor()->create();
    $user->assignRole('Admin');
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products')
        ->assertForbidden();
});

/* --------------------------------------------------------------------------
   Products
   -------------------------------------------------------------------------- */

it('lists products with pagination for authorized user', function () {
    $admin = apiRoleUser('Admin');
    $token = $admin->createToken('test', ['read'])->plainTextToken;

    $origin = Origin::factory()->create();
    $category = Category::factory()->create();

    foreach (range(1, 5) as $i) {
        Product::factory()->create([
            'type' => ProductType::Consumable,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'SKU-LIST-'.$i,
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
    $admin = apiRoleUser('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    $origin = Origin::factory()->create();
    $category = Category::factory()->create();

    foreach (range(1, 3) as $i) {
        Product::factory()->create([
            'type' => ProductType::Consumable,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'CONS-'.$i,
        ]);
    }

    foreach (range(1, 2) as $i) {
        Product::factory()->create([
            'type' => ProductType::Asset,
            'origin_id' => $origin->id,
            'category_id' => $category->id,
            'sku' => 'AST-'.$i,
        ]);
    }

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products?type=asset')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

it('shows a single product', function () {
    $admin = apiRoleUser('Admin');
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
    $admin = apiRoleUser('Admin');
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
    $admin = apiRoleUser('Admin');
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
        ->getJson('/api/assets?status='.AssetStatus::CheckedOut->value)
        ->assertOk();

    expect($response->json('meta.total'))->toBe(2);
});

/* --------------------------------------------------------------------------
   Stock Movements
   -------------------------------------------------------------------------- */

it('lists stock movements with date filtering', function () {
    $admin = apiRoleUser('Admin');
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
        ->getJson('/api/stock-movements?from='.now()->subDays(5)->toDateString())
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

/* --------------------------------------------------------------------------
   Requisitions
   -------------------------------------------------------------------------- */

it('lists requisitions', function () {
    $admin = apiRoleUser('Admin');
    $token = $admin->createToken('test')->plainTextToken;

    Requisition::factory()->count(3)->create(['status' => RequisitionStatus::Submitted]);

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/requisitions')
        ->assertOk();

    expect($response->json('meta.total'))->toBe(3);
});

it('creates a requisition via api', function () {
    $user = apiRoleUser('Admin');
    $token = $user->createToken('test', ['write'])->plainTextToken;

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
    $user = apiRoleUser('Admin');
    $token = $user->createToken('test', ['write'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'lines' => [
                ['product_id' => 999999, 'qty_requested' => 0],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lines.0.product_id', 'lines.0.qty_requested']);
});

it('rejects requisition creation for users without inventory roles', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test', ['write'])->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'notes' => 'Blocked requisition',
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 1],
            ],
        ])
        ->assertForbidden();
});

it('rejects requisition creation for read-only tokens', function () {
    $user = apiRoleUser('Admin');
    $token = $user->createToken('test', ['read'])->plainTextToken;

    $product = Product::factory()->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/requisitions', [
            'notes' => 'Read-only requisition',
            'lines' => [
                ['product_id' => $product->id, 'qty_requested' => 1],
            ],
        ])
        ->assertForbidden();
});

it('rejects api pagination above the configured maximum', function () {
    $admin = apiRoleUser('Admin');
    $token = $admin->createToken('test', ['read'])->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products?per_page=500')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['per_page']);
});

it('accepts api pagination within the configured maximum', function () {
    $admin = apiRoleUser('Admin');
    $token = $admin->createToken('test', ['read'])->plainTextToken;

    Product::factory()->count(3)->create([
        'type' => ProductType::Consumable,
        'origin_id' => Origin::factory()->create()->id,
        'category_id' => Category::factory()->create()->id,
    ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/products?per_page=2')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data');
});
