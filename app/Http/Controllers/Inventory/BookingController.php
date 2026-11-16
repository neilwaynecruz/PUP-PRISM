<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BookingApproveRequest;
use App\Http\Requests\Inventory\BookingIndexRequest;
use App\Http\Requests\Inventory\BookingStoreRequest;
use App\Http\Requests\Inventory\BulkTrashActionRequest;
use App\Http\Resources\AssetResource;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Services\Inventory\BookingWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
    ) {}

    public function index(BookingIndexRequest $request): Response
    {
        $this->authorize('viewAny', Booking::class);

        $currentUser = Auth::user();
        $validated = $request->validated();
        $assetSearch = $request->assetSearchTerm();
        $search = $request->searchTerm();
        $status = $request->statusFilter();

        $assets = Asset::query()
            ->where('status', AssetStatus::Available)
            ->whereHas('product', fn ($q) => $q->where('type', ProductType::Asset)->where('is_active', true))
            ->with(['product:id,name', 'position:id,department_id,title', 'position.department:id,name'])
            ->when($assetSearch !== '', function ($query) use ($assetSearch) {
                $query->where(function ($query) use ($assetSearch) {
                    $query->whereLike('tag_code', "%{$assetSearch}%")
                        ->orWhereHas('product', function ($query) use ($assetSearch) {
                            $query->whereLike('name', "%{$assetSearch}%");
                        });
                });
            })
            ->orderBy('tag_code')
            ->limit(25)
            ->get([
                'id', 'product_id', 'position_id', 'tag_code', 'serial_number',
                'status', 'lifecycle_status', 'condition_status', 'acquisition_date',
                'acquisition_cost', 'warranty_expires_at', 'installed_at', 'maintenance_interval_days',
            ]);

        $bookingPayload = [
            'asset:id,product_id,position_id,tag_code',
            'asset.product:id,name',
            'requester:id,name,email,position_id',
            'requesterPosition:id,department_id,title',
            'approver:id,name,email,position_id',
            'approverPosition:id,department_id,title',
            'requesterPosition.department:id,name',
            'approverPosition.department:id,name',
        ];

        $calendarWindowStart = now()->toImmutable()->subDays(30);
        $calendarWindowEnd = now()->toImmutable()->addMonths(6);
        $canViewAllBookings = $currentUser instanceof User
            && $currentUser->hasAnyRole(['Admin', 'Property Custodian']);

        $calendarEvents = Booking::query()
            ->with($bookingPayload)
            ->when(! $canViewAllBookings, fn ($query) => $query->where('requester_id', $currentUser?->id))
            ->where('end_at', '>=', $calendarWindowStart)
            ->where('start_at', '<=', $calendarWindowEnd)
            ->orderBy('start_at')
            ->get();

        $approvalQueue = $canViewAllBookings
            ? Booking::query()
                ->with($bookingPayload)
                ->where('status', BookingStatus::Requested)
                ->orderBy('start_at')
                ->limit(15)
                ->get()
            : collect();

        $bookings = Booking::query()
            ->with([
                ...$bookingPayload,
            ])
            ->when(! $canViewAllBookings, fn ($query) => $query->where('requester_id', $currentUser?->id))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    if (ctype_digit($search)) {
                        $query->whereKey((int) $search);
                    }

                    $query->orWhereHas('asset', function ($query) use ($search) {
                        $query->whereLike('tag_code', "%{$search}%")
                            ->orWhereHas('product', fn ($query) => $query->whereLike('name', "%{$search}%"));
                    })
                        ->orWhereHas('requester', function ($query) use ($search) {
                            $query->whereLike('name', "%{$search}%")
                                ->orWhereLike('email', "%{$search}%");
                        });
                });
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when(isset($validated['date_from']), fn ($query) => $query->whereDate('start_at', '>=', $validated['date_from']))
            ->when(isset($validated['date_to']), fn ($query) => $query->whereDate('start_at', '<=', $validated['date_to']))
            ->when(isset($validated['requester_id']) && $canViewAllBookings, fn ($query) => $query->where('requester_id', $validated['requester_id']))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/bookings/Index', [
            'filters' => [
                'asset_search' => $assetSearch,
                'search' => $search,
                'status' => $status,
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'requester_id' => $validated['requester_id'] ?? null,
            ],
            'statusOptions' => collect(BookingStatus::cases())->map(fn (BookingStatus $case) => [
                'value' => $case->value,
                'label' => $case->value,
            ])->values()->all(),
            'assets' => AssetResource::collectionForInertia($assets, $request),
            'calendar_events' => BookingResource::collectionForInertia($calendarEvents, $request),
            'approval_queue' => BookingResource::collectionForInertia($approvalQueue, $request),
            'bookings' => (new BookingCollection($bookings))->toArray($request),
            'exportUrls' => [
                'csv' => route('inventory.reports.bookings', ['format' => 'csv'], absolute: false),
                'pdf' => route('inventory.reports.bookings', ['format' => 'pdf'], absolute: false),
            ],
            'can' => [
                'approve' => $currentUser instanceof User
                    ? $currentUser->hasAnyRole(['Admin', 'Property Custodian'])
                    : false,
            ],
            'timezone' => (string) config('app.timezone'),
        ]);
    }

    public function show(Request $request, int $booking): Response|SymfonyResponse
    {
        $currentUser = Auth::user();

        $bookingRecord = Booking::withTrashed()
            ->with([
                'asset:id,product_id,position_id,tag_code',
                'asset.product:id,name',
                'requester:id,name,email,position_id',
                'requesterPosition:id,department_id,title,code',
                'requesterPosition.department:id,name',
                'approver:id,name,email',
                'approverPosition:id,department_id,title,code',
                'approverPosition.department:id,name',
                'deletedBy:id,name,email',
            ])
            ->find($booking);

        if (! $bookingRecord instanceof Booking) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Booking not found or has been permanently deleted.')]);

            return Inertia::location(route('inventory.bookings.index'));
        }

        $this->authorize('view', $bookingRecord);

        return Inertia::render('inventory/bookings/Show', [
            'booking' => (new BookingResource($bookingRecord))->resolve($request),
            'can' => [
                'approve' => $bookingRecord->exists && ! $bookingRecord->trashed() && $currentUser instanceof User
                    ? $currentUser->can('approve', $bookingRecord)
                    : false,
                'reject' => $bookingRecord->exists && ! $bookingRecord->trashed() && $currentUser instanceof User
                    ? $currentUser->can('reject', $bookingRecord)
                    : false,
            ],
            'isDeleted' => $bookingRecord->trashed(),
        ]);
    }

    public function store(BookingStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $validated = $request->validated();

        try {
            $this->workflow->createBooking(
                actor: $actor,
                assetId: (int) $validated['asset_id'],
                startAt: $request->startAt(),
                endAt: $request->endAt(),
                purpose: $validated['purpose'] ?? null,
                ipAddress: $request->ip(),
            );
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking request submitted.')]);

        return back();
    }

    public function update(BookingApproveRequest $request, Booking $booking): RedirectResponse
    {
        $action = $request->string('action')->toString();

        $this->authorize($action === 'reject' ? 'reject' : 'approve', $booking);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        if ($action === 'approve') {
            try {
                $this->workflow->approveBooking($booking, $actor, $request->ip());
            } catch (ValidationException $exception) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first()
                        ?? __('This asset is already booked for part of the selected schedule. Choose different dates or select another asset.'),
                ]);

                return back()->withErrors($exception->errors());
            }
        } else {
            $rejected = $this->workflow->rejectBooking($booking, $actor, $request->ip());

            if (! $rejected instanceof Booking) {
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => __('This booking has already been processed.'),
                ]);

                return back();
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User && $actor->hasAnyRole(['Admin', 'Property Custodian']),
            403,
            __('You are not authorized to approve bookings.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        $outcome = $this->workflow->bulkApprove(
            ids: array_values(array_map('intval', $validated['ids'])),
            actor: $actor,
            ipAddress: $request->ip(),
        );

        $updated = $outcome['approved'];
        $conflicts = $outcome['conflicts'];
        $skipped = $outcome['skipped'];

        $message = trans_choice(
            '{1} :count booking approved.|[0,*] :count bookings approved.',
            $updated,
            ['count' => $updated],
        );

        if ($conflicts > 0) {
            $message .= " {$conflicts} skipped due to schedule conflicts.";
        }

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were no longer pending.";
        }

        Inertia::flash('toast', [
            'type' => $conflicts > 0 || $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function bulkReject(Request $request): RedirectResponse
    {
        $actor = $request->user();

        abort_unless(
            $actor instanceof User && $actor->hasAnyRole(['Admin', 'Property Custodian']),
            403,
            __('You are not authorized to reject bookings.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        $outcome = $this->workflow->bulkReject(
            ids: array_values(array_map('intval', $validated['ids'])),
            actor: $actor,
            ipAddress: $request->ip(),
        );

        $updated = $outcome['rejected'];
        $skipped = $outcome['skipped'];

        $message = trans_choice(
            '{1} :count booking rejected.|[0,*] :count bookings rejected.',
            $updated,
            ['count' => $updated],
        );

        if ($skipped > 0) {
            $message .= " {$skipped} skipped because they were no longer pending.";
        }

        Inertia::flash('toast', [
            'type' => $skipped > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $this->workflow->softDelete(
            booking: $booking,
            actor: $actor,
            deletionReason: $request->string('deletion_reason')->trim()->toString() ?: null,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking moved to trash.')]);

        return to_route('inventory.bookings.index');
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Booking::class);

        $search = $request->string('search')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();
        $deletedBy = $request->integer('deleted_by');

        $bookings = Booking::onlyTrashed()
            ->with([
                'asset:id,product_id,position_id,tag_code',
                'asset.product:id,name',
                'requester:id,name,email,position_id',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'deletedBy:id,name,email',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('asset', function ($q) use ($search) {
                    $q->whereLike('tag_code', "%{$search}%");
                });
            })
            ->when($dateFrom, fn ($q) => $q->whereDate('deleted_at', '>=', $dateFrom))
            ->when($dateTo, fn ($q) => $q->whereDate('deleted_at', '<=', $dateTo))
            ->when($deletedBy, fn ($q) => $q->where('deleted_by', $deletedBy))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        $deleters = Booking::onlyTrashed()
            ->whereNotNull('deleted_by')
            ->distinct()
            ->join('users', 'bookings.deleted_by', '=', 'users.id')
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return Inertia::render('inventory/bookings/Trash', [
            'bookings' => (new BookingCollection($bookings))->toArray($request),
            'filters' => [
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'deleted_by' => $deletedBy,
            ],
            'deleters' => $deleters,
        ]);
    }

    public function restore(int $booking): RedirectResponse
    {
        /** @var Booking $bookingRecord */
        $bookingRecord = Booking::withTrashed()->findOrFail($booking);

        $this->authorize('restore', $bookingRecord);

        $this->workflow->restore($bookingRecord);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking restored.')]);

        return back();
    }

    public function forceDelete(int $booking): RedirectResponse
    {
        /** @var Booking $bookingRecord */
        $bookingRecord = Booking::withTrashed()->findOrFail($booking);

        $this->authorize('forceDelete', $bookingRecord);

        $this->workflow->forceDelete($bookingRecord);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking permanently deleted.')]);

        return back();
    }

    public function bulkRestore(BulkTrashActionRequest $request): RedirectResponse
    {
        $this->authorize('trash', Booking::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $restored = $this->workflow->bulkRestore($request->ids(), $actor);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => trans_choice(
                '{1} :count booking restored.|[0,*] :count bookings restored.',
                $restored,
                ['count' => $restored],
            ),
        ]);

        return back();
    }

    public function bulkForceDelete(BulkTrashActionRequest $request): RedirectResponse
    {
        $this->authorize('trash', Booking::class);

        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $outcome = $this->workflow->bulkForceDelete($request->ids(), $actor);
        $deleted = $outcome['deleted'];
        $errors = $outcome['skipped'];

        $message = trans_choice(
            '{1} :count booking permanently deleted.|[0,*] :count bookings permanently deleted.',
            $deleted,
            ['count' => $deleted],
        );

        if ($errors > 0) {
            $message .= " {$errors} could not be deleted due to references.";
        }

        Inertia::flash('toast', [
            'type' => $errors > 0 ? 'warning' : 'success',
            'message' => __($message),
        ]);

        return back();
    }
}
