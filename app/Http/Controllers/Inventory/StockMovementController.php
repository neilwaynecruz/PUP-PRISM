<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StockMovementFilterRequest;
use App\Http\Resources\StockMovementCollection;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(StockMovementFilterRequest $request): Response
    {
        $type = $request->validated('type', '');
        $search = $request->validated('search', '');
        $dateFrom = $request->validated('date_from');
        $dateTo = $request->validated('date_to');
        $performedBy = $request->validated('performed_by');
        $sort = $request->validated('sort', 'performed_at');
        $direction = $request->validated('direction', 'desc');

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
            ->when($dateFrom !== null, fn ($query) => $query->whereDate('performed_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($query) => $query->whereDate('performed_at', '<=', $dateTo))
            ->when($performedBy !== null, fn ($query) => $query->where('performed_by', $performedBy))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->whereHas('product', function ($query) use ($search) {
                        $query->where('sku', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    })
                        ->orWhereHas('asset', function ($query) use ($search) {
                            $query->where('tag_code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('stockLot', function ($query) use ($search) {
                            $query->where('reference_no', 'like', "%{$search}%");
                        })
                        ->orWhereHas('performedBy', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        })
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(25)
            ->withQueryString();

        $users = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        $exportParams = array_filter([
            'type' => $type ?: null,
            'search' => $search ?: null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'performed_by' => $performedBy,
        ], fn ($v) => $v !== null && $v !== '');

        return Inertia::render('inventory/movements/Index', [
            'filters' => [
                'type' => $type,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'performed_by' => $performedBy,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'movements' => (new StockMovementCollection($movements))->toArray($request),
            'users' => $users,
            'exportUrls' => [
                'csv' => route('inventory.reports.movements', [
                    'format' => 'csv',
                    ...$exportParams,
                ], absolute: false),
                'pdf' => route('inventory.reports.movements', [
                    'format' => 'pdf',
                    ...$exportParams,
                ], absolute: false),
            ],
        ]);
    }
}
