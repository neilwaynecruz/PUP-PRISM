<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\StockMovementCollection;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $type = $request->string('type')->trim()->toString();
        $search = $request->string('search')->trim()->toString();

        $movements = StockMovement::query()
            ->with([
                'product:id,sku,name',
                'stockLot:id,product_id,reference_no,received_at,expires_at',
                'asset:id,product_id,position_id,tag_code,status',
                'performedBy:id,name,email',
                'accountablePosition:id,department_id,title,code',
                'accountablePosition.department:id,name',
            ])
            ->when($type !== '', fn ($query) => $query->where('movement_type', $type))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('product', function ($query) use ($search) {
                        $query->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })->orWhereHas('asset', function ($query) use ($search) {
                        $query->where('tag_code', 'like', "%{$search}%");
                    });
                });
            })
            ->orderByDesc('performed_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('inventory/movements/Index', [
            'filters' => [
                'type' => $type,
                'search' => $search,
            ],
            'movements' => (new StockMovementCollection($movements))->toArray($request),
            'exportUrls' => [
                'csv' => route('inventory.reports.movements', [
                    'format' => 'csv',
                    ...$request->only(['type', 'search']),
                ], absolute: false),
                'pdf' => route('inventory.reports.movements', [
                    'format' => 'pdf',
                    ...$request->only(['type', 'search']),
                ], absolute: false),
            ],
        ]);
    }
}
