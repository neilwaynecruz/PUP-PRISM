<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatsService
{
    /**
     * @param  array{from: string|null, to: string|null}  $range
     * @return array<string, mixed>
     */
    public function getAdminStats(array $range): array
    {
        $from = isset($range['from']) ? CarbonImmutable::parse($range['from'])->startOfDay() : CarbonImmutable::now()->startOfMonth();
        $to = isset($range['to']) ? CarbonImmutable::parse($range['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $cacheKey = 'dashboard_admin_'.$from->toDateString().'_'.$to->toDateString();

        return Cache::remember($cacheKey, 60, function () use ($from, $to) {
            return [
                'alerts' => $this->activeAlerts(),
                'lowStock' => $this->lowStockProducts(),
                'unserviceableAssets' => $this->unserviceableAssets(),
                'assetStatusCounts' => $this->assetStatusCounts(),
                'receivingTrends' => $this->movementTrends('receive', $from, $to),
                'issuingTrends' => $this->movementTrends('issue', $from, $to),
                'requisitionSummary' => $this->requisitionSummary($from, $to),
                'bookingSummary' => $this->bookingSummary($from, $to),
                'assetConditionSummary' => $this->assetConditionSummary(),
                'recentlyDeleted' => $this->recentlyDeleted(),
            ];
        });
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
     * @return list<array{id: int, type: string, name: string, deleted_at: string, deleted_by: string|null}>
     */
    private function recentlyDeleted(): array
    {
        $products = Product::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'name', 'deleted_at'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'type' => 'product',
                'name' => $p->name,
                'deleted_at' => $p->deleted_at->format('M d, Y H:i'),
                'deleted_by' => $p->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.products.restore', $p->id),
            ]);

        $bookings = Booking::onlyTrashed()
            ->with('deletedBy:id,name', 'asset:id,tag_code')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'asset_id', 'deleted_at'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'type' => 'booking',
                'name' => 'Booking: '.($b->asset?->tag_code ?? 'Unknown'),
                'deleted_at' => $b->deleted_at->format('M d, Y H:i'),
                'deleted_by' => $b->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.bookings.restore', $b->id),
            ]);

        $requisitions = Requisition::onlyTrashed()
            ->with('deletedBy:id,name')
            ->orderByDesc('deleted_at')
            ->limit(5)
            ->get(['id', 'deleted_at'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => 'requisition',
                'name' => 'Requisition #'.$r->id,
                'deleted_at' => $r->deleted_at->format('M d, Y H:i'),
                'deleted_by' => $r->deletedBy?->name ?? 'Unknown',
                'restore_url' => route('inventory.requisitions.restore', $r->id),
            ]);

        return $products
            ->merge($bookings)
            ->merge($requisitions)
            ->sortByDesc('deleted_at')
            ->take(5)
            ->values()
            ->toArray();
    }
}
