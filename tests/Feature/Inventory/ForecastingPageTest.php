<?php

use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
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

function forecastingPageUser(string $role): User
{
    $factory = User::factory();

    if ($role === 'Supply Head') {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('property custodians cannot access forecasting pages', function () {
    $custodian = forecastingPageUser('Property Custodian');

    $product = Product::factory()->consumable()->create();

    $this->actingAs($custodian)
        ->get(route('inventory.forecasting.index', absolute: false))
        ->assertForbidden();

    $this->actingAs($custodian)
        ->get(route('inventory.forecasting.show', $product, absolute: false))
        ->assertForbidden();
});

test('supply head can view forecasting index with expected props', function () {
    $supplyHead = forecastingPageUser('Supply Head');

    $expectedSku = 'SKU-FORECAST-PAGE';

    $product = Product::factory()->consumable()->create([
        'sku' => $expectedSku,
        'name' => 'Bond Paper Ream',
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'forecast_method' => 'seasonal',
        'current_on_hand_qty' => 6,
        'predicted_daily_consumption' => 1.5,
        'predicted_days_until_stockout' => 4,
        'recommended_reorder_qty' => 10,
        'confidence_score' => 82.5,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.forecasting.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/forecasting/Index')
            ->has('filters')
            ->has('forecastSummary')
            ->has('methodOptions', 3)
            ->has('products.data', 1)
            ->where('products.data.0.sku', $expectedSku)
            ->where('products.data.0.snapshot.recommended_reorder_qty', 10));
});

test('supply head can view forecasting detail for a consumable product', function () {
    $supplyHead = forecastingPageUser('Supply Head');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-FORECAST-DETAIL',
        'name' => 'Ink Cartridge',
    ]);

    $expectedMethod = 'moving_average';

    ForecastProfile::factory()->create([
        'product_id' => $product->id,
        'method' => $expectedMethod,
        'lookback_days' => 60,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.forecasting.show', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/forecasting/Show')
            ->where('product.id', $product->id)
            ->where('profile.method', $expectedMethod)
            ->where('profile.lookback_days', 60)
            ->has('forecast')
            ->has('confidenceExplanation')
            ->has('methodOptions', 3));
});

test('forecast profile updates persist for authorized users', function () {
    $supplyHead = forecastingPageUser('Supply Head');

    $product = Product::factory()->consumable()->create();

    $this->actingAs($supplyHead)
        ->put(route('inventory.forecasting.profile.update', $product, absolute: false), [
            'method' => 'seasonal',
            'lookback_days' => 120,
            'forecast_horizon_days' => 21,
            'lead_time_days' => 10,
            'safety_stock_days' => 5,
        ])
        ->assertRedirect(route('inventory.forecasting.show', $product, absolute: false));

    $profile = ForecastProfile::query()->where('product_id', $product->id)->first();

    expect($profile)->not->toBeNull();
    expect($profile?->method)->toBe('seasonal');
    expect($profile?->lookback_days)->toBe(120);
    expect($profile?->forecast_horizon_days)->toBe(21);
    expect($profile?->lead_time_days)->toBe(10);
    expect($profile?->safety_stock_days)->toBe(5);
});

test('forecasting index filters by urgency', function () {
    $supplyHead = forecastingPageUser('Supply Head');

    $urgentItemName = 'Urgent Item';
    $urgencyFilter = 'urgent';

    $urgentProduct = Product::factory()->consumable()->create(['name' => $urgentItemName]);
    $stableProduct = Product::factory()->consumable()->create(['name' => 'Stable Item']);

    ForecastSnapshot::factory()->create([
        'product_id' => $urgentProduct->id,
        'forecast_date' => now()->toDateString(),
        'predicted_days_until_stockout' => 5,
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $stableProduct->id,
        'forecast_date' => now()->toDateString(),
        'predicted_days_until_stockout' => 30,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.forecasting.index', ['urgency' => $urgencyFilter], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 1)
            ->where('products.data.0.name', $urgentItemName)
            ->where('filters.urgency', $urgencyFilter));
});
