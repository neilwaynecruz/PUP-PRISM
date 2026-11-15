<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BookingApproveRequest;
use App\Http\Requests\Inventory\BookingIndexRequest;
use App\Http\Requests\Inventory\BookingStoreRequest;
use App\Http\Resources\AssetResource;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\Inventory\AssetIntegrityService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BookingController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly AssetIntegrityService $assetIntegrity,
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
            ->get(['id', 'product_id', 'position_id', 'tag_code', 'status']);

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

        $calendarWindowStart = CarbonImmutable::now()->subDays(30);
        $calendarWindowEnd = CarbonImmutable::now()->addMonths(6);
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

        $booking = Booking::query()->withTrashed()
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

        if (! $booking) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Booking not found or has been permanently deleted.')]);

            return Inertia::location(route('inventory.bookings.index'));
        }

        $this->authorize('view', $booking);

        return Inertia::render('inventory/bookings/Show', [
            'booking' => (new BookingResource($booking))->resolve($request),
            'can' => [
                'approve' => $booking->exists && ! $booking->trashed() && $currentUser instanceof User
                    ? $currentUser->can('approve', $booking)
                    : false,
                'reject' => $booking->exists && ! $booking->trashed() && $currentUser instanceof User
                    ? $currentUser->can('reject', $booking)
                    : false,
            ],
            'isDeleted' => $booking->trashed(),
        ]);
    }

    public function store(BookingStoreRequest $request): RedirectResponse
    {
        $this->authorize('create', Booking::class);

        $validated = $request->validated();

        try {
            $booking = $this->createBooking($request, $validated);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        AuditLogService::logCreated($booking, "Booking created for asset #{$validated['asset_id']}.");

        $this->notifications->bookingSubmitted($booking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking request submitted.')]);

        return back();
    }

    public function update(BookingApproveRequest $request, Booking $booking): RedirectResponse
    {
        $action = $request->string('action')->toString();

        $this->authorize($action === 'reject' ? 'reject' : 'approve', $booking);

        if ($action === 'approve') {
            try {
                $this->approveBooking($booking, $request);
            } catch (ValidationException $exception) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => collect($exception->errors())->flatten()->first()
                        ?? __('This asset is already booked for part of the selected schedule. Choose different dates or select another asset.'),
                ]);

                return back()->withErrors($exception->errors());
            }

            $freshBooking = $booking->fresh() ?? $booking;
            AuditLogService::logCustom('approve', "Booking #{$booking->id} approved.", $freshBooking);
            $this->notifications->bookingStatusChanged($freshBooking, 'approved');
        } else {
            /** @var Booking|null $rejectedBooking */
            $rejectedBooking = DB::transaction(function () use ($request, $booking): ?Booking {
                /** @var Booking $lockedBooking */
                $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

                if ($lockedBooking->status !== BookingStatus::Requested) {
                    return null;
                }

                $lockedBooking->update([
                    'status' => BookingStatus::Rejected,
                    'approver_id' => $request->user()->id,
                    'approver_position_id' => $request->user()->position_id,
                    'approved_ip_address' => $request->ip(),
                ]);

                AuditLogService::logCustom('reject', "Booking #{$lockedBooking->id} rejected.", $lockedBooking);

                return $lockedBooking;
            });

            if (! $rejectedBooking instanceof Booking) {
                Inertia::flash('toast', [
                    'type' => 'warning',
                    'message' => __('This booking has already been processed.'),
                ]);

                return back();
            }

            $this->notifications->bookingStatusChanged($rejectedBooking, 'rejected');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Property Custodian']),
            403,
            __('You are not authorized to approve bookings.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        /** @var array{approved: array<int, Booking>, conflicts: int, skipped: int} $result */
        $result = DB::transaction(function () use ($request, $validated): array {
            $bookings = Booking::query()
                ->whereIn('id', $validated['ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortBy('start_at')
                ->values();

            foreach ($bookings as $booking) {
                $this->authorize('approve', $booking);
            }

            $approved = [];
            $conflicts = 0;
            $skipped = 0;

            foreach ($bookings as $booking) {
                if ($booking->status !== BookingStatus::Requested) {
                    $skipped++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($booking, $request): void {
                        $this->approveBooking($booking, $request);
                    });

                    $freshBooking = $booking->fresh() ?? $booking;
                    AuditLogService::logCustom('approve', "Booking #{$booking->id} approved.", $freshBooking);
                    $approved[] = $freshBooking;
                } catch (ValidationException) {
                    $conflicts++;
                }
            }

            return ['approved' => $approved, 'conflicts' => $conflicts, 'skipped' => $skipped];
        });

        foreach ($result['approved'] as $approvedBooking) {
            $this->notifications->bookingStatusChanged($approvedBooking, 'approved');
        }

        $updated = count($result['approved']);
        $conflicts = $result['conflicts'];
        $skipped = $result['skipped'];

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
        abort_unless(
            $request->user()?->hasAnyRole(['Admin', 'Property Custodian']),
            403,
            __('You are not authorized to reject bookings.'),
        );

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        /** @var array{rejected: array<int, Booking>, skipped: int} $result */
        $result = DB::transaction(function () use ($request, $validated): array {
            $bookings = Booking::query()
                ->whereIn('id', $validated['ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                $this->authorize('reject', $booking);
            }

            $rejected = [];
            $skipped = 0;

            foreach ($bookings as $booking) {
                if ($booking->status === BookingStatus::Requested) {
                    $booking->update([
                        'status' => BookingStatus::Rejected,
                        'approver_id' => $request->user()->id,
                        'approver_position_id' => $request->user()->position_id,
                        'approved_ip_address' => $request->ip(),
                    ]);
                    AuditLogService::logCustom('reject', "Booking #{$booking->id} rejected.", $booking);
                    $rejected[] = $booking;
                } else {
                    $skipped++;
                }
            }

            return ['rejected' => $rejected, 'skipped' => $skipped];
        });

        foreach ($result['rejected'] as $rejectedBooking) {
            $this->notifications->bookingStatusChanged($rejectedBooking, 'rejected');
        }

        $updated = count($result['rejected']);
        $skipped = $result['skipped'];

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

        $booking->deleted_by = $request->user()?->id;
        $booking->deletion_reason = $request->string('deletion_reason')->trim()->toString() ?: null;
        $booking->save();
        $booking->delete();

        AuditLogService::logDeleted($booking);

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

        $bookings = Booking::query()
            ->onlyTrashed()
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

        $deleters = Booking::query()
            ->onlyTrashed()
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
        /** @var Booking $booking */
        $booking = Booking::query()->withTrashed()->findOrFail($booking);

        $this->authorize('restore', $booking);

        $booking->restore();

        AuditLogService::logRestored($booking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking restored.')]);

        return back();
    }

    public function forceDelete(int $booking): RedirectResponse
    {
        /** @var Booking $booking */
        $booking = Booking::query()->withTrashed()->findOrFail($booking);

        $this->authorize('forceDelete', $booking);

        AuditLogService::logForceDeleted($booking);
        $booking->forceDelete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking permanently deleted.')]);

        return back();
    }

    public function bulkRestore(Request $request): RedirectResponse
    {
        $this->authorize('trash', Booking::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        $bookings = Booking::query()
            ->onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            $this->authorize('restore', $booking);
        }

        $restored = DB::transaction(function () use ($bookings): int {
            $count = 0;

            foreach ($bookings as $booking) {
                $booking->restore();
                AuditLogService::logRestored($booking);
                $count++;
            }

            return $count;
        });

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

    public function bulkForceDelete(Request $request): RedirectResponse
    {
        $this->authorize('trash', Booking::class);

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:bookings,id'],
        ]);

        $bookings = Booking::query()
            ->onlyTrashed()
            ->whereIn('id', $validated['ids'])
            ->orderBy('id')
            ->get();

        foreach ($bookings as $booking) {
            $this->authorize('forceDelete', $booking);
        }

        $deleted = 0;
        $errors = 0;

        foreach ($bookings as $booking) {
            try {
                DB::transaction(function () use ($booking): void {
                    AuditLogService::logForceDeleted($booking);
                    $booking->forceDelete();
                });
                $deleted++;
            } catch (QueryException $e) {
                report($e);
                $errors++;
            }
        }

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

    /**
     * @param  array{asset_id: int, start_at: string, end_at: string, purpose?: string|null}  $validated
     */
    private function createBooking(BookingStoreRequest $request, array $validated): Booking
    {
        $startAt = $request->startAt();
        $endAt = $request->endAt();

        return DB::transaction(function () use ($request, $validated, $startAt, $endAt): Booking {
            $asset = Asset::query()
                ->whereKey($validated['asset_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->assetIntegrity->ensureBookable(
                asset: $asset,
                startAt: $startAt,
                endAt: $endAt,
            );

            return Booking::create([
                'asset_id' => $validated['asset_id'],
                'requester_id' => $request->user()->id,
                'requester_position_id' => $request->user()->position_id,
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => $request->ip(),
                'approved_ip_address' => null,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'status' => BookingStatus::Requested,
                'purpose' => $validated['purpose'] ?? null,
            ]);
        });
    }

    private function approveBooking(Booking $booking, Request $request): void
    {
        DB::transaction(function () use ($booking, $request): void {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::Requested) {
                throw ValidationException::withMessages([
                    'action' => __('Only pending booking requests can be approved.'),
                ]);
            }

            if ($lockedBooking->end_at !== null && $lockedBooking->end_at->isPast()) {
                throw ValidationException::withMessages([
                    'start_at' => __('This request can no longer be approved because its schedule has already ended.'),
                ]);
            }

            $asset = Asset::query()
                ->whereKey($lockedBooking->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assetIntegrity->ensureBookable(
                asset: $asset,
                startAt: $lockedBooking->start_at,
                endAt: $lockedBooking->end_at,
                ignoreBookingId: $lockedBooking->id,
            );

            $lockedBooking->update([
                'status' => BookingStatus::Approved,
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);

            $booking->refresh();
        });
    }
}
