<?php

namespace App\Http\Controllers;

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Models\Asset;
use App\Models\InventoryAlert;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $alerts = [];
        $lowStock = collect();
        $unserviceableAssets = collect();
        $assetStatusCounts = [AssetStatus::Unserviceable->value => 0, AssetStatus::Condemned->value => 0];

        if ($user->hasRole('Admin')) {
            $alerts = InventoryAlert::query()
                ->whereNull('resolved_at')
                ->orderByDesc('detected_at')
                ->limit(20)
                ->get(['id', 'type', 'message', 'detected_at']);

            $lowStock = Product::query()
                ->where('type', ProductType::Consumable)
                ->where('is_active', true)
                ->whereHas('stock')
                ->with(['stock:id,product_id,on_hand_qty', 'category:id,name'])
                ->whereHas('stock', fn ($q) => $q->whereColumn('product_stocks.on_hand_qty', '<=', 'products.reorder_threshold'))
                ->orderBy('name')
                ->limit(30)
                ->get(['id', 'sku', 'name', 'reorder_threshold']);

            $assetStatusCounts = Asset::query()
                ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
                ->select('status', DB::raw('CAST(COUNT(*) AS INTEGER) as aggregate'))
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->union([AssetStatus::Unserviceable->value => 0, AssetStatus::Condemned->value => 0])
                ->toArray();

            $unserviceableAssets = Asset::query()
                ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
                ->with('product:id,name')
                ->orderBy('status')
                ->orderBy('tag_code')
                ->limit(30)
                ->get(['id', 'product_id', 'tag_code', 'status']);
        }

        return Inertia::render('Dashboard', [
            'alerts' => $alerts,
            'lowStock' => $lowStock->map(fn (Product $p) => [
                'id' => $p->id,
                'sku' => $p->sku,
                'name' => $p->name,
                'category' => $p->category?->name,
                'on_hand_qty' => $p->stock?->on_hand_qty,
                'reorder_threshold' => $p->reorder_threshold,
            ]),
            'unserviceableAssets' => $unserviceableAssets->map(fn (Asset $a) => [
                'id' => $a->id,
                'tag_code' => $a->tag_code,
                'status' => $a->status->value,
                'name' => $a->product?->name,
            ]),
            'assetStatusCounts' => [
                'labels' => [AssetStatus::Unserviceable->value, AssetStatus::Condemned->value],
                'data' => [(int) ($assetStatusCounts[AssetStatus::Unserviceable->value] ?? 0), (int) ($assetStatusCounts[AssetStatus::Condemned->value] ?? 0)],
            ],
            'exportUrls' => $user->hasRole('Admin') ? [
                'assetConditionsCsv' => route('inventory.reports.asset-conditions', ['format' => 'csv'], absolute: false),
                'assetConditionsPdf' => route('inventory.reports.asset-conditions', ['format' => 'pdf'], absolute: false),
            ] : null,
        ]);
    }
}
