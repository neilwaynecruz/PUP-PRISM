<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RequisitionApproveRequest;
use App\Http\Requests\Inventory\RequisitionIssueRequest;
use App\Http\Requests\Inventory\RequisitionRejectRequest;
use App\Http\Requests\Inventory\RequisitionStoreRequest;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RequisitionController extends Controller
{
    public function index(): Response
    {
        $requisitions = Requisition::query()
            ->with(['requester:id,name,email', 'requesterPosition:id,department_id,title', 'requesterPosition.department:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/requisitions/Index', [
            'requisitions' => $requisitions->through(fn (Requisition $r) => [
                'id' => $r->id,
                'status' => $r->status,
                'created_at' => $r->created_at?->toIso8601String(),
                'requester' => [
                    'id' => $r->requester_id,
                    'name' => $r->requester?->name,
                ],
                'requester_position' => $r->requesterPosition ? [
                    'title' => $r->requesterPosition->title,
                    'department' => $r->requesterPosition->department?->name,
                ] : null,
            ]),
        ]);
    }

    public function show(Requisition $requisition): Response
    {
        $currentUser = Auth::user();

        $requisition->load([
            'requester:id,name,email',
            'requesterPosition:id,department_id,title,code',
            'requesterPosition.department:id,name',
            'approver:id,name,email',
            'approverPosition:id,department_id,title,code',
            'approverPosition.department:id,name',
            'issuer:id,name,email',
            'issuedPosition:id,department_id,title,code',
            'issuedPosition.department:id,name',
            'lines.product:id,sku,name,type',
        ]);

        return Inertia::render('inventory/requisitions/Show', [
            'requisition' => [
                'id' => $requisition->id,
                'status' => $requisition->status,
                'notes' => $requisition->notes,
                'approved_at' => $requisition->approved_at?->toIso8601String(),
                'issued_at' => $requisition->issued_at?->toIso8601String(),
                'requested_ip_address' => $requisition->requested_ip_address,
                'approved_ip_address' => $requisition->approved_ip_address,
                'issued_ip_address' => $requisition->issued_ip_address,
                'requester' => $requisition->requester?->only(['id', 'name']),
                'requester_position' => $requisition->requesterPosition ? [
                    'title' => $requisition->requesterPosition->title,
                    'code' => $requisition->requesterPosition->code,
                    'department' => $requisition->requesterPosition->department?->name,
                ] : null,
                'approver' => $requisition->approver?->only(['id', 'name']),
                'approver_position' => $requisition->approverPosition ? [
                    'title' => $requisition->approverPosition->title,
                    'code' => $requisition->approverPosition->code,
                    'department' => $requisition->approverPosition->department?->name,
                ] : null,
                'issuer' => $requisition->issuer?->only(['id', 'name']),
                'issued_position' => $requisition->issuedPosition ? [
                    'title' => $requisition->issuedPosition->title,
                    'code' => $requisition->issuedPosition->code,
                    'department' => $requisition->issuedPosition->department?->name,
                ] : null,
                'lines' => $requisition->lines->map(fn (RequisitionLine $l) => [
                    'id' => $l->id,
                    'sku' => $l->product?->sku,
                    'name' => $l->product?->name,
                    'type' => $l->product?->type,
                    'qty_requested' => $l->qty_requested,
                    'qty_issued' => $l->qty_issued,
                ]),
            ],
            'can' => [
                'approve' => $requisition->exists && $currentUser instanceof User
                    ? $currentUser->can('approve', $requisition)
                    : false,
                'reject' => $requisition->exists && $currentUser instanceof User
                    ? $currentUser->can('reject', $requisition)
                    : false,
                'issue' => $requisition->exists && $currentUser instanceof User
                    ? $currentUser->can('issue', $requisition)
                    : false,
            ],
        ]);
    }

    public function store(RequisitionStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($request, $validated): void {
            $requisition = Requisition::create([
                'requester_id' => $request->user()->id,
                'requester_position_id' => $request->user()->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => $request->ip(),
                'approved_ip_address' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => 'Submitted',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['lines'] as $line) {
                $product = Product::query()->where('sku', $line['sku'])->firstOrFail();

                RequisitionLine::create([
                    'requisition_id' => $requisition->id,
                    'product_id' => $product->id,
                    'qty_requested' => (int) $line['qty_requested'],
                    'qty_issued' => 0,
                ]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition submitted.')]);

        return back();
    }

    public function approve(RequisitionApproveRequest $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('approve', $requisition);

        $requisition->update([
            'status' => 'Approved',
            'approver_id' => $request->user()->id,
            'approver_position_id' => $request->user()->position_id,
            'approved_ip_address' => $request->ip(),
            'approved_at' => CarbonImmutable::now(),
            'notes' => $request->validated()['notes'] ?? $requisition->notes,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition approved.')]);

        return back();
    }

    public function reject(RequisitionRejectRequest $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('reject', $requisition);

        $reason = trim($request->validated()['notes']);
        $existingNotes = trim((string) $requisition->notes);

        $requisition->update([
            'status' => 'Rejected',
            'notes' => $existingNotes === ''
                ? "Rejection reason: {$reason}"
                : "{$existingNotes}\n\nRejection reason: {$reason}",
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition rejected.')]);

        return back();
    }

    public function issue(
        RequisitionIssueRequest $request,
        Requisition $requisition,
        InventoryService $inventory,
    ): RedirectResponse {
        $this->authorize('issue', $requisition);

        $inventory->issueRequisition(
            user: $request->user(),
            requisition: $requisition,
            notes: $request->validated()['notes'] ?? null,
            ipAddress: $request->ip(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition issued.')]);

        return back();
    }
}
