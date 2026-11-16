<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\PaginatesApiResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRequisitionRequest;
use App\Http\Resources\RequisitionResource;
use App\Models\Requisition;
use App\Services\Inventory\RequisitionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RequisitionController extends Controller
{
    use PaginatesApiResources;

    public function __construct(
        private readonly RequisitionWorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Requisition::class);

        $user = $request->user();
        $status = $request->string('status')->trim()->toString();

        $requisitions = Requisition::query()
            ->with(['requester:id,name,email', 'approver:id,name,email', 'issuer:id,name,email', 'lines.product:id,sku,name'])
            ->when(! $user?->hasAnyRole(['Admin', 'Supply Head']), function ($query) use ($user) {
                $query->where('requester_id', $user?->id);
            })
            ->when($status !== '', function ($query) use ($status) {
                $query->where('status', $status);
            })
            ->orderByDesc('created_at')
            ->paginate($this->perPage($request))
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

    public function show(Request $request, Requisition $requisition): JsonResponse
    {
        $this->authorize('view', $requisition);

        if (! $request->user()?->hasAnyRole(['Admin', 'Supply Head']) && $requisition->requester_id !== $request->user()?->id) {
            abort(403);
        }

        $requisition->load(['requester:id,name,email', 'approver:id,name,email', 'issuer:id,name,email', 'lines.product:id,sku,name']);

        return response()->json([
            'data' => new RequisitionResource($requisition),
        ]);
    }

    public function store(StoreRequisitionRequest $request): JsonResponse
    {
        $this->authorize('create', Requisition::class);

        $validated = $request->validated();
        $user = $request->user();
        $idempotencyKey = isset($validated['idempotency_key']) ? trim((string) $validated['idempotency_key']) : null;

        if ($idempotencyKey === null || $idempotencyKey === '') {
            $requisition = $this->workflow->submitFromProductIds(
                actor: $user,
                linesInput: $validated['lines'],
                notes: $validated['notes'] ?? null,
                ipAddress: $request->ip(),
            );

            return response()->json([
                'data' => new RequisitionResource($requisition),
            ], 201);
        }

        $keyHash = hash('sha256', $idempotencyKey);
        $lockKey = "api:requisitions:idempotency:lock:{$user->id}:{$keyHash}";
        $cacheKey = "api:requisitions:idempotency:record:{$user->id}:{$keyHash}";
        $payloadHash = $this->fingerprintPayload($validated);

        return Cache::lock($lockKey, 10)->block(5, function () use ($cacheKey, $payloadHash, $validated, $request, $user): JsonResponse {
            $existingRecord = Cache::get($cacheKey);

            if (is_array($existingRecord) && isset($existingRecord['requisition_id'], $existingRecord['payload_hash'])) {
                if ($existingRecord['payload_hash'] !== $payloadHash) {
                    return response()->json([
                        'message' => __('This idempotency key has already been used with a different requisition payload.'),
                    ], 409);
                }

                $existingRequisition = Requisition::query()
                    ->with(['requester:id,name,email', 'lines.product:id,sku,name'])
                    ->find((int) $existingRecord['requisition_id']);

                if ($existingRequisition instanceof Requisition) {
                    return response()->json([
                        'data' => new RequisitionResource($existingRequisition),
                    ], 200)->header('X-Idempotent-Replay', 'true');
                }
            }

            $requisition = $this->workflow->submitFromProductIds(
                actor: $user,
                linesInput: $validated['lines'],
                notes: $validated['notes'] ?? null,
                ipAddress: $request->ip(),
            );

            Cache::put($cacheKey, [
                'requisition_id' => $requisition->id,
                'payload_hash' => $payloadHash,
            ], now()->addHours(24));

            return response()->json([
                'data' => new RequisitionResource($requisition),
            ], 201);
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fingerprintPayload(array $validated): string
    {
        $normalizedLines = collect($validated['lines'])
            ->map(fn (array $line): array => [
                'product_id' => (int) $line['product_id'],
                'qty_requested' => (int) $line['qty_requested'],
            ])
            ->sortBy('product_id')
            ->values()
            ->all();

        return hash('sha256', json_encode([
            'notes' => $validated['notes'] ?? null,
            'lines' => $normalizedLines,
        ], JSON_THROW_ON_ERROR));
    }
}
