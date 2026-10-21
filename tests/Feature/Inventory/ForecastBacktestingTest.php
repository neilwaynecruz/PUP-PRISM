<?php

use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Forecasting\DemandForecaster;
use App\Services\Forecasting\ForecastBacktester;
use App\Services\Procurement\PurchaseOrderGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
});

test('forecast backtester calculates accurate error metrics across methods without data leakage', function () {
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-BACKTEST-01']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 50]);

    $now = CarbonImmutable::now();

    // Seed 40 consecutive days of known issuance (2 units per day)
    foreach (range(0, 40) as $offset) {
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -2,
            'qty_before' => 200 - ($offset * 2),
            'qty_after' => 198 - ($offset * 2),
            'performed_at' => $now->subDays($offset)->setTime(10, 0),
        ]);
    }

    $backtester = app(ForecastBacktester::class);
    $result = $backtester->evaluate($product, evaluationDays: 7, totalLookbackDays: 30, asOf: $now);

    expect($result['has_sufficient_data'])->toBeTrue();
    expect($result['evaluation_days'])->toBe(7);
    expect($result['methods'])->toHaveKeys(['moving_average', 'exponential_smoothing', 'seasonal']);

    foreach ($result['methods'] as $metrics) {
        expect($metrics['mae'])->toBeGreaterThanOrEqual(0);
        expect($metrics['rmse'])->toBeGreaterThanOrEqual(0);
        expect($metrics['wape_pct'])->toBeGreaterThanOrEqual(0);
    }

    // Since demand was exactly constant at 2/day, MAE on moving average should be 0.00
    expect($result['methods']['moving_average']['mae'])->toBe(0.0);
    expect($result['methods']['moving_average']['bias'])->toBe(0.0);
    expect($result['recommended_method'])->not->toBeNull();
});

test('forecast backtester safely handles zero demand without division by zero', function () {
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-BACKTEST-ZERO']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);

    $backtester = app(ForecastBacktester::class);
    $result = $backtester->evaluate($product);

    expect($result['has_sufficient_data'])->toBeFalse();
    expect($result['recommended_method'])->toBeNull();
    expect($result['methods'])->toBeEmpty();
});

test('forecast backtester reports WAPE as unavailable when evaluation demand is zero', function () {
    $asOf = CarbonImmutable::parse('2026-10-20')->endOfDay();
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-BACKTEST-ZERO-EVAL']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);

    foreach (range(10, 20) as $offset) {
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -2,
            'performed_at' => $asOf->subDays($offset)->setTime(10, 0),
        ]);
    }

    $result = app(ForecastBacktester::class)->evaluate(
        $product,
        evaluationDays: 7,
        totalLookbackDays: 30,
        asOf: $asOf,
    );

    expect($result['has_sufficient_data'])->toBeTrue();

    foreach ($result['methods'] as $metrics) {
        expect($metrics['wape_pct'])->toBeNull();
    }
});

test('demand forecaster considers open purchase orders and pending requisitions in net inventory position', function () {
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-NET-INV-01',
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 20,
    ]);

    // On hand = 10
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 10,
    ]);

    $forecaster = app(DemandForecaster::class);

    // Initial forecast with no POs and no requisitions
    $initialResult = $forecaster->forecast($product);
    expect($initialResult->currentOnHandQty)->toBe(10);
    expect($initialResult->pendingOnOrderQty)->toBe(0);
    expect($initialResult->pendingCommittedQty)->toBe(0);
    expect($initialResult->recommendedReorderQty)->toBe(10); // target 20 - on_hand 10

    // Now create an open purchase order of 25 units sent to supplier
    $po = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
    ]);
    $po->lines()->create([
        'product_id' => $product->id,
        'qty_ordered' => 25,
        'qty_received' => 0,
        'unit_price' => 50,
        'subtotal' => 1250,
    ]);

    $poResult = $forecaster->forecast($product);
    expect($poResult->pendingOnOrderQty)->toBe(25);
    // Net inventory position = 10 on hand + 25 on order = 35 >= threshold 20, so recommended reorder should be 0!
    expect($poResult->recommendedReorderQty)->toBe(0);

    // Now create a pending approved requisition of 15 units committed to a department
    $requester = User::factory()->create();
    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'status' => RequisitionStatus::Approved,
    ]);
    $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 15,
        'qty_issued' => 0,
    ]);

    $committedResult = $forecaster->forecast($product);
    expect($committedResult->pendingCommittedQty)->toBe(15);
    // Net inventory = 10 on hand - 15 committed + 25 on order = 20. Target = 20.
    // Recommended reorder = 0!
    expect($committedResult->recommendedReorderQty)->toBe(0);

    // If requisition requested 30 units: Net inventory = 10 - 30 + 25 = 5. Target = 20.
    $requisition->lines()->first()?->update(['qty_requested' => 30]);
    $shortageResult = $forecaster->forecast($product);
    expect($shortageResult->pendingCommittedQty)->toBe(30);
    // Target 20 - Net 5 = 15
    expect($shortageResult->recommendedReorderQty)->toBe(15);
});

test('demand forecaster uses uncommitted stock when estimating the stockout date', function () {
    $asOf = CarbonImmutable::parse('2026-10-20')->endOfDay();
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-COMMITTED-STOCKOUT',
        'reorder_threshold' => 10,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 20,
    ]);

    $product->forecastProfile()->create([
        'method' => 'moving_average',
        'lookback_days' => 14,
        'forecast_horizon_days' => 7,
        'lead_time_days' => 7,
        'safety_stock_days' => 0,
        'is_active' => true,
    ]);

    foreach (range(0, 13) as $offset) {
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -2,
            'performed_at' => $asOf->subDays($offset)->setTime(10, 0),
        ]);
    }

    $requester = User::factory()->create();
    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'status' => RequisitionStatus::Approved,
    ]);
    $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 12,
        'qty_issued' => 0,
    ]);

    $product->refresh()->load(['stock', 'forecastProfile']);
    $result = app(DemandForecaster::class)->forecast($product, asOf: $asOf);

    expect($result->predictedDailyConsumption)->toBe(2.0)
        ->and($result->effectiveAvailableQty)->toBe(8)
        ->and($result->predictedDaysUntilStockout)->toBe(4)
        ->and($result->predictedStockoutDate?->toDateString())->toBe('2026-10-24');
});

test('purchase order generator prevents duplicate draft purchase orders on repeated runs', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 15,
        'unit_price' => 25,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 5,
    ]);

    $generator = app(PurchaseOrderGenerator::class);

    // First run creates 1 draft purchase order
    $firstRun = $generator->generateFromAlerts($user);
    expect($firstRun)->toHaveCount(1);
    expect(PurchaseOrder::query()->count())->toBe(1);

    // Second run should detect existing draft PO line and prevent duplicate order creation
    $secondRun = $generator->generateFromAlerts($user);
    expect($secondRun)->toBeEmpty();
    expect(PurchaseOrder::query()->count())->toBe(1);
});

test('purchase order generator rechecks for an active order after selecting candidates', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    $supplier = Supplier::factory()->create(['is_active' => true]);
    $product = Product::factory()->consumable()->create([
        'supplier_id' => $supplier->id,
        'reorder_threshold' => 15,
        'unit_price' => 25,
    ]);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 5,
    ]);

    $competingOrderCreated = false;

    DB::listen(function (QueryExecuted $query) use (&$competingOrderCreated, $supplier, $product, $user): void {
        if (
            $competingOrderCreated
            || ! str_contains($query->sql, 'from "products"')
            || ! str_contains($query->sql, 'reorder_threshold')
        ) {
            return;
        }

        $competingOrderCreated = true;
        $purchaseOrder = PurchaseOrder::factory()->create([
            'supplier_id' => $supplier->id,
            'requested_by' => $user->id,
            'status' => PurchaseOrderStatus::Draft,
        ]);
        $purchaseOrder->lines()->create([
            'product_id' => $product->id,
            'qty_ordered' => 25,
            'qty_received' => 0,
            'unit_price' => 25,
            'subtotal' => 625,
        ]);
    });

    $generated = app(PurchaseOrderGenerator::class)->generateFromAlerts($user);

    expect($competingOrderCreated)->toBeTrue()
        ->and($generated)->toBeEmpty()
        ->and(PurchaseOrder::query()->count())->toBe(1);
});
