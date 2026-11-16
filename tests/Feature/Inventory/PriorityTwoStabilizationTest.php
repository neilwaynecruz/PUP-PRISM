<?php

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\StockMovementReasonCode;
use App\Models\Asset;
use App\Models\Category;
use App\Models\InventoryAlert;
use App\Models\Location;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\UnitOfMeasurement;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\Forecasting\ConsumptionDataCollector;
use App\Services\Forecasting\DemandForecaster;
use App\Services\Forecasting\ForecastBacktester;
use App\Services\Inventory\AssetIntegrityService;
use App\Services\Inventory\AssetLifecycleService;
use App\Services\Inventory\AssetMaintenanceService;
use App\Services\Inventory\AssetTransferService;
use App\Services\Reports\ProductInventoryReportService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
    Role::findOrCreate('User');
});

function createPriorityTwoUser(string $role = 'Property Custodian'): User
{
    $position = Position::factory()->create();
    $user = User::factory()
        ->assignedPosition($position)
        ->withTwoFactor()
        ->create();
    $user->assignRole($role);

    return $user;
}

test('custodian can return a checked-out asset to stock and custodian kpi counts checked-out assets', function () {
    $custodian = createPriorityTwoUser('Property Custodian');
    $supplyHead = createPriorityTwoUser('Supply Head');
    $stockLocation = Location::factory()->create(['is_active' => true]);

    $checkedOut = Asset::factory()->create([
        'status' => AssetStatus::CheckedOut,
        'condition_status' => AssetConditionStatus::Serviceable,
        'position_id' => $custodian->position_id,
        'current_location_id' => $stockLocation->id,
    ]);
    Asset::factory()->create([
        'status' => AssetStatus::Available,
        'condition_status' => AssetConditionStatus::Serviceable,
        'position_id' => $custodian->position_id,
    ]);

    $stats = app(DashboardStatsService::class)->getCustodianStats($custodian, []);
    expect($stats['custodianSummary']['assigned_assets'])->toBe(1)
        ->and($stats['kpiSummary']['assigned_assets'])->toBe(1);

    $csrfToken = 'priority-two-return-token';

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.assets.return', $checkedOut, absolute: false), [
            '_token' => $csrfToken,
            'location_id' => $stockLocation->id,
            'condition_status' => AssetConditionStatus::Serviceable->value,
        ])
        ->assertForbidden();

    $this->actingAs($custodian)
        ->from(route('inventory.assets.show', $checkedOut, absolute: false))
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.assets.return', $checkedOut, absolute: false), [
            '_token' => $csrfToken,
            'location_id' => $stockLocation->id,
            'condition_status' => AssetConditionStatus::Damaged->value,
            'notes' => 'Returned with minor screen crack.',
        ])
        ->assertRedirect(route('inventory.assets.show', $checkedOut, absolute: false));

    $checkedOut->refresh();

    expect($checkedOut->position_id)->toBeNull()
        ->and($checkedOut->current_location_id)->toBe($stockLocation->id)
        ->and($checkedOut->condition_status)->toBe(AssetConditionStatus::Damaged)
        ->and($checkedOut->status)->toBe(AssetStatus::Unserviceable);

    $movement = StockMovement::query()
        ->where('product_id', $checkedOut->product_id)
        ->where('movement_type', 'return')
        ->latest('id')
        ->first();

    expect($movement)->not->toBeNull()
        ->and($movement?->reason_code)->toBe(StockMovementReasonCode::ReturnToStock->value)
        ->and($movement?->asset_id)->toBe($checkedOut->id);

    Cache::flush();
    $updatedStats = app(DashboardStatsService::class)->getCustodianStats($custodian, []);
    expect($updatedStats['custodianSummary']['assigned_assets'])->toBe(0);
});

test('unserviceable assets can enter active repair, preserve status on cancel, and do not block condition changes while reported', function () {
    $requester = createPriorityTwoUser('Property Custodian');
    $approver = createPriorityTwoUser('Admin');

    $asset = Asset::factory()->create([
        'status' => AssetStatus::Unserviceable,
        'condition_status' => AssetConditionStatus::Damaged,
    ]);

    $maintenanceService = app(AssetMaintenanceService::class);
    $maintenance = $maintenanceService->create($requester, [
        'asset_id' => $asset->id,
        'maintenance_type' => MaintenanceType::Corrective->value,
        'reported_issue' => 'Power supply fault',
        'reported_date' => now()->toDateString(),
    ]);

    $lifecycleService = app(AssetLifecycleService::class);
    $reportedStageEvent = $lifecycleService->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::ConditionChange->value,
        'condition_status' => AssetConditionStatus::Unserviceable->value,
        'reason' => 'Confirmed diagnostic downgrade before technician begins repair.',
    ]);

    $approvedEvent = $lifecycleService->approve($approver, $reportedStageEvent);
    expect($approvedEvent->status)->toBe(ApprovalStatus::Approved)
        ->and($asset->fresh()?->condition_status)->toBe(AssetConditionStatus::Unserviceable);

    $inProgress = $maintenanceService->transition($maintenance->fresh(), MaintenanceStatus::InProgress, []);
    expect($inProgress->status)->toBe(MaintenanceStatus::InProgress)
        ->and($asset->fresh()?->status)->toBe(AssetStatus::Unserviceable);

    $cancelled = $maintenanceService->transition($inProgress, MaintenanceStatus::Cancelled, [
        'resolution_notes' => 'Waiting for replacement board.',
    ]);

    expect($cancelled->status)->toBe(MaintenanceStatus::Cancelled)
        ->and($asset->fresh()?->status)->toBe(AssetStatus::Unserviceable);
});

test('pending transfers block bookings and handovers cleanly and approved transfers update position and location', function () {
    $requester = createPriorityTwoUser('Property Custodian');
    $approver = createPriorityTwoUser('Admin');
    $sourceLocation = Location::factory()->create(['is_active' => true]);
    $destinationLocation = Location::factory()->create(['is_active' => true]);
    $destinationPosition = Position::factory()->create();

    $asset = Asset::factory()->create([
        'status' => AssetStatus::Available,
        'condition_status' => AssetConditionStatus::Serviceable,
        'current_location_id' => $sourceLocation->id,
        'position_id' => null,
    ]);

    $transferService = app(AssetTransferService::class);
    $transfer = $transferService->create($requester, [
        'asset_id' => $asset->id,
        'to_location_id' => $destinationLocation->id,
        'to_position_id' => $destinationPosition->id,
        'transfer_date' => now()->toDateString(),
        'reason' => 'Assign unit to department laboratory',
    ]);

    $integrity = app(AssetIntegrityService::class);
    expect($integrity->handoverBlockers($asset))
        ->not->toBeEmpty()
        ->and(fn () => $integrity->ensureBookable($asset, now(), now()->addDay()))
        ->toThrow(ValidationException::class, 'pending location transfer');

    $approvedTransfer = $transferService->approve($approver, $transfer);
    $asset->refresh();

    expect($approvedTransfer->status)->toBe(AssetTransferStatus::Approved)
        ->and($asset->current_location_id)->toBe($destinationLocation->id)
        ->and($asset->position_id)->toBe($destinationPosition->id)
        ->and($asset->status)->toBe(AssetStatus::CheckedOut);
});

test('dashboard metrics separate expired vs near-expiry batches, sort recently deleted properly, measure sent-to-received lead times, and report full low stock counts', function () {
    $category = Category::factory()->create(['name' => 'Office Supplies']);
    $supplier = Supplier::factory()->create(['name' => 'Priority Supplier']);

    foreach (range(1, 35) as $index) {
        $product = Product::factory()->consumable()->create([
            'category_id' => $category->id,
            'supplier_id' => $supplier->id,
            'reorder_threshold' => 10,
            'name' => "Low Stock Item {$index}",
        ]);
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'on_hand_qty' => 2,
        ]);
    }

    $deletedOld = Product::factory()->consumable()->create(['name' => 'Deleted Dec 2025']);
    $deletedOld->forceFill(['deleted_at' => CarbonImmutable::parse('2025-12-20 08:00:00')])->save();

    $deletedNew = Product::factory()->consumable()->create(['name' => 'Deleted Nov 2026']);
    $deletedNew->forceFill(['deleted_at' => CarbonImmutable::parse('2026-11-10 08:00:00')])->save();

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Received,
        'created_at' => CarbonImmutable::now()->subDays(20),
        'sent_at' => CarbonImmutable::now()->subDays(5),
        'received_at' => CarbonImmutable::now()->subDays(1),
    ]);

    $lotProduct = Product::factory()->consumable()->create([
        'category_id' => $category->id,
        'reorder_threshold' => 1,
    ]);
    ProductStock::factory()->create(['product_id' => $lotProduct->id, 'on_hand_qty' => 20]);

    $expiredLot = StockLot::factory()->create([
        'product_id' => $lotProduct->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'expires_at' => CarbonImmutable::now()->subDays(3)->toDateString(),
    ]);
    $nearExpiryLot = StockLot::factory()->create([
        'product_id' => $lotProduct->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'expires_at' => CarbonImmutable::now()->addDays(5)->toDateString(),
    ]);

    $adminStats = app(DashboardStatsService::class)->getAdminStats([]);

    expect($adminStats['lowStock'][0]['category'])->toBe('Office Supplies')
        ->and($adminStats['kpiSummary']['low_stock_count'])->toBe(35)
        ->and($adminStats['kpiSummary']['near_expiry_batch_count'])->toBe(1)
        ->and($adminStats['kpiSummary']['expired_batch_count'])->toBe(1)
        ->and(collect($adminStats['nearExpiryLots'])->pluck('id')->all())->toBe([$nearExpiryLot->id])
        ->and(collect($adminStats['expiredLots'])->pluck('id')->all())->toBe([$expiredLot->id]);

    $deletedNames = collect($adminStats['recentlyDeleted'])->pluck('name')->values()->all();
    expect(array_search('Deleted Nov 2026', $deletedNames, true))
        ->toBeLessThan(array_search('Deleted Dec 2025', $deletedNames, true));

    $supplierMetric = collect($adminStats['supplierPerformance'])->firstWhere('name', $supplier->name);
    expect($supplierMetric)->not->toBeNull()
        ->and((float) $supplierMetric['avg_lead_time_days'])->toBeGreaterThanOrEqual(3.5)
        ->and((float) $supplierMetric['avg_lead_time_days'])->toBeLessThanOrEqual(4.5);

    $this->artisan('app:inventory-generate-alerts')->assertExitCode(0);

    expect(InventoryAlert::query()->where('type', 'expired')->where('stock_lot_id', $expiredLot->id)->whereNull('resolved_at')->exists())->toBeTrue()
        ->and(InventoryAlert::query()->where('type', 'expiring')->where('stock_lot_id', $nearExpiryLot->id)->whereNull('resolved_at')->exists())->toBeTrue()
        ->and(InventoryAlert::query()->where('type', 'expiring')->where('stock_lot_id', $expiredLot->id)->exists())->toBeFalse();
});

test('postgresql partial unique indexes prevent duplicate unresolved inventory alerts while allowing upsert recovery', function () {
    try {
        DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $exception) {
        Assert::markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    $originalConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_testing');

    $product = null;
    $category = null;
    $origin = null;
    $supplier = null;
    $uom = null;
    try {
        $suffix = uniqid();
        $category = Category::factory()->create(['name' => "PG Cat {$suffix}"]);
        $origin = Origin::factory()->create(['name' => "PG Origin {$suffix}"]);
        $supplier = Supplier::factory()->create(['name' => "PG Supplier {$suffix}"]);
        $uom = UnitOfMeasurement::factory()->create(['name' => "PG Unit {$suffix}", 'abbreviation' => 'p'.random_int(1000, 9999)]);

        $product = Product::factory()->consumable()->create([
            'sku' => "PG-ALERT-{$suffix}",
            'category_id' => $category->id,
            'origin_id' => $origin->id,
            'supplier_id' => $supplier->id,
            'unit_of_measurement_id' => $uom->id,
        ]);
        $lot = StockLot::factory()->create(['product_id' => $product->id]);

        $first = InventoryAlert::upsertUnresolved(
            ['type' => 'low_stock', 'product_id' => $product->id, 'stock_lot_id' => null],
            ['message' => 'Initial alert', 'detected_at' => now()],
        );
        $second = InventoryAlert::upsertUnresolved(
            ['type' => 'low_stock', 'product_id' => $product->id, 'stock_lot_id' => null],
            ['message' => 'Updated alert', 'detected_at' => now()],
        );

        expect($second->id)->toBe($first->id)
            ->and(InventoryAlert::query()->where('type', 'low_stock')->where('product_id', $product->id)->whereNull('resolved_at')->count())->toBe(1);

        expect(fn () => DB::connection('pgsql_testing')->transaction(fn () => DB::connection('pgsql_testing')->table('inventory_alerts')->insert([
            'type' => 'low_stock',
            'product_id' => $product->id,
            'stock_lot_id' => null,
            'message' => 'Duplicate raw insert',
            'detected_at' => now(),
            'resolved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])))->toThrow(QueryException::class);

        InventoryAlert::query()->where('id', $first->id)->update(['resolved_at' => now()]);
        $reopened = InventoryAlert::upsertUnresolved(
            ['type' => 'low_stock', 'product_id' => $product->id, 'stock_lot_id' => null],
            ['message' => 'Reopened alert', 'detected_at' => now()],
        );

        expect($reopened->id)->not->toBe($first->id);

        InventoryAlert::upsertUnresolved(
            ['type' => 'expired', 'product_id' => $product->id, 'stock_lot_id' => $lot->id],
            ['message' => 'Expired lot', 'detected_at' => now()],
        );

        expect(fn () => DB::connection('pgsql_testing')->transaction(fn () => DB::connection('pgsql_testing')->table('inventory_alerts')->insert([
            'type' => 'expired',
            'product_id' => $product->id,
            'stock_lot_id' => $lot->id,
            'message' => 'Duplicate lot raw insert',
            'detected_at' => now(),
            'resolved_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ])))->toThrow(QueryException::class);
    } finally {
        if ($product instanceof Product) {
            InventoryAlert::query()->where('product_id', $product->id)->delete();
            StockLot::query()->where('product_id', $product->id)->delete();
            Product::withTrashed()->where('id', $product->id)->forceDelete();
        }
        $uom?->delete();
        $supplier?->delete();
        $origin?->delete();
        $category?->delete();
        DB::setDefaultConnection($originalConnection);
    }
});

test('supplier controller invalidates cached supplier options and inventory report filters by supplier_id', function () {
    $admin = createPriorityTwoUser('Admin');
    $csrfToken = 'priority-two-supplier-token';

    $supplierA = Supplier::factory()->create(['name' => 'Alpha Logistics']);
    $supplierB = Supplier::factory()->create(['name' => 'Beta Trading']);

    Product::factory()->consumable()->create(['name' => 'Alpha Item', 'supplier_id' => $supplierA->id]);
    Product::factory()->consumable()->create(['name' => 'Beta Item', 'supplier_id' => $supplierB->id]);

    $this->actingAs($admin)->get(route('inventory.products.create', absolute: false))->assertOk();
    expect(Cache::has(Supplier::OPTIONS_CACHE_KEY))->toBeTrue();

    $this->actingAs($admin)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.suppliers.store', absolute: false), [
            '_token' => $csrfToken,
            'name' => 'Gamma Depot',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Cache::has(Supplier::OPTIONS_CACHE_KEY))->toBeFalse();

    $report = app(ProductInventoryReportService::class)->build(
        Request::create('/reports/inventory-summary', 'GET', ['supplier_id' => (string) $supplierA->id]),
    );

    $rows = iterator_to_array($report->rows);
    expect($rows)->toHaveCount(1)
        ->and($rows[0]['name'])->toBe('Alpha Item');
});

test('forecasting does not zero-pad new products, keeps forecasts empty on insufficient history, and gates seasonal backtesting at 56 days', function () {
    $timezone = (string) config('app.timezone', 'UTC');
    $asOf = CarbonImmutable::now($timezone)->endOfDay();

    $newProduct = Product::factory()->consumable()->create([
        'created_at' => $asOf->subDays(2),
    ]);
    StockMovement::factory()->create([
        'product_id' => $newProduct->id,
        'movement_type' => 'issue',
        'qty_delta' => -3,
        'performed_at' => $asOf->subDay()->setTime(10, 0),
    ]);

    $collector = app(ConsumptionDataCollector::class);
    $observed = $collector->getDailyConsumption($newProduct, 90, $asOf);
    expect(count($observed))->toBe(3);

    $insufficientResult = app(DemandForecaster::class)->forecast($newProduct, null, $asOf);
    expect($insufficientResult->hasSufficientHistory)->toBeFalse()
        ->and($insufficientResult->forecastDailyConsumption)->toBe([])
        ->and($insufficientResult->predictedDailyConsumption)->toBe(0.0)
        ->and($insufficientResult->predictedDaysUntilStockout)->toBeNull();

    $mediumProduct = Product::factory()->consumable()->create([
        'created_at' => $asOf->subDays(45),
    ]);
    foreach (range(1, 45) as $offset) {
        StockMovement::factory()->create([
            'product_id' => $mediumProduct->id,
            'movement_type' => 'issue',
            'qty_delta' => -2,
            'performed_at' => $asOf->subDays($offset)->setTime(9, 0),
        ]);
    }

    $evaluation = app(ForecastBacktester::class)->evaluate($mediumProduct, 14, 45, $asOf);
    expect($evaluation['methods'])->not->toHaveKey('seasonal')
        ->and($evaluation['methods'])->toHaveKeys(['moving_average', 'exponential_smoothing']);
});
