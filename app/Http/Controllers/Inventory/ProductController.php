<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductStoreRequest;
use App\Http\Requests\Inventory\ProductUpdateRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\ForecastSnapshot;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockMovement;
use App\Services\AuditLogService;
use App\Services\Forecasting\Data\ForecastResult;
use App\Services\Forecasting\DemandForecaster;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Product::class);
        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $originId = $request->integer('origin_id') ?: null;
        $active = $request->has('active') ? $request->boolean('active') : null;

        $products = Product::query()
            ->with([
                'category:id,name',
                'origin:id,name',
                'stock:id,product_id,on_hand_qty',
            ])
            ->withCount('assets')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', fn ($query) => $query->where('type', $type))
            ->when($categoryId !== null, fn ($query) => $query->where('category_id', $categoryId))
            ->when($originId !== null, fn ($query) => $query->where('origin_id', $originId))
            ->when($active !== null, fn ($query) => $query->where('is_active', $active))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/products/Index', [
            'filters' => [
                'search' => $search,
                'type' => $type,
                'category_id' => $categoryId,
                'origin_id' => $originId,
                'active' => $active,
            ],
            'products' => (new ProductCollection($products))->toArray($request),
            'categories' => $this->categoryOptions(),
            'origins' => $this->originOptions(),
            'exportUrls' => [
                'csv' => route('inventory.reports.products', [
                    'format' => 'csv',
                    ...$request->only(['search', 'type', 'category_id', 'origin_id', 'active']),
                ], absolute: false),
                'pdf' => route('inventory.reports.products', [
                    'format' => 'pdf',
                    ...$request->only(['search', 'type', 'category_id', 'origin_id', 'active']),
                ], absolute: false),
            ],
            'can' => [
                'create' => $request->user()?->can('create', Product::class) ?? false,
                'bulkUpdate' => $request->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Product::class);

        return Inertia::render('inventory/products/Create', [
            'categories' => $this->categoryOptions(),
            'origins' => $this->originOptions(),
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $validated = $request->validated();

        $product = DB::transaction(function () use ($validated): Product {
            $product = Product::create([
                'sku' => $validated['sku'],
                'name' => $validated['name'],
                'category_id' => $validated['category_id'] ?? null,
                'origin_id' => $validated['origin_id'] ?? null,
                'type' => $validated['type'],
                'reorder_threshold' => $validated['reorder_threshold'] ?? 0,
                'is_active' => $validated['is_active'],
            ]);

            if ($product->type === ProductType::Consumable) {
                ProductStock::create([
                    'product_id' => $product->id,
                    'on_hand_qty' => 0,
                ]);
            }

            return $product;
        });

        AuditLogService::logCreated($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('inventory.products.index');
    }

    public function show(Request $request, DemandForecaster $forecaster, int $product): Response|SymfonyResponse
    {
        $product = Product::query()->withTrashed()
            ->with([
                'category:id,name',
                'origin:id,name',
                'stock:id,product_id,on_hand_qty',
                'forecastProfile:id,product_id,method,lookback_days,forecast_horizon_days,lead_time_days,safety_stock_days,smoothing_factor,trend_factor,is_active',
            ])
            ->withCount('assets')
            ->find($product);

        if (! $product) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Product not found or has been permanently deleted.')]);

            return Inertia::location(route('inventory.products.index'));
        }

        $this->authorize('view', $product);

        $stockMovements = StockMovement::query()
            ->where('product_id', $product->id)
            ->with([
                'performedBy:id,name',
                'stockLot:id,reference_no,received_at',
                'requisition:id',
                'asset:id,tag_code',
                'accountablePosition:id,title,department_id',
                'accountablePosition.department:id,name',
            ])
            ->orderByDesc('performed_at')
            ->limit(20)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'movement_type' => $m->movement_type,
                'qty_delta' => $m->qty_delta,
                'qty_before' => $m->qty_before,
                'qty_after' => $m->qty_after,
                'performed_by' => $m->performedBy?->name ?? 'System',
                'performed_at' => $m->performed_at?->toIso8601String(),
                'notes' => $m->notes,
                'reference' => $m->requisition_id !== null
                    ? "Requisition #{$m->requisition_id}"
                    : ($m->stockLot?->reference_no !== null
                        ? "Receiving {$m->stockLot->reference_no}"
                        : ($m->asset?->tag_code !== null ? "Asset {$m->asset->tag_code}" : null)),
                'source' => $m->movement_type === 'receive'
                    ? 'Receiving'
                    : ($m->movement_type === 'issue'
                        ? 'Requisition issuance'
                        : ($m->movement_type === 'transfer' ? 'Asset handover' : 'Inventory update')),
                'accountable_position' => $m->accountablePosition
                    ? trim(
                        $m->accountablePosition->title
                        .($m->accountablePosition->relationLoaded('department') && $m->accountablePosition->department?->name
                            ? ", {$m->accountablePosition->department->name}"
                            : '')
                    )
                    : null,
            ])
            ->all();

        $forecast = null;

        if ($product->type === ProductType::Consumable) {
            $latestSnapshot = ForecastSnapshot::query()
                ->where('product_id', $product->id)
                ->latest('forecast_date')
                ->latest('generated_at')
                ->first();

            if (
                $latestSnapshot instanceof ForecastSnapshot
                && $latestSnapshot->forecast_date?->toDateString() === CarbonImmutable::now()->toDateString()
            ) {
                $forecast = $this->formatForecastSnapshot($latestSnapshot);
            } else {
                $forecast = $this->formatForecastResult(
                    $forecaster->forecast($product, $product->forecastProfile),
                    'live',
                );
            }
        }

        return Inertia::render('inventory/products/Show', [
            'product' => (new ProductResource($product))->resolve(),
            'forecast' => $forecast,
            'stockMovements' => $stockMovements,
            'can' => [
                'edit' => $request->user()?->can('update', $product) ?? false,
                'printLabel' => $request->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false,
            ],
            'isDeleted' => $product->trashed(),
        ]);
    }

    public function edit(Request $request, int $product): Response|SymfonyResponse
    {
        $product = Product::query()->withTrashed()
            ->with([
                'category:id,name',
                'origin:id,name',
                'stock:id,product_id,on_hand_qty',
            ])
            ->find($product);

        if (! $product) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Product not found or has been permanently deleted.')]);

            return Inertia::location(route('inventory.products.index'));
        }

        $this->authorize('update', $product);

        if ($product->trashed()) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => __('This product is in trash. Restore it before editing.')]);

            return Inertia::location(route('inventory.products.trash'));
        }

        return Inertia::render('inventory/products/Edit', [
            'product' => (new ProductResource($product))->resolve(),
            'categories' => $this->categoryOptions(),
            'origins' => $this->originOptions(),
            'can' => [
                'delete' => $request->user()?->can('delete', $product) ?? false,
            ],
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $oldValues = $product->toArray();
        $product->update($request->validated());

        AuditLogService::logUpdated($product, $oldValues);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('inventory.products.edit', $product);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        try {
            $product->deleted_by = $request->user()?->id;
            $product->deletion_reason = $request->string('deletion_reason')->trim()->toString() ?: null;
            $product->save();
            $product->delete();

            AuditLogService::logDeleted($product);
        } catch (QueryException $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to delete this product because it is referenced by other records.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product moved to trash.')]);

        return to_route('inventory.products.index');
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Product::class);

        $search = $request->string('search')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $deletedBy = $request->integer('deleted_by');

        $products = Product::query()
            ->onlyTrashed()
            ->with([
                'category:id,name',
                'origin:id,name',
                'stock:id,product_id,on_hand_qty',
                'deletedBy:id,name,email',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('deleted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('deleted_at', '<=', $dateTo))
            ->when($deletedBy, fn ($q) => $q->where('deleted_by', $deletedBy))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $deleters = Product::query()
            ->onlyTrashed()
            ->whereNotNull('deleted_by')
            ->distinct()
            ->join('users', 'products.deleted_by', '=', 'users.id')
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('inventory/products/Trash', [
            'products' => (new ProductCollection($products))->toArray($request),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'deleted_by' => $deletedBy,
            ],
            'deleters' => $deleters,
        ]);
    }

    public function restore(int $product): RedirectResponse
    {
        /** @var Product $product */
        $product = Product::query()->withTrashed()->findOrFail($product);

        $this->authorize('restore', $product);

        $product->restore();

        AuditLogService::logRestored($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product restored.')]);

        return back();
    }

    public function forceDelete(int $product): RedirectResponse
    {
        /** @var Product $product */
        $product = Product::query()->withTrashed()->findOrFail($product);

        $this->authorize('forceDelete', $product);

        try {
            AuditLogService::logForceDeleted($product);
            $product->forceDelete();

            Inertia::flash('toast', ['type' => 'success', 'message' => __('Product permanently deleted.')]);
        } catch (QueryException $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to permanently delete this product because it is referenced by other records.'),
            ]);
        }

        return back();
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back();
        }

        $restored = 0;
        foreach ($ids as $id) {
            /** @var Product|null $product */
            $product = Product::query()->withTrashed()->find($id);
            if ($product && $product->trashed()) {
                $this->authorize('restore', $product);
                $product->restore();
                AuditLogService::logRestored($product);
                $restored++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("{$restored} product(s) restored."),
        ]);

        return back();
    }

    public function bulkForceDelete(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back();
        }

        $deleted = 0;
        $errors = 0;

        foreach ($ids as $id) {
            /** @var Product|null $product */
            $product = Product::query()->withTrashed()->find($id);
            if ($product && $product->trashed()) {
                try {
                    $this->authorize('forceDelete', $product);
                    AuditLogService::logForceDeleted($product);
                    $product->forceDelete();
                    $deleted++;
                } catch (QueryException $e) {
                    report($e);
                    $errors++;
                }
            }
        }

        $message = "{$deleted} product(s) permanently deleted.";
        if ($errors > 0) {
            $message .= " {$errors} could not be deleted due to references.";
        }

        Inertia::flash('toast', [
            'type' => $errors > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkActivate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $products = Product::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $this->authorize('update', $product);

            if ($product->is_active) {
                $skipped++;

                continue;
            }

            $oldValues = $product->toArray();
            $product->update(['is_active' => true]);
            AuditLogService::logUpdated($product, $oldValues, "Product {$product->sku} activated.");
            $updated++;
        }

        $message = "{$updated} product(s) activated.";

        if ($skipped > 0) {
            $message .= " {$skipped} already active.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkDeactivate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $products = Product::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($products as $product) {
            $this->authorize('update', $product);

            if (! $product->is_active) {
                $skipped++;

                continue;
            }

            $oldValues = $product->toArray();
            $product->update(['is_active' => false]);
            AuditLogService::logUpdated($product, $oldValues, "Product {$product->sku} deactivated.");
            $updated++;
        }

        $message = "{$updated} product(s) deactivated.";

        if ($skipped > 0) {
            $message .= " {$skipped} already inactive.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkChangeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        $products = Product::query()
            ->whereIn('id', $validated['ids'])
            ->get();

        $updated = 0;
        $skipped = 0;
        $categoryId = (int) $validated['category_id'];
        $categoryName = Category::query()->whereKey($categoryId)->value('name') ?? __('Selected category');

        foreach ($products as $product) {
            $this->authorize('update', $product);

            if ((int) $product->category_id === $categoryId) {
                $skipped++;

                continue;
            }

            $oldValues = $product->toArray();
            $product->update(['category_id' => $categoryId]);
            AuditLogService::logUpdated($product, $oldValues, "Product {$product->sku} moved to category {$categoryName}.");
            $updated++;
        }

        $message = "{$updated} product(s) moved to {$categoryName}.";

        if ($skipped > 0) {
            $message .= " {$skipped} already matched that category.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return $this->rememberOptionList(
            Category::OPTIONS_CACHE_KEY,
            fn () => Category::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function originOptions(): array
    {
        return $this->rememberOptionList(
            Origin::OPTIONS_CACHE_KEY,
            fn () => Origin::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Origin $origin) => [
                    'id' => $origin->id,
                    'name' => $origin->name,
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @param  callable(): array<int, array{id: int, name: string}>  $resolver
     * @return array<int, array{id: int, name: string}>
     */
    private function rememberOptionList(string $cacheKey, callable $resolver): array
    {
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $this->normalizeOptionList($cached);
        }

        if ($cached instanceof Collection) {
            return $this->normalizeOptionList($cached->all());
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        /** @var array<int, array{id: int, name: string}> $fresh */
        $fresh = Cache::remember($cacheKey, now()->addDay(), $resolver);

        return $this->normalizeOptionList($fresh);
    }

    /**
     * @param  array<int, mixed>  $options
     * @return array<int, array{id: int, name: string}>
     */
    private function normalizeOptionList(array $options): array
    {
        return array_values(array_map(
            fn (mixed $option) => [
                'id' => (int) data_get($option, 'id'),
                'name' => (string) data_get($option, 'name'),
            ],
            $options,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatForecastSnapshot(ForecastSnapshot $snapshot): array
    {
        $rawData = is_array($snapshot->raw_data) ? $snapshot->raw_data : [];

        return [
            'method' => $snapshot->forecast_method,
            'source' => 'snapshot',
            'current_on_hand_qty' => $snapshot->current_on_hand_qty,
            'reorder_point_qty' => $snapshot->reorder_point_qty,
            'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
            'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout,
            'predicted_stockout_date' => $snapshot->predicted_stockout_date?->toDateString(),
            'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
            'confidence_score' => $snapshot->confidence_score !== null
                ? round($snapshot->confidence_score, 2)
                : null,
            'generated_at' => $snapshot->generated_at?->toISOString(),
            'historical_daily' => data_get($rawData, 'historical_daily', []),
            'forecast_daily' => data_get($rawData, 'forecast_daily', []),
            'history_window_days' => (int) data_get($rawData, 'summary.lookback_days', 0),
            'forecast_horizon_days' => (int) data_get($rawData, 'summary.forecast_horizon_days', 0),
            'lead_time_days' => (int) data_get($rawData, 'summary.lead_time_days', 0),
            'safety_stock_days' => (int) data_get($rawData, 'summary.safety_stock_days', 0),
            'has_sufficient_history' => (bool) data_get($rawData, 'summary.has_sufficient_history', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatForecastResult(ForecastResult $result, string $source = 'live'): array
    {
        return [
            'method' => $result->method,
            'source' => $source,
            'current_on_hand_qty' => $result->currentOnHandQty,
            'reorder_point_qty' => $result->reorderPointQty,
            'predicted_daily_consumption' => $result->predictedDailyConsumption,
            'predicted_days_until_stockout' => $result->predictedDaysUntilStockout,
            'predicted_stockout_date' => $result->predictedStockoutDate?->toDateString(),
            'recommended_reorder_qty' => $result->recommendedReorderQty,
            'confidence_score' => $result->confidenceScore,
            'generated_at' => $result->generatedAt->toISOString(),
            'historical_daily' => $result->historicalDailyConsumption,
            'forecast_daily' => $result->forecastDailyConsumption,
            'history_window_days' => $result->lookbackDays,
            'forecast_horizon_days' => $result->forecastHorizonDays,
            'lead_time_days' => $result->leadTimeDays,
            'safety_stock_days' => $result->safetyStockDays,
            'has_sufficient_history' => $result->hasSufficientHistory,
        ];
    }
}
