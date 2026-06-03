<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);

        $search = $request->string('search')->trim()->toString();
        $type = $request->string('type')->trim()->toString();
        $categoryId = $request->integer('category_id') ?: null;
        $active = $request->has('active') ? $request->boolean('active') : null;

        $products = Product::query()
            ->with(['category:id,name', 'origin:id,name', 'stock:id,product_id,on_hand_qty'])
            ->withCount('assets')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when($type !== '', function ($query) use ($type) {
                $query->where('type', $type);
            })
            ->when($categoryId, function ($query) use ($categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->when($active !== null, function ($query) use ($active) {
                $query->where('is_active', $active);
            })
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return response()->json([
            'data' => ProductResource::collection($products),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);

        $product->load(['category:id,name', 'origin:id,name', 'stock:id,product_id,on_hand_qty']);
        $product->loadCount('assets');

        return response()->json([
            'data' => new ProductResource($product),
        ]);
    }
}
