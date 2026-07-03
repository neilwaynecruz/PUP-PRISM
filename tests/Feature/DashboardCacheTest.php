<?php

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\DashboardStatsCache;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Cache::flush();
    config([
        'dashboard.cache.enabled' => true,
        'dashboard.cache.ttl' => 90,
    ]);

    Role::findOrCreate('Admin');
});

test('second dashboard request uses cached admin stats', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $range = ['from' => null, 'to' => null];
    $cache = app(DashboardStatsCache::class);
    $cacheKey = $cache->key('admin', $range);

    expect(Cache::has($cacheKey))->toBeFalse();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    expect(Cache::has($cacheKey))->toBeTrue();
});

test('stock movement creation invalidates dashboard cache', function () {
    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $range = ['from' => null, 'to' => null];
    $cache = app(DashboardStatsCache::class);
    $versionBefore = (int) Cache::get('dashboard_stats_version', 1);
    $originalKey = $cache->key('admin', $range);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    expect(Cache::has($originalKey))->toBeTrue();

    $product = Product::factory()->consumable()->create();

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'receive',
        'performed_by' => $admin->id,
    ]);

    expect((int) Cache::get('dashboard_stats_version'))->toBe($versionBefore + 1);
    expect($cache->key('admin', $range))->not->toBe($originalKey);
});

test('dashboard cache can be disabled via configuration', function () {
    config(['dashboard.cache.enabled' => false]);

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $cache = app(DashboardStatsCache::class);
    $cacheKey = $cache->key('admin', ['from' => null, 'to' => null]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk();

    expect(Cache::has($cacheKey))->toBeFalse();
});

test('supply head dashboard stats are cached separately from admin stats', function () {
    Role::findOrCreate('Supply Head');

    $admin = User::factory()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $supplyHead = User::factory()->create(['email_verified_at' => now()]);
    $supplyHead->assignRole('Supply Head');

    $range = ['from' => null, 'to' => null];
    $cache = app(DashboardStatsCache::class);

    $this->actingAs($admin)->get(route('dashboard'))->assertOk();
    $this->actingAs($supplyHead)->get(route('dashboard'))->assertOk();

    expect(Cache::has($cache->key('admin', $range)))->toBeTrue();
    expect(Cache::has($cache->key('procurement', $range)))->toBeTrue();
});
