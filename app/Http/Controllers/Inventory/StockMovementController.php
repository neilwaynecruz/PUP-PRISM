<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
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
            'movements' => $movements->through(fn (StockMovement $movement) => [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'qty_delta' => $movement->qty_delta,
                'performed_at' => $movement->performed_at,
                'ip_address' => $movement->ip_address,
                'notes' => $movement->notes,
                'product' => $movement->product ? [
                    'id' => $movement->product->id,
                    'sku' => $movement->product->sku,
                    'name' => $movement->product->name,
                ] : null,
                'stock_lot' => $movement->stockLot ? [
                    'id' => $movement->stockLot->id,
                    'reference_no' => $movement->stockLot->reference_no,
                    'received_at' => $movement->stockLot->received_at,
                    'expires_at' => $movement->stockLot->expires_at,
                ] : null,
                'asset' => $movement->asset ? [
                    'id' => $movement->asset->id,
                    'tag_code' => $movement->asset->tag_code,
                    'status' => $movement->asset->status,
                ] : null,
                'performed_by' => [
                    'id' => $movement->performedBy->id,
                    'name' => $movement->performedBy->name,
                    'email' => $movement->performedBy->email,
                ],
                'accountable_position' => $movement->accountablePosition ? [
                    'title' => $movement->accountablePosition->title,
                    'code' => $movement->accountablePosition->code,
                    'department' => $movement->accountablePosition->department?->name,
                ] : null,
            ]),
        ]);
    }
}
