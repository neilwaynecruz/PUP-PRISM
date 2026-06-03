<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StockMovementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', StockMovement::class);

        $validated = Validator::make($request->all(), [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'movement_type' => ['nullable', 'string', 'in:receive,issue,adjustment,transfer'],
        ])->validate();

        $movements = StockMovement::query()
            ->with(['product:id,sku,name', 'stockLot:id,reference_no', 'asset:id,tag_code', 'performedBy:id,name,email'])
            ->when(isset($validated['from']), function ($query) use ($validated) {
                $query->whereDate('performed_at', '>=', $validated['from']);
            })
            ->when(isset($validated['to']), function ($query) use ($validated) {
                $query->whereDate('performed_at', '<=', $validated['to']);
            })
            ->when(isset($validated['product_id']), function ($query) use ($validated) {
                $query->where('product_id', $validated['product_id']);
            })
            ->when(isset($validated['movement_type']), function ($query) use ($validated) {
                $query->where('movement_type', $validated['movement_type']);
            })
            ->orderByDesc('performed_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return response()->json([
            'data' => StockMovementResource::collection($movements),
            'meta' => [
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
                'per_page' => $movements->perPage(),
                'total' => $movements->total(),
            ],
        ]);
    }
}
