<?php

use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Booking;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\seed;

/**
 * Seed the full UAT dataset as of a given moment.
 */
function seedHistoryAt(string $moment): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse($moment, (string) config('app.timezone')));
    seed(DatabaseSeeder::class);
}

afterEach(fn () => CarbonImmutable::setTestNow());

/**
 * Integrity rules every seeded dataset must satisfy; returns the violation count per rule.
 *
 * @return array<string, int>
 */
function historyIntegrityViolations(): array
{
    $chainBreaks = 0;

    foreach (StockMovement::query()->whereNotNull('qty_delta')->where('qty_delta', '<>', 0)->orderBy('product_id')->orderBy('performed_at')->orderBy('id')->get(['product_id', 'qty_before', 'qty_after'])->groupBy('product_id') as $movements) {
        $previousAfter = null;

        foreach ($movements as $movement) {
            if ($previousAfter !== null && (int) $movement->qty_before !== $previousAfter) {
                $chainBreaks++;
            }

            $previousAfter = (int) $movement->qty_after;
        }
    }

    return [
        'negative stock' => DB::table('product_stocks')->where('on_hand_qty', '<', 0)->count(),
        'lot balance out of range' => DB::table('stock_lots')->where(fn ($q) => $q->where('qty_remaining', '<', 0)->orWhereColumn('qty_remaining', '>', 'qty_received'))->count(),
        'snapshot arithmetic' => DB::table('stock_movements')->whereNotNull('qty_delta')->whereRaw('qty_after <> qty_before + qty_delta')->count(),
        'running balance chain' => $chainBreaks,
        'lots do not reconcile with stock' => DB::table('product_stocks')->join('products', 'products.id', '=', 'product_stocks.product_id')->where('products.type', 'consumable')
            ->whereRaw('product_stocks.on_hand_qty <> (select coalesce(sum(qty_remaining), 0) from stock_lots where stock_lots.product_id = product_stocks.product_id)')->count(),
        'issue without lot' => DB::table('stock_movements')->where('movement_type', 'issue')->whereNull('stock_lot_id')->count(),
        'future movements' => DB::table('stock_movements')->where('performed_at', '>', now())->count(),
        'PO total mismatch' => DB::table('purchase_orders')->whereRaw('abs(subtotal - (select coalesce(sum(subtotal), 0) from purchase_order_lines where purchase_order_lines.purchase_order_id = purchase_orders.id)) > 0.01')->count(),
        'over-received PO line' => DB::table('purchase_order_lines')->whereColumn('qty_received', '>', 'qty_ordered')->count(),
        'issued differs from movements' => DB::table('requisition_lines')->join('requisitions', 'requisitions.id', '=', 'requisition_lines.requisition_id')
            ->where('requisitions.notes', 'like', 'Monthly % supply requisition for %')
            ->whereRaw("requisition_lines.qty_issued <> (select coalesce(-sum(qty_delta), 0) from stock_movements where stock_movements.requisition_id = requisition_lines.requisition_id and stock_movements.product_id = requisition_lines.product_id and movement_type = 'issue')")->count(),
        'overlapping approved bookings' => DB::table('bookings as a')->join('bookings as b', fn ($j) => $j->on('a.asset_id', '=', 'b.asset_id')->on('a.id', '<', 'b.id'))
            ->where('a.status', 'Approved')->where('b.status', 'Approved')->whereNull('a.deleted_at')->whereNull('b.deleted_at')
            ->whereColumn('a.start_at', '<', 'b.end_at')->whereColumn('b.start_at', '<', 'a.end_at')->count(),
        'maintenance before acquisition' => DB::table('asset_maintenances')->join('assets', 'assets.id', '=', 'asset_maintenances.asset_id')->whereNotNull('assets.acquisition_date')->whereRaw('date(asset_maintenances.scheduled_for) < date(assets.acquisition_date)')->count(),
        'transfer to same room' => DB::table('asset_transfers')->whereColumn('from_location_id', 'to_location_id')->count(),
        'retired asset available' => DB::table('assets')->whereIn('lifecycle_status', ['retired', 'disposed'])->where('status', 'Available')->count(),
        'duplicate serial numbers' => DB::table('assets')->whereNotNull('serial_number')->selectRaw('serial_number, count(*) c')->groupBy('serial_number')->havingRaw('count(*) > 1')->get()->count(),
        'self-approved transfers' => DB::table('asset_transfers')->where('status', 'approved')->whereColumn('approved_by', 'initiated_by')->count(),
        'products without unit' => DB::table('products')->whereNull('unit_of_measurement_id')->count(),
    ];
}

/**
 * @return array<string, array{count: int, max_id: int}>
 */
function historyIdentitySnapshot(): array
{
    $snapshot = [];

    foreach ([Requisition::class, PurchaseOrder::class, StockMovement::class, Booking::class, AssetMaintenance::class, AssetTransfer::class, Product::class, Asset::class] as $model) {
        $query = method_exists($model, 'withTrashed') ? $model::withTrashed() : $model::query();
        $snapshot[class_basename($model)] = ['count' => $query->count(), 'max_id' => (int) $query->max('id')];
    }

    return $snapshot;
}

test('the history spans 48 months of operations and satisfies every integrity rule', function () {
    seedHistoryAt('2026-10-31 09:00:00');

    $months = fn (string $table, string $column, ?callable $scope = null): int => DB::table($table)
        ->when($scope, $scope)
        ->get([$column])
        ->map(fn ($row) => CarbonImmutable::parse($row->{$column})->format('Y-m'))
        ->unique()
        ->count();

    expect($months('requisitions', 'created_at'))->toBeGreaterThanOrEqual(48)
        ->and($months('stock_movements', 'performed_at', fn ($q) => $q->where('movement_type', 'issue')))->toBeGreaterThanOrEqual(48)
        ->and($months('asset_transfers', 'transfer_date'))->toBeGreaterThanOrEqual(40)
        ->and($months('bookings', 'start_at'))->toBeGreaterThanOrEqual(45)
        ->and($months('asset_maintenances', 'scheduled_for'))->toBeGreaterThanOrEqual(36)
        ->and(PurchaseOrder::query()->where('po_number', 'like', 'PO-HIST-%-%')->count())->toBeGreaterThanOrEqual(48)
        ->and(PurchaseOrder::query()->where('po_number', 'like', 'PO-ACQ-%')->count())->toBeGreaterThanOrEqual(30)
        ->and(Requisition::query()->where('notes', 'like', 'Monthly % supply requisition for %')->count())->toBe(4 * 47)
        ->and(DB::table('inventory_count_sessions')->where('reference_no', 'like', 'PHY-HIST-%')->count())->toBeGreaterThanOrEqual(14)
        ->and(DB::table('forecast_snapshots')->distinct()->count('forecast_date'))->toBeGreaterThanOrEqual(12)
        ->and(Asset::query()->where('lifecycle_status', 'retired')->count())->toBeGreaterThan(0)
        ->and(array_filter(historyIntegrityViolations()))->toBe([]);
});

test('history purchase orders deliver exactly the quantity received into their lots', function () {
    seedHistoryAt('2026-10-31 09:00:00');

    $mismatches = DB::table('purchase_order_lines')
        ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
        ->join('products', 'products.id', '=', 'purchase_order_lines.product_id')
        ->where('purchase_orders.po_number', 'like', 'PO-HIST-%-%')
        ->get(['purchase_orders.po_number', 'products.id as product_id', 'products.sku', 'purchase_order_lines.qty_received'])
        ->filter(function ($line): bool {
            $reference = 'DR-'.substr($line->po_number, 8, 6).'-'.$line->sku;

            return (int) DB::table('stock_lots')->where('product_id', $line->product_id)->where('reference_no', $reference)->value('qty_received') !== (int) $line->qty_received;
        });

    expect($mismatches)->toBeEmpty();
});

test('same-day and next-day reruns keep every history record and its identity', function () {
    seedHistoryAt('2026-10-15 09:00:00');
    $initial = historyIdentitySnapshot();
    $ledger = StockMovement::query()->orderBy('id')->get(['id', 'qty_before', 'qty_after', 'qty_delta'])->toArray();

    seedHistoryAt('2026-10-15 16:30:00');

    expect(historyIdentitySnapshot())->toBe($initial)
        ->and(StockMovement::query()->orderBy('id')->get(['id', 'qty_before', 'qty_after', 'qty_delta'])->toArray())->toBe($ledger);

    $maintenanceIds = AssetMaintenance::query()->pluck('id')->all();
    $requisitionIds = Requisition::withTrashed()->pluck('id')->all();

    // A day later, records may only be added (e.g. a maintenance cycle that has become due);
    // existing ones keep their identity and no natural key is duplicated.
    seedHistoryAt('2026-10-16 09:00:00');

    $duplicateMaintenance = DB::table('asset_maintenances')
        ->selectRaw('asset_id, maintenance_type, scheduled_for, count(*) as c')
        ->groupBy('asset_id', 'maintenance_type', 'scheduled_for')
        ->havingRaw('count(*) > 1')
        ->get();

    expect(AssetMaintenance::query()->whereKey($maintenanceIds)->count())->toBe(count($maintenanceIds))
        ->and(Requisition::withTrashed()->whereKey($requisitionIds)->count())->toBe(count($requisitionIds))
        ->and(historyIdentitySnapshot()['Requisition'])->toBe($initial['Requisition'])
        ->and(historyIdentitySnapshot()['Product'])->toBe($initial['Product'])
        ->and(historyIdentitySnapshot()['Asset'])->toBe($initial['Asset'])
        ->and($duplicateMaintenance)->toBeEmpty()
        ->and(array_filter(historyIntegrityViolations()))->toBe([]);
});

test('a month-boundary rerun rolls the history window without duplicates', function () {
    seedHistoryAt('2026-10-31 09:00:00');
    $oldestRequisition = Requisition::query()->where('notes', 'like', 'Monthly % supply requisition for November 2022')->firstOrFail();
    $survivingRequisition = Requisition::query()->where('notes', 'like', 'Monthly College of Engineering supply requisition for June 2025')->firstOrFail();

    seedHistoryAt('2026-11-01 09:00:00');

    $history = Requisition::query()->where('notes', 'like', 'Monthly % supply requisition for %');

    expect((clone $history)->count())->toBe(4 * 47)
        ->and(Requisition::withTrashed()->whereKey($oldestRequisition->id)->exists())->toBeFalse()
        ->and(StockMovement::query()->where('requisition_id', $oldestRequisition->id)->exists())->toBeFalse()
        ->and((clone $history)->where('notes', 'like', '%October 2026')->count())->toBe(4)
        ->and(Requisition::query()->whereKey($survivingRequisition->id)->exists())->toBeTrue()
        ->and(array_filter(historyIntegrityViolations()))->toBe([]);
});

test('a year-boundary rerun rolls the window into the new year with valid ledgers', function () {
    seedHistoryAt('2026-12-31 09:00:00');
    $before = historyIdentitySnapshot();

    seedHistoryAt('2027-01-01 09:00:00');

    $history = Requisition::query()->where('notes', 'like', 'Monthly % supply requisition for %');

    expect((clone $history)->count())->toBe(4 * 47)
        ->and((clone $history)->where('notes', 'like', '%January 2023')->exists())->toBeFalse()
        ->and((clone $history)->where('notes', 'like', '%December 2026')->count())->toBe(4)
        ->and(historyIdentitySnapshot()['Product'])->toBe($before['Product'])
        ->and(historyIdentitySnapshot()['Asset'])->toBe($before['Asset'])
        ->and(array_filter(historyIntegrityViolations()))->toBe([]);
});

test('the catalog keeps every board-marker variant and assigns a unit to every product', function () {
    seedHistoryAt('2026-10-31 09:00:00');

    expect(Product::query()->where('sku', 'like', 'OFF-MARK-%')->count())->toBe(8)
        ->and(Product::query()->where('sku', 'like', 'OFF-MARK-BLA-%')->count())->toBe(2)
        ->and(Product::query()->where('sku', 'like', 'OFF-MARK-BLU-%')->count())->toBe(2)
        ->and(Product::withTrashed()->whereNull('unit_of_measurement_id')->count())->toBe(0);
});
