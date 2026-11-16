<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\ForecastSnapshot;
use App\Models\HandoverLog;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    public function __construct(
        private readonly DashboardStatsCache $cache,
    ) {}

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    public function getAdminStats(array $range): array
    {
        return $this->cache->remember('admin', $range, fn (): array => $this->buildAdminStats($range));
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    public function getProcurementStats(array $range): array
    {
        return $this->cache->remember('procurement', $range, fn (): array => $this->buildProcurementStats($range));
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    public function getCustodianStats(User $user, array $range): array
    {
        $cacheKey = 'custodian:'.$user->id;

        return $this->cache->remember($cacheKey, $range, fn (): array => $this->buildCustodianStats($user, $range));
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    private function buildAdminStats(array $range): array
    {
        $from = isset($range['from']) ? CarbonImmutable::parse($range['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($range['to']) ? CarbonImmutable::parse($range['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();

        return [
            'alerts' => $this->activeAlerts(),
            'forecastSummary' => $this->getForecastSummary(),
            'lowStock' => $this->lowStockProducts(),
            'unserviceableAssets' => $this->unserviceableAssets(),
            'assetStatusCounts' => $this->assetStatusCounts(),
            'receivingTrends' => $this->movementTrends('receive', $from, $to),
            'issuingTrends' => $this->movementTrends('issue', $from, $to),
            'requisitionSummary' => $this->requisitionSummary($from, $to),
            'bookingSummary' => $this->bookingSummary($from, $to),
            'purchaseOrderSummary' => $this->purchaseOrderSummary($from, $to),
            'supplierPerformance' => $this->supplierPerformance(),
            'assetConditionSummary' => $this->assetConditionSummary(),
            'recentlyDeleted' => $this->recentlyDeleted(),
            'kpiSummary' => $this->kpiSummary(),
            'nearExpiryLots' => $this->nearExpiryLots(),
            'expiredLots' => $this->expiredLots(),
        ];
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    private function buildProcurementStats(array $range): array
    {
        $from = isset($range['from']) ? CarbonImmutable::parse($range['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($range['to']) ? CarbonImmutable::parse($range['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();

        return [
            'alerts' => $this->activeAlerts(),
            'forecastSummary' => $this->getForecastSummary(),
            'lowStock' => $this->lowStockProducts(),
            'purchaseOrderSummary' => $this->purchaseOrderSummary($from, $to),
            'supplierPerformance' => $this->supplierPerformance(),
            'requisitionSummary' => $this->requisitionSummary($from, $to),
            'bookingSummary' => $this->bookingSummary($from, $to),
            'kpiSummary' => $this->kpiSummary(),
        ];
    }

    /**
     * @return array{
     *     forecast_date: string|null,
     *     last_generated_at: string|null,
     *     urgent_count: int,
     *     at_risk_count: int,
     *     average_confidence: float|null,
     *     items: list<array{
     *         product_id: int,
     *         product_name: string,
     *         sku: string,
     *         current_on_hand_qty: int,
     *         reorder_point_qty: int,
     *         predicted_daily_consumption: float,
     *         predicted_days_until_stockout: int|null,
     *         predicted_stockout_date: string|null,
     *         recommended_reorder_qty: int,
     *         confidence_score: float|null
     *     }>
     * }
     */
    public function getForecastSummary(): array
    {
        $latestForecastDate = ForecastSnapshot::query()->max('forecast_date');

        if ($latestForecastDate === null) {
            return [
                'forecast_date' => null,
                'last_generated_at' => null,
                'urgent_count' => 0,
                'at_risk_count' => 0,
                'average_confidence' => null,
                'items' => [],
            ];
        }

        $latestForecastValue = (string) $latestForecastDate;
        $latestForecastDate = CarbonImmutable::parse($latestForecastValue)->toDateString();
        $snapshotSummary = ForecastSnapshot::query()
            ->where('forecast_date', $latestForecastValue)
            ->selectRaw('MAX(generated_at) as last_generated_at')
            ->selectRaw('SUM(CASE WHEN predicted_days_until_stockout IS NOT NULL AND predicted_days_until_stockout <= 7 THEN 1 ELSE 0 END) as urgent_count')
            ->selectRaw('SUM(CASE WHEN predicted_days_until_stockout BETWEEN 8 AND 14 THEN 1 ELSE 0 END) as at_risk_count')
            ->selectRaw('AVG(confidence_score) as average_confidence')
            ->toBase()
            ->first();
        /** @var Collection<int, ForecastSnapshot> $latestSnapshots */
        $latestSnapshots = ForecastSnapshot::query()
            ->with('product:id,sku,name')
            ->where('forecast_date', $latestForecastValue)
            ->orderByRaw('CASE WHEN predicted_days_until_stockout IS NULL THEN 1 ELSE 0 END')
            ->orderBy('predicted_days_until_stockout')
            ->orderByDesc('recommended_reorder_qty')
            ->orderByDesc('generated_at')
            ->limit(8)
            ->get();
        $items = $latestSnapshots
            ->map(fn (ForecastSnapshot $snapshot) => [
                'product_id' => $snapshot->product_id,
                'product_name' => $snapshot->product->name ?? 'Unknown product',
                'sku' => $snapshot->product->sku ?? 'N/A',
                'current_on_hand_qty' => $snapshot->current_on_hand_qty,
                'reorder_point_qty' => $snapshot->reorder_point_qty,
                'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
                'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout,
                'predicted_stockout_date' => $snapshot->predicted_stockout_date !== null
                    ? CarbonImmutable::parse((string) $snapshot->predicted_stockout_date)->toDateString()
                    : null,
                'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
                'confidence_score' => $snapshot->confidence_score !== null
                    ? round($snapshot->confidence_score, 2)
                    : null,
            ])
            ->values()
            ->toArray();

        $lastGeneratedAt = $snapshotSummary?->last_generated_at;
        $averageConfidence = $snapshotSummary?->average_confidence;

        return [
            'forecast_date' => $latestForecastDate,
            'last_generated_at' => $lastGeneratedAt !== null
                ? CarbonImmutable::parse((string) $lastGeneratedAt)->toISOString()
                : null,
            'urgent_count' => (int) ($snapshotSummary->urgent_count ?? 0),
            'at_risk_count' => (int) ($snapshotSummary->at_risk_count ?? 0),
            'average_confidence' => $averageConfidence !== null
                ? round((float) $averageConfidence, 2)
                : null,
            'items' => $items,
        ];
    }

    /**
     * @return list<array{id: int, type: string, message: string, detected_at: string}>
     */
    private function activeAlerts(): array
    {
        return InventoryAlert::query()
            ->whereNull('resolved_at')
            ->orderByDesc('detected_at')
            ->limit(20)
            ->get(['id', 'type', 'message', 'detected_at'])
            ->toArray();
    }

    /**
     * @return Builder<Product>
     */
    private function lowStockQuery(): Builder
    {
        return Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereHas('stock', fn ($q) => $q->whereColumn('product_stocks.on_hand_qty', '<=', 'products.reorder_threshold'));
    }

    /**
     * @return list<array{id: int, sku: string, name: string, category: string|null, on_hand_qty: int|null, reorder_threshold: int}>
     */
    private function lowStockProducts(): array
    {
        /** @var Collection<int, Product> $products */
        $products = $this->lowStockQuery()
            ->with(['stock:id,product_id,on_hand_qty', 'category:id,name'])
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'sku', 'name', 'category_id', 'reorder_threshold']);

        return $products
            ->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name,
                'on_hand_qty' => $p->stock?->on_hand_qty,
                'reorder_threshold' => $p->reorder_threshold,
            ])
            ->toArray();
    }

    /**
     * @return list<array{id: int, tag_code: string, status: string, name: string|null}>
     */
    private function unserviceableAssets(): array
    {
        /** @var Collection<int, Asset> $assets */
        $assets = Asset::query()
            ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
            ->with('product:id,name')
            ->orderBy('status')
            ->orderBy('tag_code')
            ->limit(30)
            ->get(['id', 'product_id', 'tag_code', 'status']);

        return $assets
            ->map(fn (Asset $a) => [
                'id' => $a->id,
                'tag_code' => $a->tag_code,
                'status' => $a->status->value,
                'name' => $a->product?->name,
            ])
            ->toArray();
    }

    /**
     * @return array<string, int>
     */
    private function assetStatusCounts(): array
    {
        return Asset::query()
            ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
            ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->union([AssetStatus::Unserviceable->value => 0, AssetStatus::Condemned->value => 0])
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function movementTrends(string $type, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $maxRangeDays = max(1, (int) config('dashboard.max_range_days', 366));
        $effectiveFrom = $from->diffInDays($to) > $maxRangeDays
            ? $to->subDays($maxRangeDays)->startOfDay()
            : $from;

        $rows = StockMovement::query()
            ->where('movement_type', $type)
            ->whereBetween('performed_at', [$effectiveFrom, $to])
            ->select(DB::raw('DATE(performed_at) as date'), DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('date')
            ->get();

        $dates = [];
        $current = $effectiveFrom->copy();

        while ($current <= $to) {
            $dates[$current->toDateString()] = 0;
            $current = $current->addDay();
        }

        foreach ($rows as $row) {
            $dates[$row->getAttribute('date')] = (int) $row->getAttribute('count');
        }

        return [
            'labels' => array_keys($dates),
            'data' => array_values($dates),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function requisitionSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = Requisition::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [];

        foreach (RequisitionStatus::cases() as $case) {
            $summary[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function bookingSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = Booking::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [];

        foreach (BookingStatus::cases() as $case) {
            $summary[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    private function purchaseOrderSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $counts = PurchaseOrder::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [];

        foreach (PurchaseOrderStatus::cases() as $case) {
            $summary[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $summary;
    }

    /**
     * @return list<array{id: int, name: string, total_pos: int, open_pos: int, avg_lead_time_days: float|null}>
     */
    public function supplierPerformance(): array
    {
        $driver = DB::connection()->getDriverName();
        $openStatuses = [
            PurchaseOrderStatus::Draft->value,
            PurchaseOrderStatus::Sent->value,
            PurchaseOrderStatus::Partial->value,
        ];
        $startedExpr = 'COALESCE(purchase_orders.sent_at, purchase_orders.created_at)';
        $leadTimeExpr = match ($driver) {
            'pgsql' => "CASE WHEN purchase_orders.received_at IS NOT NULL AND {$startedExpr} IS NOT NULL THEN GREATEST(0.0, EXTRACT(EPOCH FROM (purchase_orders.received_at - {$startedExpr})) / 86400.0) END",
            'sqlite' => "CASE WHEN purchase_orders.received_at IS NOT NULL AND {$startedExpr} IS NOT NULL THEN MAX(0.0, julianday(purchase_orders.received_at) - julianday({$startedExpr})) END",
            default => "CASE WHEN purchase_orders.received_at IS NOT NULL AND {$startedExpr} IS NOT NULL THEN GREATEST(0.0, TIMESTAMPDIFF(MICROSECOND, {$startedExpr}, purchase_orders.received_at) / 86400000000.0) END",
        };

        /** @var Collection<int, Supplier> $suppliers */
        $suppliers = Supplier::query()
            ->leftJoin('purchase_orders', function ($join): void {
                $join->on('purchase_orders.supplier_id', '=', 'suppliers.id')
                    ->whereNull('purchase_orders.deleted_at');
            })
            ->select([
                'suppliers.id',
                'suppliers.name',
            ])
            ->selectRaw('COUNT(purchase_orders.id) as total_pos')
            ->selectRaw('SUM(CASE WHEN purchase_orders.status IN (?, ?, ?) THEN 1 ELSE 0 END) as open_pos', $openStatuses)
            ->selectRaw("AVG({$leadTimeExpr}) as avg_lead_time_days")
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_pos')
            ->orderBy('suppliers.name')
            ->orderBy('suppliers.id')
            ->limit(10)
            ->get();

        return $suppliers
            ->map(static fn (Supplier $supplier): array => [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
                'total_pos' => (int) $supplier->getAttribute('total_pos'),
                'open_pos' => (int) $supplier->getAttribute('open_pos'),
                'avg_lead_time_days' => $supplier->getAttribute('avg_lead_time_days') !== null
                    ? round((float) $supplier->getAttribute('avg_lead_time_days'), 2)
                    : null,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function assetConditionSummary(): array
    {
        $counts = Asset::query()
            ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $summary = [];

        foreach (AssetStatus::cases() as $case) {
            $summary[$case->value] = (int) ($counts[$case->value] ?? 0);
        }

        return $summary;
    }

    /**
     * @return list<array{id: int, type: string, name: string, deleted_at: string, deleted_by: string|null, restore_url: string}>
     */
    private function recentlyDeleted(): array
    {
        /** @var Collection<int, Product> $deletedProducts */
        $deletedProducts = Product::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'name', 'deleted_at']);

        $products = $deletedProducts
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'type' => 'product',
                'name' => $product->name,
                'deleted_at' => $this->formatDeletedAt($product->deleted_at),
                '_sort_ts' => $product->deleted_at !== null ? CarbonImmutable::parse((string) $product->deleted_at)->getTimestamp() : 0,
                'deleted_by' => $product->deletedBy->name ?? 'Unknown',
                'restore_url' => route('inventory.products.restore', $product->id),
            ]);

        /** @var Collection<int, Booking> $deletedBookings */
        $deletedBookings = Booking::onlyTrashed()
            ->with('deletedBy:id,name', 'asset:id,tag_code')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'asset_id', 'deleted_at']);

        $bookings = $deletedBookings
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'type' => 'booking',
                'name' => 'Booking: '.($booking->asset->tag_code ?? 'Unknown'),
                'deleted_at' => $this->formatDeletedAt($booking->deleted_at),
                '_sort_ts' => $booking->deleted_at !== null ? CarbonImmutable::parse((string) $booking->deleted_at)->getTimestamp() : 0,
                'deleted_by' => $booking->deletedBy->name ?? 'Unknown',
                'restore_url' => route('inventory.bookings.restore', $booking->id),
            ]);

        /** @var Collection<int, Requisition> $deletedRequisitions */
        $deletedRequisitions = Requisition::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'deleted_at']);

        $requisitions = $deletedRequisitions
            ->map(fn (Requisition $requisition) => [
                'id' => $requisition->id,
                'type' => 'requisition',
                'name' => 'Requisition #'.$requisition->id,
                'deleted_at' => $this->formatDeletedAt($requisition->deleted_at),
                '_sort_ts' => $requisition->deleted_at !== null ? CarbonImmutable::parse((string) $requisition->deleted_at)->getTimestamp() : 0,
                'deleted_by' => $requisition->deletedBy->name ?? 'Unknown',
                'restore_url' => route('inventory.requisitions.restore', $requisition->id),
            ]);

        return $products
            ->merge($bookings)
            ->merge($requisitions)
            ->sortByDesc('_sort_ts')
            ->take(5)
            ->map(function (array $item): array {
                unset($item['_sort_ts']);

                return $item;
            })
            ->values()
            ->toArray();
    }

    private function formatDeletedAt(mixed $deletedAt): string
    {
        return $deletedAt !== null
            ? CarbonImmutable::parse((string) $deletedAt)->format('M d, Y H:i')
            : 'Unknown';
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    private function buildCustodianStats(User $user, array $range): array
    {
        $from = isset($range['from']) ? CarbonImmutable::parse($range['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($range['to']) ? CarbonImmutable::parse($range['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $positionId = $user->position_id;

        $myOpenRequisitions = Requisition::query()
            ->where('requester_id', $user->id)
            ->whereIn('status', [
                RequisitionStatus::Submitted,
                RequisitionStatus::Approved,
                RequisitionStatus::PartiallyIssued,
                RequisitionStatus::Backordered,
            ])
            ->count();

        $myUpcomingBookings = Booking::query()
            ->where('requester_id', $user->id)
            ->where('status', BookingStatus::Approved)
            ->where('end_at', '>=', CarbonImmutable::now())
            ->count();

        $assignedAssets = $positionId
            ? Asset::query()->where('position_id', $positionId)->where('status', AssetStatus::CheckedOut)->count()
            : 0;

        $pendingHandovers = HandoverLog::query()
            ->where('to_user_id', $user->id)
            ->pending()
            ->count();

        return [
            'custodianSummary' => [
                'my_open_requisitions' => $myOpenRequisitions,
                'my_upcoming_bookings' => $myUpcomingBookings,
                'assigned_assets' => $assignedAssets,
                'pending_handovers' => $pendingHandovers,
            ],
            'requisitionSummary' => Requisition::query()
                ->where('requester_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->mapWithKeys(fn (mixed $count, string $status) => [$status => (int) $count])
                ->all(),
            'bookingSummary' => Booking::query()
                ->where('requester_id', $user->id)
                ->whereBetween('created_at', [$from, $to])
                ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->mapWithKeys(fn (mixed $count, string $status) => [$status => (int) $count])
                ->all(),
            'kpiSummary' => [
                'my_open_requisitions' => $myOpenRequisitions,
                'my_upcoming_bookings' => $myUpcomingBookings,
                'pending_handovers' => $pendingHandovers,
                'assigned_assets' => $assignedAssets,
            ],
        ];
    }

    /**
     * @return array{
     *     issued_today_count: int,
     *     near_expiry_batch_count: int,
     *     expired_batch_count: int,
     *     low_stock_count: int,
     *     pending_requisitions_count: int,
     *     pending_bookings_count: int,
     *     active_alerts_count: int,
     *     open_purchase_orders_count: int
     * }
     */
    private function kpiSummary(): array
    {
        $today = CarbonImmutable::now();

        $issuedTodayCount = StockMovement::query()
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [$today->startOfDay(), $today->endOfDay()])
            ->count();

        $nearExpiryBatchCount = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', $today->toDateString())
            ->whereDate('expires_at', '<=', $today->addDays(30)->toDateString())
            ->count();

        $expiredBatchCount = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today->toDateString())
            ->count();

        $pendingRequisitions = (int) (Requisition::query()
            ->where('status', RequisitionStatus::Submitted)
            ->count());

        $pendingBookings = (int) (Booking::query()
            ->where('status', BookingStatus::Requested)
            ->count());

        $openPurchaseOrders = (int) (PurchaseOrder::query()
            ->whereIn('status', [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::Partial,
            ])
            ->count());

        return [
            'issued_today_count' => $issuedTodayCount,
            'near_expiry_batch_count' => $nearExpiryBatchCount,
            'expired_batch_count' => $expiredBatchCount,
            'low_stock_count' => $this->lowStockQuery()->count(),
            'pending_requisitions_count' => $pendingRequisitions,
            'pending_bookings_count' => $pendingBookings,
            'active_alerts_count' => InventoryAlert::query()->whereNull('resolved_at')->count(),
            'open_purchase_orders_count' => $openPurchaseOrders,
        ];
    }

    /**
     * @return list<array{id: int, product_name: string, sku: string, qty_remaining: int, expires_at: string}>
     */
    private function nearExpiryLots(): array
    {
        $today = CarbonImmutable::now();
        $threshold = $today->addDays(30);

        /** @var Collection<int, StockLot> $lots */
        $lots = StockLot::query()
            ->with('product:id,sku,name')
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', $today->toDateString())
            ->whereDate('expires_at', '<=', $threshold->toDateString())
            ->orderBy('expires_at')
            ->limit(10)
            ->get(['id', 'product_id', 'qty_remaining', 'expires_at']);

        return $lots
            ->map(fn (StockLot $lot) => [
                'id' => $lot->id,
                'product_name' => $lot->product->name ?? 'Unknown',
                'sku' => $lot->product->sku ?? 'N/A',
                'qty_remaining' => $lot->qty_remaining,
                'expires_at' => CarbonImmutable::parse((string) $lot->expires_at)->toDateString(),
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, product_name: string, sku: string, qty_remaining: int, expires_at: string}>
     */
    private function expiredLots(): array
    {
        $today = CarbonImmutable::now()->toDateString();

        /** @var Collection<int, StockLot> $lots */
        $lots = StockLot::query()
            ->with('product:id,sku,name')
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $today)
            ->orderBy('expires_at')
            ->limit(10)
            ->get(['id', 'product_id', 'qty_remaining', 'expires_at']);

        return $lots
            ->map(fn (StockLot $lot) => [
                'id' => $lot->id,
                'product_name' => $lot->product->name ?? 'Unknown',
                'sku' => $lot->product->sku ?? 'N/A',
                'qty_remaining' => $lot->qty_remaining,
                'expires_at' => CarbonImmutable::parse((string) $lot->expires_at)->toDateString(),
            ])
            ->all();
    }
}
