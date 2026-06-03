<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ProductStoreRequest;
use App\Http\Requests\Inventory\ProductUpdateRequest;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(Request $request): Response
    {
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
            'can' => [
                'create' => $request->user()?->can('create', Product::class) ?? false,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('inventory/products/Create', [
            'categories' => $this->categoryOptions(),
            'origins' => $this->originOptions(),
        ]);
    }

    public function store(ProductStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
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
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return to_route('inventory.products.index');
    }

    public function show(Product $product): Response
    {
        $product->load([
            'category:id,name',
            'origin:id,name',
            'stock:id,product_id,on_hand_qty',
        ])->loadCount('assets');

        return Inertia::render('inventory/products/Show', [
            'product' => (new ProductResource($product))->resolve(),
        ]);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('inventory/products/Edit', [
            'product' => (new ProductResource($product))->resolve(),
            'categories' => $this->categoryOptions(),
            'origins' => $this->originOptions(),
        ]);
    }

    public function update(ProductUpdateRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product updated.')]);

        return to_route('inventory.products.edit', $product);
    }

    public function destroy(Product $product): RedirectResponse
    {
        try {
            $product->delete();
        } catch (QueryException $e) {
            report($e);

            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to delete this product because it is referenced by other records.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return to_route('inventory.products.index');
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
}
