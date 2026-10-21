<?php

use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Services\Optimization\AbcAnalyzer;
use App\Services\Optimization\EoqCalculator;
use App\Services\Optimization\InventoryOptimizationService;
use App\Services\Optimization\SafetyStockCalculator;
use Carbon\CarbonImmutable;

test('AbcAnalyzer correctly classifies products by cumulative consumption value', function () {
    $abcAnalyzer = app(AbcAnalyzer::class);
    $today = CarbonImmutable::now();

    // Product A: 100 units issued at 1,000 PHP = 100,000 PHP (High value)
    $productA = Product::factory()->consumable()->create(['sku' => 'SKU-ABC-A', 'unit_price' => 1000]);
    ProductStock::factory()->create(['product_id' => $productA->id, 'on_hand_qty' => 50]);
    StockMovement::factory()->create([
        'product_id' => $productA->id,
        'movement_type' => 'issue',
        'qty_delta' => -100,
        'performed_at' => $today->subDays(30),
    ]);

    // Product B: 50 units issued at 500 PHP = 25,000 PHP (Moderate value)
    $productB = Product::factory()->consumable()->create(['sku' => 'SKU-ABC-B', 'unit_price' => 500]);
    ProductStock::factory()->create(['product_id' => $productB->id, 'on_hand_qty' => 50]);
    StockMovement::factory()->create([
        'product_id' => $productB->id,
        'movement_type' => 'issue',
        'qty_delta' => -50,
        'performed_at' => $today->subDays(45),
    ]);

    // Product C: 20 units issued at 50 PHP = 1,000 PHP (Low value)
    $productC = Product::factory()->consumable()->create(['sku' => 'SKU-ABC-C', 'unit_price' => 50]);
    ProductStock::factory()->create(['product_id' => $productC->id, 'on_hand_qty' => 50]);
    StockMovement::factory()->create([
        'product_id' => $productC->id,
        'movement_type' => 'issue',
        'qty_delta' => -20,
        'performed_at' => $today->subDays(60),
    ]);

    $result = $abcAnalyzer->analyze($today);

    expect($result['summary']['total_annual_value'])->toBe(126000.0);
    expect($result['summary']['class_a_count'])->toBeGreaterThanOrEqual(1);

    $itemsBySku = collect($result['items'])->keyBy('sku');
    expect($itemsBySku->get('SKU-ABC-A'))->toMatchArray(['classification' => 'A']);
    expect($itemsBySku->get('SKU-ABC-B'))->toMatchArray(['classification' => 'B']);
    expect($itemsBySku->get('SKU-ABC-C'))->toMatchArray(['classification' => 'C']);
});

test('SafetyStockCalculator computes standard deviation, safety stock and reorder point', function () {
    $safetyStockCalculator = app(SafetyStockCalculator::class);
    $today = CarbonImmutable::now();
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-SS-001', 'unit_price' => 200]);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 100]);

    // Set up forecast profile with lead time 14 days
    $product->forecastProfile()->create([
        'method' => 'exponential_smoothing',
        'lookback_days' => 30,
        'forecast_horizon_days' => 30,
        'lead_time_days' => 14,
        'safety_stock_days' => 7,
    ]);

    // Create daily movements: 10 units every 2 days over past 20 days
    for ($i = 1; $i <= 10; $i++) {
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -10,
            'performed_at' => $today->subDays($i * 2),
        ]);
    }

    $result = $safetyStockCalculator->calculate($product, serviceLevelZ: 1.65, lookbackDays: 30, asOf: $today);

    expect($result['mean_daily_demand'])->toBeGreaterThan(0);
    expect($result['demand_std_dev'])->toBeGreaterThan(0);
    expect($result['lead_time_days'])->toBe(14);
    expect($result['safety_stock'])->toBeGreaterThan(0);
    expect($result['reorder_point'])->toBeGreaterThan($result['safety_stock']);
});

test('EoqCalculator computes economic order quantity, cycle days, and annual costs', function () {
    $eoqCalculator = app(EoqCalculator::class);
    $today = CarbonImmutable::now();
    // 1000 units annual demand, unit price 100 PHP, holding rate 0.20 (20 PHP/unit/yr), order cost 500 PHP
    // EOQ = sqrt((2 * 1000 * 500) / 20) = sqrt(50,000) ~= 224
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-EOQ-001', 'unit_price' => 100]);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 200]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'issue',
        'qty_delta' => -1000,
        'performed_at' => $today->subDays(90),
    ]);

    $result = $eoqCalculator->calculate($product, orderingCost: 500, holdingCostRate: 0.20, asOf: $today);

    expect($result['annual_demand'])->toBe(1000);
    expect($result['holding_cost_per_unit'])->toBe(20.0);
    expect($result['eoq'])->toBe(224);
    expect($result['orders_per_year'])->toBeGreaterThan(0.0);
    expect($result['total_annual_inventory_cost'])->toBeGreaterThan(0.0);
});

test('InventoryOptimizationService combines ABC, Safety Stock and EOQ into a product profile', function () {
    $optimizationService = app(InventoryOptimizationService::class);
    $today = CarbonImmutable::now();
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-OPT-001', 'unit_price' => 150]);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 80]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'issue',
        'qty_delta' => -50,
        'performed_at' => $today->subDays(15),
    ]);

    $profile = $optimizationService->getProductOptimization($product, $today);

    expect($profile['product_id'])->toBe($product->id);
    expect($profile['sku'])->toBe('SKU-OPT-001');
    expect($profile['abc_classification'])->toBeIn(['A', 'B', 'C']);
    expect($profile['safety_stock'])->toBeArray();
    expect($profile['eoq'])->toBeArray();
    expect($profile['recommended_batch_order_qty'])->toBeGreaterThan(0);
});
