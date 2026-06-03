<?php

namespace App\Http\Controllers\Api;

use App\Enums\AssetStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $status = $request->string('status')->trim()->toString();
        $productId = $request->integer('product_id') ?: null;
        $search = $request->string('search')->trim()->toString();

        $assets = Asset::query()
            ->with(['product:id,name', 'position:id,title,department_id'])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->when($productId, function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where('tag_code', 'like', "%{$search}%");
            })
            ->orderBy('tag_code')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return response()->json([
            'data' => AssetResource::collection($assets),
            'meta' => [
                'current_page' => $assets->currentPage(),
                'last_page' => $assets->lastPage(),
                'per_page' => $assets->perPage(),
                'total' => $assets->total(),
            ],
        ]);
    }

    public function show(Asset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        $asset->load(['product:id,name', 'position:id,title,department_id']);

        return response()->json([
            'data' => new AssetResource($asset),
        ]);
    }
}
