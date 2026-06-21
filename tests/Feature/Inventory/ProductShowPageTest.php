<?php

use App\Enums\ProductType;
use App\Models\ForecastSnapshot;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Property Custodian');
});

test('product show page exposes enriched stock movement history', function () {
    $position = Position::factory()->create([
        'title' => 'Supply Office',
    ]);
    $position->load('department');

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'type' => ProductType::Consumable,
    ]);

    ProductStock::query()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 12,
    ]);

    $requisition = Requisition::factory()->create();

    $lot = StockLot::query()->create([
        'product_id' => $product->id,
        'reference_no' => 'PO-2026-001',
        'received_at' => now()->subDay(),
        'expires_at' => null,
        'qty_received' => 12,
        'qty_remaining' => 7,
    ]);

    StockMovement::query()->create([
        'movement_type' => 'issue',
        'product_id' => $product->id,
        'stock_lot_id' => $lot->id,
        'asset_id' => null,
        'requisition_id' => $requisition->id,
        'qty_delta' => -5,
        'qty_before' => 12,
        'qty_after' => 7,
        'performed_by' => $user->id,
        'accountable_position_id' => $position->id,
        'ip_address' => '127.0.0.1',
        'performed_at' => now(),
        'notes' => 'Issued to the laboratory',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.products.show', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/products/Show')
            ->has('stockMovements', 1)
            ->where('stockMovements.0.source', 'Requisition issuance')
            ->where('stockMovements.0.reference', "Requisition #{$requisition->id}")
            ->where('stockMovements.0.qty_before', 12)
            ->where('stockMovements.0.qty_after', 7)
            ->where(
                'stockMovements.0.accountable_position',
                trim("Supply Office, {$position->department?->name}"),
            ));
});

test('product show page exposes forecast insight for consumables', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'name' => 'Toner Cartridge',
    ]);

    ProductStock::query()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 9,
    ]);

    ForecastSnapshot::factory()->create([
        'product_id' => $product->id,
        'forecast_date' => now()->toDateString(),
        'forecast_method' => 'exponential_smoothing',
        'current_on_hand_qty' => 9,
        'reorder_point_qty' => 22,
        'predicted_daily_consumption' => 1.8,
        'predicted_days_until_stockout' => 5,
        'predicted_stockout_date' => now()->addDays(5)->toDateString(),
        'recommended_reorder_qty' => 13,
        'confidence_score' => 78.4,
        'raw_data' => [
            'historical_daily' => [
                ['date' => now()->subDays(2)->toDateString(), 'qty' => 2],
                ['date' => now()->subDay()->toDateString(), 'qty' => 1],
            ],
            'forecast_daily' => [
                ['date' => now()->addDay()->toDateString(), 'predicted_qty' => 1.7],
            ],
            'summary' => [
                'lookback_days' => 90,
                'forecast_horizon_days' => 30,
                'lead_time_days' => 14,
                'safety_stock_days' => 7,
                'has_sufficient_history' => true,
            ],
        ],
    ]);

    $this->actingAs($user)
        ->get(route('inventory.products.show', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/products/Show')
            ->where('forecast.method', 'exponential_smoothing')
            ->where('forecast.current_on_hand_qty', 9)
            ->where('forecast.predicted_days_until_stockout', 5)
            ->where('forecast.recommended_reorder_qty', 13));
});
