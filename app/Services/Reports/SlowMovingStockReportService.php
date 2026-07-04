<?php

namespace App\Services\Reports;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SlowMovingStockReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $days = max(30, min(365, $request->integer('days', 90)));
        $cutoff = CarbonImmutable::now()->subDays($days);

        $recentIssueCounts = StockMovement::query()
            ->where('movement_type', 'issue')
            ->where('performed_at', '>=', $cutoff)
            ->select('product_id', DB::raw('CAST(COUNT(*) AS INTEGER) as issue_count'))
            ->groupBy('product_id')
            ->pluck('issue_count', 'product_id');

        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereHas('stock', fn ($query) => $query->where('on_hand_qty', '>', 0))
            ->with(['stock:id,product_id,on_hand_qty', 'category:id,name'])
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'category_id']);

        $rows = $products
            ->filter(fn (Product $product) => (int) ($recentIssueCounts[$product->id] ?? 0) === 0)
            ->map(fn (Product $product) => [
                'sku' => $product->sku,
                'product_name' => $product->name,
                'category' => $product->category?->name ?? '',
                'on_hand_qty' => $product->stock?->on_hand_qty ?? 0,
                'issues_in_window' => 0,
                'window_days' => $days,
            ])
            ->values()
            ->all();

        return new TableReport(
            title: 'Slow-Moving Stock Report',
            filenameBase: 'slow-moving-stock-report',
            filters: $this->normalizeFilters([
                'Lookback days' => (string) $days,
                'Criteria' => 'Consumables with on-hand stock and zero issues in window',
            ]),
            columns: [
                'sku' => 'SKU',
                'product_name' => 'Product name',
                'category' => 'Category',
                'on_hand_qty' => 'On hand qty',
                'issues_in_window' => 'Issues in window',
                'window_days' => 'Window (days)',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
