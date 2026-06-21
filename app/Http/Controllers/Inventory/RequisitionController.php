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
use App\Models\RequisitionTemplate;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\RequisitionTemplateService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(
        Request $request,
        RequisitionTemplateService $templates,
    ): Response {
        $this->authorize('viewAny', Requisition::class);

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
            'templates' => $request->user() instanceof User
                ? $templates->templatesForUser($request->user())
                : [],
            'can' => [
                'bulkApprove' => $request->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false,
                'bulkIssue' => $request->user()?->hasAnyRole(['Admin', 'Supply Head']) ?? false,
                'manageTemplates' => $request->user()?->can('create', RequisitionTemplate::class) ?? false,
            ],
        ]);
    }

    public function show(Request $request, int $requisition): Response|SymfonyResponse
    {
        $currentUser = Auth::user();

        $requisition = Requisition::query()->withTrashed()
            ->with([
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
                'deletedBy:id,name,email',
            ])
            ->find($requisition);

        if (! $requisition) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Requisition not found or has been permanently deleted.')]);

            return Inertia::location(route('inventory.requisitions.index'));
        }

        $this->authorize('view', $requisition);

        return Inertia::render('inventory/requisitions/Show', [
            'requisition' => (new RequisitionResource($requisition))->resolve($request),
            'can' => [
                'approve' => $requisition->exists && ! $requisition->trashed() && $currentUser instanceof User
                    ? $currentUser->can('approve', $requisition)
                    : false,
                'reject' => $requisition->exists && ! $requisition->trashed() && $currentUser instanceof User
                    ? $currentUser->can('reject', $requisition)
                    : false,
                'issue' => $requisition->exists && ! $requisition->trashed() && $currentUser instanceof User
                    ? $currentUser->can('issue', $requisition)
                    : false,
            ],
            'isDeleted' => $requisition->trashed(),
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

        AuditLogService::logCreated($requisition, "Requisition #{$requisition->id} submitted.");

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

        AuditLogService::logCustom('approve', "Requisition #{$requisition->id} approved.", $requisition);

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

        AuditLogService::logCustom('reject', "Requisition #{$requisition->id} rejected.", $requisition);

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
        AuditLogService::logCustom('issue', "Requisition #{$requisition->id} issued.", $requisition);
        $this->notifications->requisitionStatusChanged($requisition, 'issued');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition issued.')]);

        return back();
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $requisitions = Requisition::query()
            ->whereIn('id', $validated['ids'])
            ->orderBy('created_at')
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($requisitions as $requisition) {
            $this->authorize('approve', $requisition);

            if ($requisition->status === RequisitionStatus::Submitted) {
                $requisition->update([
                    'status' => RequisitionStatus::Approved,
                    'approver_id' => $request->user()->id,
                    'approver_position_id' => $request->user()->position_id,
                    'approved_ip_address' => $request->ip(),
                    'approved_at' => CarbonImmutable::now(),
                ]);
                AuditLogService::logCustom('approve', "Requisition #{$requisition->id} approved.", $requisition);
                $this->notifications->requisitionStatusChanged($requisition, 'approved');
                $updated++;
            } else {
                $skipped++;
            }
        }

        $message = "{$updated} requisition(s) approved.";

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were no longer submitted.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkIssue(
        Request $request,
        InventoryService $inventory,
    ): RedirectResponse {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $requisitions = Requisition::query()
            ->whereIn('id', $validated['ids'])
            ->orderBy('created_at')
            ->get();

        $updated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($requisitions as $requisition) {
            $this->authorize('issue', $requisition);

            if ($requisition->status === RequisitionStatus::Approved) {
                try {
                    $inventory->issueRequisition(
                        user: $request->user(),
                        requisition: $requisition,
                        notes: null,
                        ipAddress: $request->ip(),
                    );
                    AuditLogService::logCustom('issue', "Requisition #{$requisition->id} issued.", $requisition->fresh());
                    $this->notifications->requisitionStatusChanged($requisition, 'issued');
                    $updated++;
                } catch (\RuntimeException $exception) {
                    report($exception);
                    $failed++;
                }
            } else {
                $skipped++;
            }
        }

        $message = "{$updated} requisition(s) issued.";

        if ($failed > 0) {
            $message .= " {$failed} could not be issued due to stock or workflow constraints.";
        }

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were no longer approved.";
        }

        Inertia::flash('toast', [
            'type' => $failed > 0 || $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function destroy(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $requisition->deleted_by = $request->user()?->id;
        $requisition->deletion_reason = $request->string('deletion_reason')->trim()->toString() ?: null;
        $requisition->save();
        $requisition->delete();

        AuditLogService::logDeleted($requisition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition moved to trash.')]);

        return to_route('inventory.requisitions.index');
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Requisition::class);

        $search = $request->string('search')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $deletedBy = $request->integer('deleted_by');

        $requisitions = Requisition::query()
            ->onlyTrashed()
            ->with([
                'requester:id,name,email',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'deletedBy:id,name,email',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('requester', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                })->orWhere('id', 'like', "%{$search}%");
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('deleted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('deleted_at', '<=', $dateTo))
            ->when($deletedBy, fn ($q) => $q->where('deleted_by', $deletedBy))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $deleters = Requisition::query()
            ->onlyTrashed()
            ->whereNotNull('deleted_by')
            ->distinct()
            ->join('users', 'requisitions.deleted_by', '=', 'users.id')
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('inventory/requisitions/Trash', [
            'requisitions' => (new RequisitionCollection($requisitions))->toArray($request),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'deleted_by' => $deletedBy,
            ],
            'deleters' => $deleters,
        ]);
    }

    public function restore(int $requisition): RedirectResponse
    {
        /** @var Requisition $requisition */
        $requisition = Requisition::query()->withTrashed()->findOrFail($requisition);

        $this->authorize('restore', $requisition);

        $requisition->restore();

        AuditLogService::logRestored($requisition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition restored.')]);

        return back();
    }

    public function forceDelete(int $requisition): RedirectResponse
    {
        /** @var Requisition $requisition */
        $requisition = Requisition::query()->withTrashed()->findOrFail($requisition);

        $this->authorize('forceDelete', $requisition);

        AuditLogService::logForceDeleted($requisition);
        $requisition->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition permanently deleted.')]);

        return back();
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back();
        }

        $restored = 0;
        foreach ($ids as $id) {
            /** @var Requisition|null $requisition */
            $requisition = Requisition::query()->withTrashed()->find($id);
            if ($requisition && $requisition->trashed()) {
                $this->authorize('restore', $requisition);
                $requisition->restore();
                AuditLogService::logRestored($requisition);
                $restored++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("{$restored} requisition(s) restored."),
        ]);

        return back();
    }

    public function bulkForceDelete(Request $request): RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back();
        }

        $deleted = 0;
        foreach ($ids as $id) {
            /** @var Requisition|null $requisition */
            $requisition = Requisition::query()->withTrashed()->find($id);
            if ($requisition && $requisition->trashed()) {
                $this->authorize('forceDelete', $requisition);
                AuditLogService::logForceDeleted($requisition);
                $requisition->forceDelete();
                $deleted++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("{$deleted} requisition(s) permanently deleted."),
        ]);

        return back();
    }
}
