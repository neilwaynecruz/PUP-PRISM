<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\RequisitionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BulkTrashActionRequest;
use App\Http\Requests\Inventory\RequisitionApproveRequest;
use App\Http\Requests\Inventory\RequisitionIndexRequest;
use App\Http\Requests\Inventory\RequisitionIssueRequest;
use App\Http\Requests\Inventory\RequisitionRejectRequest;
use App\Http\Requests\Inventory\RequisitionStoreRequest;
use App\Http\Resources\RequisitionCollection;
use App\Http\Resources\RequisitionResource;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\User;
use App\Services\Inventory\RequisitionTemplateService;
use App\Services\Inventory\RequisitionWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class RequisitionController extends Controller
{
    public function __construct(
        private readonly RequisitionWorkflowService $workflow,
    ) {}

    public function index(
        RequisitionIndexRequest $request,
        RequisitionTemplateService $templates,
    ): Response {
        $this->authorize('viewAny', Requisition::class);

        $validated = $request->validated();
        $search = $request->searchTerm();
        $status = $request->statusFilter();

        $requisitions = Requisition::query()
            ->with([
                'requester:id,name,email',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'lines:id,requisition_id,product_id,qty_requested,qty_issued',
                'lines.product:id,sku,name,type,variant,unit_of_measurement_id',
                'lines.product.unitOfMeasurement:id,name,abbreviation',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $searchId = ltrim($search, '#');

                    if (ctype_digit($searchId)) {
                        $query->whereKey((int) $searchId);
                    }

                    $query->orWhereHas('requester', function ($query) use ($search) {
                        $query->whereLike('name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    })->orWhereHas('lines.product', function ($query) use ($search) {
                        $query->whereLike('name', "%{$search}%")
                            ->orWhereLike('sku', "%{$search}%");
                    });
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('created_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('created_at', '<=', $validated['date_to']))
            ->when(isset($validated['requester_id']), fn ($query) => $query->where('requester_id', $validated['requester_id']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/requisitions/Index', [
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'requester_id' => $validated['requester_id'] ?? null,
            ],
            'statusOptions' => collect(RequisitionStatus::cases())->map(fn (RequisitionStatus $case) => [
                'value' => $case->value,
                'label' => $case->value,
            ])->values()->all(),
            'requisitions' => (new RequisitionCollection($requisitions))->toArray($request),
            'statusCounts' => Requisition::query()
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->map(fn ($count): int => (int) $count)
                ->all(),
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
                'lines.product:id,sku,name,type,variant,unit_of_measurement_id',
                'lines.product.unitOfMeasurement:id,name,abbreviation',
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
        $this->authorize('create', Requisition::class);

        $validated = $request->validated();
        $submissionKey = sprintf(
            'requisition-submission:%d:%s',
            $request->user()->id,
            $validated['submission_token'],
        );

        if (! Cache::add($submissionKey, true, now()->addMinutes(10))) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This requisition was already submitted. No duplicate was created.'),
            ]);

            return back();
        }

        try {
            $this->workflow->submitFromSkus(
                actor: $request->user(),
                linesInput: $validated['lines'],
                notes: $validated['notes'] ?? null,
                ipAddress: $request->ip(),
            );
        } catch (Throwable $exception) {
            Cache::forget($submissionKey);

            throw $exception;
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition submitted.')]);

        return back();
    }

    public function approve(RequisitionApproveRequest $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Supply Head']),
            403,
            __('You are not authorized to approve requisitions.'),
        );

        $result = $this->workflow->approve(
            requisition: $requisition,
            actor: $request->user(),
            notes: $request->validated()['notes'] ?? null,
            ipAddress: $request->ip(),
        );

        if (! $result['approved']) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This requisition cannot be approved because it is in :status status.', [
                    'status' => $result['requisition']->status->value,
                ]),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition approved.')]);

        return back();
    }

    public function reject(RequisitionRejectRequest $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Supply Head']),
            403,
            __('You are not authorized to reject requisitions.'),
        );

        $result = $this->workflow->reject(
            requisition: $requisition,
            actor: $request->user(),
            reason: $request->validated()['notes'],
        );

        if (! $result['rejected']) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This requisition cannot be rejected because it is in :status status.', [
                    'status' => $result['requisition']->status->value,
                ]),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition rejected.')]);

        return back();
    }

    public function issue(RequisitionIssueRequest $request, Requisition $requisition): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Supply Head']),
            403,
            __('You are not authorized to issue requisitions.'),
        );

        if (! in_array($requisition->status, [RequisitionStatus::Approved, RequisitionStatus::PartiallyIssued, RequisitionStatus::Backordered], true)) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => __('This requisition cannot be issued because it is in :status status.', [
                    'status' => $requisition->status->value,
                ]),
            ]);

            return back();
        }

        try {
            $requisition = $this->workflow->issue(
                requisition: $requisition,
                actor: $request->user(),
                linePayloads: $request->issueLines(),
                markAsBackordered: $request->shouldMarkAsBackordered(),
                notes: $request->validated()['notes'] ?? null,
                ipAddress: $request->ip(),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors([
                'lines' => $exception->getMessage(),
            ]);
        }

        $message = match ($requisition->status) {
            RequisitionStatus::Issued => __('Requisition issued.'),
            RequisitionStatus::Backordered => __('Requisition updated and remaining quantities are backordered.'),
            default => __('Requisition partially fulfilled.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Supply Head']),
            403,
            __('You are not authorized to approve requisitions.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:requisitions,id'],
        ]);

        $outcome = $this->workflow->bulkApprove(
            ids: array_values(array_map('intval', $validated['ids'])),
            actor: $request->user(),
            ipAddress: $request->ip(),
        );

        $updated = $outcome['approved'];
        $skipped = $outcome['skipped'];

        $message = trans_choice(
            '{1} :count requisition approved.|[0,*] :count requisitions approved.',
            $updated,
            ['count' => $updated],
        );

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were already processed or ineligible for approval.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 && $updated === 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkIssue(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Supply Head']),
            403,
            __('You are not authorized to issue requisitions.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:requisitions,id'],
        ]);

        $outcome = $this->workflow->bulkIssue(
            ids: array_values(array_map('intval', $validated['ids'])),
            actor: $request->user(),
            ipAddress: $request->ip(),
        );

        $updated = $outcome['issued'];
        $skipped = $outcome['skipped'];
        $failed = $outcome['failed'];

        $message = trans_choice(
            '{1} :count requisition issued.|[0,*] :count requisitions issued.',
            $updated,
            ['count' => $updated],
        );

        if ($failed > 0) {
            $message .= " {$failed} could not be issued due to stock or workflow constraints.";
        }

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were not in an approved or issuable status.";
        }

        Inertia::flash('toast', [
            'type' => ($failed > 0 || ($skipped > 0 && $updated === 0)) ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function destroy(Request $request, Requisition $requisition): RedirectResponse
    {
        $this->authorize('delete', $requisition);

        $this->workflow->softDelete(
            requisition: $requisition,
            actor: $request->user(),
            deletionReason: $request->string('deletion_reason')->trim()->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition moved to trash.')]);

        return to_route('inventory.requisitions.index');
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Requisition::class);

        $rawDateFrom = $request->string('date_from')->trim()->toString();
        $rawDateTo = $request->string('date_to')->trim()->toString();
        $rawDeletedBy = trim((string) $request->input('deleted_by', ''));

        $validatedFilters = Validator::make([
            'date_from' => $rawDateFrom === '' ? null : $rawDateFrom,
            'date_to' => $rawDateTo === '' ? null : $rawDateTo,
            'deleted_by' => $rawDeletedBy === '' ? null : $rawDeletedBy,
        ], [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'deleted_by' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $search = $request->string('search')->trim()->toString();
        $dateFrom = isset($validatedFilters['date_from'])
            ? CarbonImmutable::parse((string) $validatedFilters['date_from'])->toDateString()
            : '';
        $dateTo = isset($validatedFilters['date_to'])
            ? CarbonImmutable::parse((string) $validatedFilters['date_to'])->toDateString()
            : '';
        $deletedBy = isset($validatedFilters['deleted_by']) ? (int) $validatedFilters['deleted_by'] : 0;

        $requisitions = Requisition::query()
            ->onlyTrashed()
            ->with([
                'requester:id,name,email',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'deletedBy:id,name,email',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $normalizedIdSearch = strtolower(ltrim($search, '#'));

                $query->where(function ($query) use ($search, $normalizedIdSearch) {
                    $query->whereHas('requester', function ($q) use ($search) {
                        $q->whereLike('name', "%{$search}%");
                    })->orWhereRaw('LOWER(CAST(id AS TEXT)) LIKE ?', ["%{$normalizedIdSearch}%"]);
                });
            })
            ->when($dateFrom !== '', fn ($q) => $q->whereDate('deleted_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($q) => $q->whereDate('deleted_at', '<=', $dateTo))
            ->when($deletedBy > 0, fn ($q) => $q->where('deleted_by', $deletedBy))
            ->orderByDesc('deleted_at')
            ->orderByDesc('id')
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

        $this->workflow->restore($requisition);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition restored.')]);

        return back();
    }

    public function forceDelete(int $requisition): RedirectResponse
    {
        /** @var Requisition $requisition */
        $requisition = Requisition::query()->withTrashed()->findOrFail($requisition);

        $this->authorize('forceDelete', $requisition);

        $status = $this->workflow->forceDelete($requisition);

        if ($status === 'has_history') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to permanently delete this requisition because it has issuance history.'),
            ]);

            return back();
        }

        if ($status === 'referenced') {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to permanently delete this requisition because it is referenced by other records.'),
            ]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Requisition permanently deleted.')]);

        return back();
    }

    public function bulkRestore(BulkTrashActionRequest $request): RedirectResponse
    {
        $this->authorize('trash', Requisition::class);

        $restored = $this->workflow->bulkRestore($request->ids(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} :count requisition restored.|[0,*] :count requisitions restored.',
                $restored,
                ['count' => $restored],
            ),
        ]);

        return back();
    }

    public function bulkForceDelete(BulkTrashActionRequest $request): RedirectResponse
    {
        $this->authorize('trash', Requisition::class);

        $outcome = $this->workflow->bulkForceDelete($request->ids(), $request->user());
        $deleted = $outcome['deleted'];
        $skipped = $outcome['skipped'];

        $message = trans_choice(
            '{1} :count requisition permanently deleted.|[0,*] :count requisitions permanently deleted.',
            $deleted,
            ['count' => $deleted],
        );

        if ($skipped > 0) {
            $message .= " {$skipped} preserved due to issuance history or references.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }
}
