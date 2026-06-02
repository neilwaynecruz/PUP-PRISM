<?php

use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

test('category and origin option caches are invalidated when reference data changes', function () {
    Cache::put(Category::OPTIONS_CACHE_KEY, collect([['id' => 1, 'name' => 'Stale Category']]), now()->addHour());
    Cache::put(Origin::OPTIONS_CACHE_KEY, collect([['id' => 1, 'name' => 'Stale Origin']]), now()->addHour());

    Category::factory()->create(['name' => 'Updated Category']);
    Origin::factory()->create(['name' => 'Updated Origin']);

    expect(Cache::has(Category::OPTIONS_CACHE_KEY))->toBeFalse();
    expect(Cache::has(Origin::OPTIONS_CACHE_KEY))->toBeFalse();
});

test('product pages recover from stale non-array option cache payloads', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    Category::factory()->create(['name' => 'Operational Category']);
    Origin::factory()->create(['name' => 'Operational Origin']);
    Product::factory()->create();

    Cache::put(Category::OPTIONS_CACHE_KEY, 'bad-category-cache', now()->addHour());
    Cache::put(Origin::OPTIONS_CACHE_KEY, 'bad-origin-cache', now()->addHour());

    $this->actingAs($user)
        ->get(route('inventory.products.index', absolute: false))
        ->assertOk();

    expect(Cache::get(Category::OPTIONS_CACHE_KEY))->toBeArray();
    expect(Cache::get(Origin::OPTIONS_CACHE_KEY))->toBeArray();
});
