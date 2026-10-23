<?php

use App\Models\Category;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\DashboardStatsService;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Supply Head');
});

function forecastingSupplyHead(array $attributes = []): User
{
    $user = User::factory()->withTwoFactor()->create($attributes);
    $user->assignRole('Supply Head');

    return $user;
}

test('demand forecasting command stores snapshots and forecast alerts', function () {
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-FORECAST-001',
        'name' => 'Multi-Purpose Paper',
        'reorder_threshold' => 12,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 4,
    ]);

    foreach (range(1, 14) as $dayOffset) {
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -2,
            'qty_before' => 40 - ($dayOffset * 2),
            'qty_after' => 38 - ($dayOffset * 2),
            'performed_at' => CarbonImmutable::now()->subDays(15 - $dayOffset)->setTime(9, 0),
        ]);
    }

    $this->artisan('app:generate-demand-forecasts')
        ->assertExitCode(0);

    $snapshot = ForecastSnapshot::query()
        ->where('product_id', $product->id)
        ->latest('forecast_date')
        ->first();

    expect($snapshot)->not->toBeNull();
    expect($snapshot?->predicted_daily_consumption)->toBeGreaterThan(0);
    expect($snapshot?->recommended_reorder_qty)->toBeGreaterThan(0);
    expect($snapshot?->predicted_days_until_stockout)->toBeLessThanOrEqual(14);
    expect(
        InventoryAlert::query()
            ->where('type', 'forecast_stockout')
            ->where('product_id', $product->id)
            ->whereNull('resolved_at')
            ->exists()
    )->toBeTrue();
});

test('supply head dashboard exposes forecasting summary cards', function () {
    $user = forecastingSupplyHead();

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-FORECAST-002',
        'name' => 'Printer Ink',
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'forecast_method' => 'seasonal',
        'current_on_hand_qty' => 8,
        'reorder_point_qty' => 20,
        'predicted_daily_consumption' => 2.75,
        'predicted_days_until_stockout' => 3,
        'predicted_stockout_date' => now()->addDays(3)->toDateString(),
        'recommended_reorder_qty' => 12,
        'confidence_score' => 84.2,
    ]);

    $summary = app(DashboardStatsService::class)->getForecastSummary();

    expect($summary['items'])->toHaveCount(1);
    expect($summary['items'][0]['product_name'])->toBe('Printer Ink');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('canViewForecasting', true)
            ->has('forecastSummary.items', 1)
            ->where('forecastSummary.items.0.product_name', 'Printer Ink')
            ->where('forecastSummary.items.0.recommended_reorder_qty', 12)
            ->where('forecastSummary.items.0.predicted_days_until_stockout', 3));
});

test('dashboard forecasting only hydrates snapshots from the latest forecast date', function () {
    $product = Product::factory()->consumable()->create();

    foreach (range(1, 20) as $daysAgo) {
        ForecastSnapshot::factory()->create([
            'product_id' => $product->id,
            'forecast_date' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
    ]);

    $retrievedSnapshots = 0;
    ForecastSnapshot::retrieved(function () use (&$retrievedSnapshots): void {
        $retrievedSnapshots++;
    });

    $summary = app(DashboardStatsService::class)->getForecastSummary();

    expect($summary['items'])->toHaveCount(1)
        ->and($retrievedSnapshots)->toBe(1);
});

test('dashboard forecasting separates urgent and at-risk stockouts at their boundaries', function () {
    $category = Category::factory()->create();
    $origin = Origin::factory()->create();
    $supplier = Supplier::factory()->create();

    foreach ([7, 8, 14, 15] as $daysUntilStockout) {
        $product = Product::factory()->consumable()->create([
            'category_id' => $category->id,
            'origin_id' => $origin->id,
            'supplier_id' => $supplier->id,
        ]);

        ForecastSnapshot::factory()->create([
            'product_id' => $product->id,
            'forecast_date' => now()->toDateString(),
            'predicted_days_until_stockout' => $daysUntilStockout,
        ]);
    }

    $summary = app(DashboardStatsService::class)->getForecastSummary();

    expect($summary['urgent_count'])->toBe(1)
        ->and($summary['at_risk_count'])->toBe(2);
});

test('dashboard forecasting hydrates only the eight highest-priority current snapshots', function () {
    $category = Category::factory()->create();
    $origin = Origin::factory()->create();
    $supplier = Supplier::factory()->create();

    foreach (range(1, 20) as $daysUntilStockout) {
        $product = Product::factory()->consumable()->create([
            'category_id' => $category->id,
            'origin_id' => $origin->id,
            'supplier_id' => $supplier->id,
        ]);

        ForecastSnapshot::factory()->create([
            'product_id' => $product->id,
            'forecast_date' => now()->toDateString(),
            'predicted_days_until_stockout' => $daysUntilStockout,
        ]);
    }

    $retrievedSnapshots = 0;
    ForecastSnapshot::retrieved(function () use (&$retrievedSnapshots): void {
        $retrievedSnapshots++;
    });

    $summary = app(DashboardStatsService::class)->getForecastSummary();

    expect($summary['items'])->toHaveCount(8)
        ->and($retrievedSnapshots)->toBe(8)
        ->and($summary['items'][0]['predicted_days_until_stockout'])->toBe(1)
        ->and($summary['items'][7]['predicted_days_until_stockout'])->toBe(8);
});
