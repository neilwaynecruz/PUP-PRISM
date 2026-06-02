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
