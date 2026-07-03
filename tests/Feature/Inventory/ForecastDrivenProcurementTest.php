<?php

use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\ProcurementRecommendationNotification;
use App\Services\Procurement\PurchaseOrderGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Supply Head');

    $this->withoutVite();
});

test('generate from forecast alerts includes above-threshold forecast-urgent product', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 10,
        'unit_price' => 50,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 25,
    ]);

    InventoryAlert::query()->create([
        'type' => 'forecast_stockout',
        'product_id' => $product->id,
        'stock_lot_id' => null,
        'message' => 'Forecasted stockout in 5 day(s).',
        'detected_at' => now(),
        'resolved_at' => null,
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'recommended_reorder_qty' => 30,
    ]);

    $generated = app(PurchaseOrderGenerator::class)->generateFromForecastAlerts($user);

    expect($generated)->toHaveCount(1);

    $purchaseOrder = $generated->first();
    expect($purchaseOrder)->not->toBeNull()
        ->and($purchaseOrder->status->value)->toBe('draft')
        ->and($purchaseOrder->notes)->toBe('Auto-generated from forecast stockout alerts.');

    $line = PurchaseOrderLine::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->where('product_id', $product->id)
        ->first();

    expect($line)->not->toBeNull()
        ->and($line->qty_ordered)->toBe(30);
});

test('generate from forecast alerts skips product without supplier', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    $product = Product::factory()->consumable()->create([
        'supplier_id' => null,
        'reorder_threshold' => 10,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 25,
    ]);

    InventoryAlert::query()->create([
        'type' => 'forecast_stockout',
        'product_id' => $product->id,
        'stock_lot_id' => null,
        'message' => 'Forecasted stockout in 5 day(s).',
        'detected_at' => now(),
        'resolved_at' => null,
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'recommended_reorder_qty' => 20,
    ]);

    $generated = app(PurchaseOrderGenerator::class)->generateFromForecastAlerts($user);

    expect($generated)->toBeEmpty();
    expect(PurchaseOrder::query()->count())->toBe(0);
});

test('procurement recommendation notification is sent when a new forecast stockout alert is created', function () {
    Notification::fake();

    $supplyHead = User::factory()->create();
    $supplyHead->assignRole('Supply Head');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-FORECAST-NOTIFY',
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

    $this->artisan('app:generate-demand-forecasts', ['--product' => $product->id])
        ->assertExitCode(0);

    Notification::assertSentTo(
        $supplyHead,
        ProcurementRecommendationNotification::class,
        function (ProcurementRecommendationNotification $notification) use ($product): bool {
            return $notification->product->is($product)
                && $notification->recommendedReorderQty > 0
                && $notification->predictedDaysUntilStockout > 0;
        },
    );
});

test('supply head can generate draft purchase orders from forecast alerts via controller', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'purchase-order-forecast-generate-token';

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 10,
        'unit_price' => 40,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 18,
    ]);

    InventoryAlert::query()->create([
        'type' => 'forecast_stockout',
        'product_id' => $product->id,
        'stock_lot_id' => null,
        'message' => 'Forecasted stockout in 4 day(s).',
        'detected_at' => now(),
        'resolved_at' => null,
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'recommended_reorder_qty' => 15,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.purchase-orders.generate-from-forecasts', absolute: false), [
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('inventory.purchase-orders.index', absolute: false));

    expect(PurchaseOrder::query()->count())->toBe(1);
});
