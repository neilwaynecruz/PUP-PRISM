<?php

namespace App\Http\Controllers\Api;

use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRequisitionRequest;
use App\Http\Resources\RequisitionResource;
use App\Models\Requisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RequisitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Requisition::class);

        $status = $request->string('status')->trim()->toString();

        $requisitions = Requisition::query()
            ->with(['requester:id,name,email', 'approver:id,name,email', 'issuer:id,name,email', 'lines.product:id,sku,name'])
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        return response()->json([
            'data' => RequisitionResource::collection($requisitions),
            'meta' => [
                'current_page' => $requisitions->currentPage(),
                'last_page' => $requisitions->lastPage(),
                'per_page' => $requisitions->perPage(),
                'total' => $requisitions->total(),
            ],
        ]);
    }

    public function show(Requisition $requisition): JsonResponse
    {
        $this->authorize('view', $requisition);

        $requisition->load(['requester:id,name,email', 'approver:id,name,email', 'issuer:id,name,email', 'lines.product:id,sku,name']);

        return response()->json([
            'data' => new RequisitionResource($requisition),
        ]);
    }

    public function store(StoreRequisitionRequest $request): JsonResponse
    {
        $this->authorize('create', Requisition::class);

        $validated = $request->validated();

        $requisition = DB::transaction(function () use ($validated, $request) {
            $requisition = Requisition::create([
                'requester_id' => $request->user()->id,
                'requester_position_id' => $request->user()->position_id,
                'status' => RequisitionStatus::Submitted,
                'notes' => $validated['notes'] ?? null,
                'requested_ip_address' => $request->ip(),
            ]);

            foreach ($validated['lines'] as $line) {
                $requisition->lines()->create([
                    'product_id' => $line['product_id'],
                    'qty_requested' => $line['qty_requested'],
                ]);
            }

            return $requisition;
        });

        $requisition->load(['requester:id,name,email', 'lines.product:id,sku,name']);

        return response()->json([
            'data' => new RequisitionResource($requisition),
        ], 201);
    }
}
