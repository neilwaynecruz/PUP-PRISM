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
            'kpiSummary' => $this->kpiSummary($from, $to),
            'nearExpiryLots' => $this->nearExpiryLots(),
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
            'kpiSummary' => $this->kpiSummary($from, $to),
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
                'product_name' => $snapshot->product?->name ?? 'Unknown product',
                'sku' => $snapshot->product?->sku ?? 'N/A',
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
            'urgent_count' => (int) ($snapshotSummary?->urgent_count ?? 0),
            'at_risk_count' => (int) ($snapshotSummary?->at_risk_count ?? 0),
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
     * @return list<array{id: int, sku: string, name: string, category: string|null, on_hand_qty: int|null, reorder_threshold: int}>
     */
    private function lowStockProducts(): array
    {
        return Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereHas('stock')
            ->with(['stock:id,product_id,on_hand_qty', 'category:id,name'])
            ->whereHas('stock', fn ($q) => $q->whereColumn('product_stocks.on_hand_qty', '<=', 'products.reorder_threshold'))
            ->orderBy('name')
            ->limit(30)
            ->get(['id', 'sku', 'name', 'reorder_threshold'])
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
        return Asset::query()
            ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
            ->with('product:id,name')
            ->orderBy('status')
            ->orderBy('tag_code')
            ->limit(30)
            ->get(['id', 'product_id', 'tag_code', 'status'])
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
        $rows = StockMovement::query()
            ->where('movement_type', $type)
            ->whereBetween('performed_at', [$from, $to])
            ->select(DB::raw('DATE(performed_at) as date'), DB::raw('CAST(COUNT(*) AS INTEGER) as count'))
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('date')
            ->get();

        $dates = [];
        $current = $from->copy();

        while ($current <= $to) {
            $dates[$current->toDateString()] = 0;
            $current = $current->addDay();
        }

        foreach ($rows as $row) {
            $dates[$row->date] = (int) $row->count;
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
        return Supplier::query()
            ->with([
                'purchaseOrders' => fn ($query) => $query
                    ->select(['id', 'supplier_id', 'status', 'created_at', 'received_at']),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
                'total_pos' => $supplier->purchaseOrders->count(),
                'open_pos' => $supplier->purchaseOrders
                    ->whereIn('status', [
                        PurchaseOrderStatus::Draft,
                        PurchaseOrderStatus::Sent,
                        PurchaseOrderStatus::Partial,
                    ])
                    ->count(),
                'avg_lead_time_days' => $supplier->purchaseOrders
                    ->filter(fn (PurchaseOrder $purchaseOrder) => $purchaseOrder->received_at !== null)
                    ->pipe(function ($purchaseOrders): ?float {
                        $durations = $purchaseOrders->map(function (PurchaseOrder $purchaseOrder): float {
                            /** @var CarbonImmutable $createdAt */
                            $createdAt = CarbonImmutable::parse($purchaseOrder->created_at);
                            /** @var CarbonImmutable $receivedAt */
                            $receivedAt = CarbonImmutable::parse($purchaseOrder->received_at);

                            return (float) $createdAt->diffInDays($receivedAt);
                        });

                        if ($durations->isEmpty()) {
                            return null;
                        }

                        return round((float) $durations->avg(), 2);
                    }),
            ])
            ->sortByDesc('total_pos')
            ->take(10)
            ->values()
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
        $products = Product::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'name', 'deleted_at'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'type' => 'product',
                'name' => $product->name,
                'deleted_at' => $this->formatDeletedAt($product->deleted_at),
                'deleted_by' => $product->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.products.restore', $product->id),
            ]);

        $bookings = Booking::onlyTrashed()
            ->with('deletedBy:id,name', 'asset:id,tag_code')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'asset_id', 'deleted_at'])
            ->map(fn (Booking $booking) => [
                'id' => $booking->id,
                'type' => 'booking',
                'name' => 'Booking: '.($booking->asset?->tag_code ?? 'Unknown'),
                'deleted_at' => $this->formatDeletedAt($booking->deleted_at),
                'deleted_by' => $booking->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.bookings.restore', $booking->id),
            ]);

        $requisitions = Requisition::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'deleted_at'])
            ->map(fn (Requisition $requisition) => [
                'id' => $requisition->id,
                'type' => 'requisition',
                'name' => 'Requisition #'.$requisition->id,
                'deleted_at' => $this->formatDeletedAt($requisition->deleted_at),
                'deleted_by' => $requisition->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.requisitions.restore', $requisition->id),
            ]);

        return $products
            ->merge($bookings)
            ->merge($requisitions)
            ->sortByDesc('deleted_at')
            ->take(5)
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
            ? Asset::query()->where('position_id', $positionId)->where('status', AssetStatus::Available)->count()
            : 0;

        $pendingHandovers = HandoverLog::query()
            ->where('to_user_id', $user->id)
            ->whereNull('verified_at')
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
     *     low_stock_count: int,
     *     pending_requisitions_count: int,
     *     pending_bookings_count: int,
     *     active_alerts_count: int,
     *     open_purchase_orders_count: int
     * }
     */
    private function kpiSummary(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $today = CarbonImmutable::now();

        $issuedTodayCount = StockMovement::query()
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [$today->startOfDay(), $today->endOfDay()])
            ->count();

        $nearExpiryBatchCount = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today->addDays(30))
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
            'low_stock_count' => count($this->lowStockProducts()),
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
        $threshold = CarbonImmutable::now()->addDays(30);

        return StockLot::query()
            ->with('product:id,sku,name')
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $threshold)
            ->orderBy('expires_at')
            ->limit(10)
            ->get(['id', 'product_id', 'qty_remaining', 'expires_at'])
            ->map(fn (StockLot $lot) => [
                'id' => $lot->id,
                'product_name' => $lot->product?->name ?? 'Unknown',
                'sku' => $lot->product?->sku ?? 'N/A',
                'qty_remaining' => $lot->qty_remaining,
                'expires_at' => CarbonImmutable::parse((string) $lot->expires_at)->toDateString(),
            ])
            ->all();
    }
}
