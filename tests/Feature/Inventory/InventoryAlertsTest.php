<?php

use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use Carbon\CarbonImmutable;

test('alerts command generates low stock and expiring alerts', function () {
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-ALERT-001',
        'reorder_threshold' => 10,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 5,
    ]);

    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_remaining' => 1,
        'expires_at' => CarbonImmutable::now()->addDays(3)->toDateString(),
    ]);

    $this->artisan('app:inventory-generate-alerts')->assertExitCode(0);

    expect(InventoryAlert::query()->where('type', 'low_stock')->count())->toBe(1);
    expect(InventoryAlert::query()->where('type', 'expiring')->count())->toBe(1);
});

test('alerts command preserves forecast-generated alerts', function () {
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-ALERT-FORECAST',
        'reorder_threshold' => 4,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 10,
    ]);

    InventoryAlert::query()->create([
        'type' => 'forecast_stockout',
        'product_id' => $product->id,
        'stock_lot_id' => null,
        'message' => 'Forecast stockout warning.',
        'detected_at' => now(),
        'resolved_at' => null,
    ]);

    $this->artisan('app:inventory-generate-alerts')->assertExitCode(0);

    expect(
        InventoryAlert::query()
            ->where('type', 'forecast_stockout')
            ->where('product_id', $product->id)
            ->whereNull('resolved_at')
            ->count()
    )->toBe(1);
});
