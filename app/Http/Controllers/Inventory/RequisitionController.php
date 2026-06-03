<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RequisitionApproveRequest;
use App\Http\Requests\Inventory\RequisitionIssueRequest;
use App\Http\Requests\Inventory\RequisitionRejectRequest;
use App\Http\Requests\Inventory\RequisitionStoreRequest;
use App\Http\Resources\RequisitionCollection;
use App\Http\Resources\RequisitionResource;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}
    public function index(Request $request): Response
    {
        $requisitions = Requisition::query()
            ->with(['requester:id,name,email', 'requesterPosition:id,department_id,title', 'requesterPosition.department:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/requisitions/Index', [
            'requisitions' => (new RequisitionCollection($requisitions))->toArray($request),
            'exportUrls' => [
                'csv' => route('inventory.reports.requisitions', ['format' => 'csv'], absolute: false),
                'pdf' => route('inventory.reports.requisitions', ['format' => 'pdf'], absolute: false),
            ],
        ]);
    }

    public function show(Request $request, Requisition $requisition): Response
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
            'requisition' => (new RequisitionResource($requisition))->resolve($request),
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

        $requisition = DB::transaction(function () use ($request, $validated): Requisition {
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
                'status' => RequisitionStatus::Submitted,
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

            return $requisition;
        });

        $this->notifications->requisitionSubmitted($requisition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition submitted.')]);

        return back();
    }

    public function approve(RequisitionApproveRequest $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('approve', $requisition);

        $requisition->update([
            'status' => RequisitionStatus::Approved,
            'approver_id' => $request->user()->id,
            'approver_position_id' => $request->user()->position_id,
            'approved_ip_address' => $request->ip(),
            'approved_at' => CarbonImmutable::now(),
            'notes' => $request->validated()['notes'] ?? $requisition->notes,
        ]);

        $this->notifications->requisitionStatusChanged($requisition, 'approved');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition approved.')]);

        return back();
    }

    public function reject(RequisitionRejectRequest $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('reject', $requisition);

        $reason = trim($request->validated()['notes']);
        $existingNotes = trim((string) $requisition->notes);

        $requisition->update([
            'status' => RequisitionStatus::Rejected,
            'notes' => $existingNotes === ''
                ? "Rejection reason: {$reason}"
                : "{$existingNotes}\n\nRejection reason: {$reason}",
        ]);

        $this->notifications->requisitionStatusChanged($requisition, 'rejected');

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

        $requisition->refresh();
        $this->notifications->requisitionStatusChanged($requisition, 'issued');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition issued.')]);

        return back();
    }

    public function destroy(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $requisition->deleted_by = $request->user()?->id;
        $requisition->deletion_reason = $request->string('deletion_reason')->trim()->toString() ?: null;
        $requisition->save();
        $requisition->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition moved to trash.')]);

        return back();
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Requisition::class);

        $requisitions = Requisition::query()
            ->onlyTrashed()
            ->with([
                'requester:id,name,email',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'deletedBy:id,name,email',
            ])
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/requisitions/Trash', [
            'requisitions' => (new RequisitionCollection($requisitions))->toArray($request),
        ]);
    }

    public function restore(int $requisition): RedirectResponse
    {
        $requisition = Requisition::query()->withTrashed()->findOrFail($requisition);

        $this->authorize('restore', $requisition);

        $requisition->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition restored.')]);

        return back();
    }
}
