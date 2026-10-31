<?php

use App\Enums\ProductType;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

function productCrudUser(string $role): User
{
    $factory = User::factory();

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

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
    $viewer = productCrudUser('Property Custodian');

    $editor = productCrudUser('Supply Head');

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
    $user = productCrudUser('Supply Head');
    $csrfToken = 'product-test-token';

    $category = Category::factory()->create();
    $origin = Origin::factory()->create();
    $supplier = Supplier::factory()->create();

    $payload = [
        '_token' => $csrfToken,
        'sku' => 'SKU-TEST-0001',
        'name' => 'Test Product',
        'type' => ProductType::Consumable->value,
        'category_id' => $category->id,
        'origin_id' => $origin->id,
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 10,
        'lead_time_days' => 6,
        'unit_price' => 199.5,
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.products.store', absolute: false), $payload)
        ->assertRedirect(route('inventory.products.index', absolute: false));

    $product = Product::query()->where('sku', 'SKU-TEST-0001')->first();

    expect($product)->not->toBeNull()
        ->and($product?->supplier_id)->toBe($supplier->id)
        ->and($product?->lead_time_days)->toBe(6)
        ->and((float) $product?->unit_price)->toBe(199.5);
});

test('product classifications persist controlled units and prevent equivalent variants', function () {
    $user = productCrudUser('Supply Head');
    $category = Category::factory()->create(['name' => 'Office Supplies']);
    $unit = UnitOfMeasurement::factory()->create([
        'code' => 'ream',
        'name' => 'Ream',
        'abbreviation' => 'ream',
    ]);
    $payload = [
        'sku' => 'OFF-PAPER-A4-80',
        'name' => 'Bond Paper',
        'variant' => 'A4 · 80 gsm · 500 sheets',
        'brand' => 'Institutional Standard',
        'size' => '210 × 297 mm',
        'type' => ProductType::Consumable->value,
        'category_id' => $category->id,
        'unit_of_measurement_id' => $unit->id,
        'reorder_threshold' => 20,
        'is_active' => true,
    ];

    $this->actingAs($user)
        ->post(route('inventory.products.store', absolute: false), $payload)
        ->assertRedirect(route('inventory.products.index', absolute: false));

    $product = Product::query()->where('sku', 'OFF-PAPER-A4-80')->firstOrFail();

    expect($product->unitOfMeasurement->is($unit))->toBeTrue()
        ->and($product->variant)->toBe('A4 · 80 gsm · 500 sheets')
        ->and($product->brand)->toBe('Institutional Standard');

    $this->actingAs($user)
        ->from(route('inventory.products.create', absolute: false))
        ->post(route('inventory.products.store', absolute: false), [
            ...$payload,
            'sku' => 'OFF-PAPER-A4-80-DUP',
            'name' => '  bond   paper ',
            'variant' => 'a4 · 80 GSM · 500 SHEETS',
        ])
        ->assertRedirect(route('inventory.products.create', absolute: false))
        ->assertSessionHasErrors('name');

    expect(Product::query()->where('sku', 'OFF-PAPER-A4-80-DUP')->exists())->toBeFalse();
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
    $user = productCrudUser('Supply Head');
    $csrfToken = 'product-test-token';

    $category = Category::factory()->create();
    $origin = Origin::factory()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.products.update', $product, absolute: false), [
            '_token' => $csrfToken,
            'sku' => 'SKU-UPDATED-0001',
            'name' => 'Updated Product',
            'type' => ProductType::Consumable->value,
            'category_id' => $category->id,
            'origin_id' => $origin->id,
            'supplier_id' => $supplier->id,
            'reorder_threshold' => 5,
            'lead_time_days' => 3,
            'unit_price' => 245.25,
            'is_active' => true,
        ])
        ->assertRedirect(route('inventory.products.edit', $product, absolute: false));

    $product->refresh();

    expect(Product::query()->where('sku', 'SKU-UPDATED-0001')->exists())->toBeTrue()
        ->and($product->supplier_id)->toBe($supplier->id)
        ->and($product->lead_time_days)->toBe(3)
        ->and((float) $product->unit_price)->toBe(245.25);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.products.destroy', $product, absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();
});

test('supply head can update product classification attributes without resending type', function () {
    $user = productCrudUser('Supply Head');
    $category = Category::factory()->create();
    $unit = UnitOfMeasurement::factory()->create();
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-ORIG-01',
        'name' => 'Original Name',
    ]);

    $this->actingAs($user)
        ->put(route('inventory.products.update', $product, absolute: false), [
            'sku' => 'SKU-ORIG-01',
            'name' => 'Updated Display Name',
            'category_id' => $category->id,
            'unit_of_measurement_id' => $unit->id,
            'variant' => '500 mL Bottle',
            'brand' => 'Safeguard',
            'model' => 'Pro-500',
            'color' => 'Clear',
            'size' => '500 mL',
            'reorder_threshold' => 15,
            'is_active' => true,
        ])
        ->assertRedirect(route('inventory.products.edit', $product, absolute: false));

    $product->refresh();

    expect($product->name)->toBe('Updated Display Name')
        ->and($product->type)->toBe(ProductType::Consumable)
        ->and($product->category_id)->toBe($category->id)
        ->and($product->unit_of_measurement_id)->toBe($unit->id)
        ->and($product->variant)->toBe('500 mL Bottle')
        ->and($product->brand)->toBe('Safeguard')
        ->and($product->model)->toBe('Pro-500')
        ->and($product->color)->toBe('Clear')
        ->and($product->size)->toBe('500 mL')
        ->and($product->reorder_threshold)->toBe(15);
});

test('supply head can update product with empty string optional fields', function () {
    $user = productCrudUser('Supply Head');
    $csrfToken = 'product-test-token';

    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'sku' => 'SKU-OPT-01',
        'name' => 'Original Item',
        'category_id' => $category->id,
        'variant' => 'Initial Variant',
        'reorder_threshold' => 10,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.products.update', $product, absolute: false), [
            '_token' => $csrfToken,
            'sku' => 'SKU-OPT-01',
            'name' => 'Renamed Item',
            'category_id' => '',
            'origin_id' => '',
            'supplier_id' => '',
            'unit_of_measurement_id' => '',
            'variant' => '',
            'brand' => '',
            'model' => '',
            'color' => '',
            'size' => '',
            'reorder_threshold' => '',
            'lead_time_days' => '',
            'unit_price' => '',
            'is_active' => '1',
        ])
        ->assertRedirect(route('inventory.products.edit', $product, absolute: false));

    $product->refresh();

    expect($product->name)->toBe('Renamed Item')
        ->and($product->category_id)->toBeNull()
        ->and($product->origin_id)->toBeNull()
        ->and($product->supplier_id)->toBeNull()
        ->and($product->unit_of_measurement_id)->toBeNull()
        ->and($product->variant)->toBeNull()
        ->and($product->brand)->toBeNull()
        ->and($product->model)->toBeNull()
        ->and($product->color)->toBeNull()
        ->and($product->size)->toBeNull()
        ->and($product->reorder_threshold)->toBe(0)
        ->and($product->lead_time_days)->toBeNull()
        ->and($product->unit_price)->toBeNull();
});

test('admin can destroy products', function () {
    $user = productCrudUser('Admin');
    $csrfToken = 'product-test-token';

    $product = Product::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.products.destroy', $product, absolute: false), ['_token' => $csrfToken])
        ->assertRedirect(route('inventory.products.index', absolute: false));

    expect(Product::query()->whereKey($product->getKey())->exists())->toBeFalse();
});
