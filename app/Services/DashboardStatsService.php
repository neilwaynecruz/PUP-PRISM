<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
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
            'assetConditionSummary' => $this->assetConditionSummary(),
            'recentlyDeleted' => $this->recentlyDeleted(),
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
        $snapshots = ForecastSnapshot::query()
            ->with('product:id,sku,name')
            ->orderByDesc('forecast_date')
            ->orderByDesc('generated_at')
            ->get();

        if ($snapshots->isEmpty()) {
            return [
                'forecast_date' => null,
                'last_generated_at' => null,
                'urgent_count' => 0,
                'at_risk_count' => 0,
                'average_confidence' => null,
                'items' => [],
            ];
        }

        $latestForecastDate = $snapshots->first()?->forecast_date?->toDateString()
            ?? CarbonImmutable::parse((string) $snapshots->first()?->forecast_date)->toDateString();
        $latestSnapshots = $snapshots
            ->filter(fn (ForecastSnapshot $snapshot) => (
                $snapshot->forecast_date?->toDateString()
                ?? CarbonImmutable::parse((string) $snapshot->forecast_date)->toDateString()
            ) === $latestForecastDate)
            ->values();
        $items = $latestSnapshots
            ->sort(function (ForecastSnapshot $left, ForecastSnapshot $right): int {
                $leftDays = $left->predicted_days_until_stockout ?? PHP_INT_MAX;
                $rightDays = $right->predicted_days_until_stockout ?? PHP_INT_MAX;

                if ($leftDays === $rightDays) {
                    return $right->recommended_reorder_qty <=> $left->recommended_reorder_qty;
                }

                return $leftDays <=> $rightDays;
            })
            ->take(8)
            ->map(fn (ForecastSnapshot $snapshot) => [
                'product_id' => $snapshot->product_id,
                'product_name' => $snapshot->product?->name ?? 'Unknown product',
                'sku' => $snapshot->product?->sku ?? 'N/A',
                'current_on_hand_qty' => $snapshot->current_on_hand_qty,
                'reorder_point_qty' => $snapshot->reorder_point_qty,
                'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
                'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout,
                'predicted_stockout_date' => $snapshot->predicted_stockout_date?->toDateString(),
                'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
                'confidence_score' => $snapshot->confidence_score !== null
                    ? round($snapshot->confidence_score, 2)
                    : null,
            ])
            ->values()
            ->toArray();

        $confidenceScores = $latestSnapshots
            ->pluck('confidence_score')
            ->filter(fn (mixed $score) => $score !== null)
            ->map(fn (mixed $score) => (float) $score);

        return [
            'forecast_date' => $latestForecastDate,
            'last_generated_at' => ($latestGeneratedAt = $latestSnapshots->max('generated_at')) !== null
                ? CarbonImmutable::parse((string) $latestGeneratedAt)->toISOString()
                : null,
            'urgent_count' => $latestSnapshots
                ->filter(fn (ForecastSnapshot $snapshot) => $snapshot->predicted_days_until_stockout !== null
                    && $snapshot->predicted_days_until_stockout <= 7)
                ->count(),
            'at_risk_count' => $latestSnapshots
                ->filter(fn (ForecastSnapshot $snapshot) => $snapshot->predicted_days_until_stockout !== null
                    && $snapshot->predicted_days_until_stockout <= 14)
                ->count(),
            'average_confidence' => $confidenceScores->isNotEmpty()
                ? round((float) $confidenceScores->avg(), 2)
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
