<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BookingApproveRequest;
use App\Http\Requests\Inventory\BookingStoreRequest;
use App\Http\Resources\AssetResource;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
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
    ) {}

    public function index(Request $request): Response
    {
        $currentUser = Auth::user();
        $assetSearch = $request->string('asset_search')->trim()->toString();

        $assets = Asset::query()
            ->where('status', AssetStatus::Available)
            ->whereHas('product', fn ($q) => $q->where('type', ProductType::Asset)->where('is_active', true))
            ->with(['product:id,name', 'position:id,department_id,title', 'position.department:id,name'])
            ->when($assetSearch !== '', function ($query) use ($assetSearch) {
                $query->where(function ($query) use ($assetSearch) {
                    $query->where('tag_code', 'like', "%{$assetSearch}%")
                        ->orWhereHas('product', function ($query) use ($assetSearch) {
                            $query->where('name', 'like', "%{$assetSearch}%");
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

        $calendarEvents = Booking::query()
            ->with($bookingPayload)
            ->where('end_at', '>=', $calendarWindowStart)
            ->where('start_at', '<=', $calendarWindowEnd)
            ->orderBy('start_at')
            ->get();

        $approvalQueue = Booking::query()
            ->with($bookingPayload)
            ->where('status', BookingStatus::Requested)
            ->orderBy('start_at')
            ->limit(15)
            ->get();

        $bookings = Booking::query()
            ->with([
                ...$bookingPayload,
            ])
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/bookings/Index', [
            'filters' => [
                'asset_search' => $assetSearch,
            ],
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
        $this->authorize('approve', $booking);

        $action = $request->string('action')->toString();

        if ($action === 'approve') {
            try {
                $this->approveBooking($booking, $request);
            } catch (ValidationException $exception) {
                Inertia::flash('toast', [
                    'type' => 'error',
                    'message' => $exception->errors()['start_at'][0] ?? __('This asset is already booked for the selected schedule.'),
                ]);

                return back()->withErrors($exception->errors());
            }

            AuditLogService::logCustom('approve', "Booking #{$booking->id} approved.", $booking->fresh());
            $this->notifications->bookingStatusChanged($booking, 'approved');
        } else {
            $booking->update([
                'status' => BookingStatus::Rejected,
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);

            AuditLogService::logCustom('reject', "Booking #{$booking->id} rejected.", $booking);
            $this->notifications->bookingStatusChanged($booking, 'rejected');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }

    public function bulkApprove(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $ids = $validated['ids'];
        $bookings = Booking::query()
            ->whereIn('id', $ids)
            ->orderBy('start_at')
            ->get();

        $updated = 0;
        $conflicts = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            $this->authorize('approve', $booking);

            if ($booking->status !== BookingStatus::Requested) {
                $skipped++;

                continue;
            }

            try {
                $this->approveBooking($booking, $request);
                AuditLogService::logCustom('approve', "Booking #{$booking->id} approved.", $booking->fresh());
                $this->notifications->bookingStatusChanged($booking, 'approved');
                $updated++;
            } catch (ValidationException) {
                $conflicts++;
            }
        }

        $message = "{$updated} booking(s) approved.";

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
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ]);

        $ids = $validated['ids'];
        $bookings = Booking::query()
            ->whereIn('id', $ids)
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($bookings as $booking) {
            $this->authorize('reject', $booking);

            if ($booking->status === BookingStatus::Requested) {
                $booking->update([
                    'status' => BookingStatus::Rejected,
                    'approver_id' => $request->user()->id,
                    'approver_position_id' => $request->user()->position_id,
                    'approved_ip_address' => $request->ip(),
                ]);
                AuditLogService::logCustom('reject', "Booking #{$booking->id} rejected.", $booking);
                $this->notifications->bookingStatusChanged($booking, 'rejected');
                $updated++;
            } else {
                $skipped++;
            }
        }

        $message = "{$updated} booking(s) rejected.";

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
                    $q->where('tag_code', 'like', "%{$search}%");
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
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back();
        }

        $restored = 0;
        foreach ($ids as $id) {
            /** @var Booking|null $booking */
            $booking = Booking::query()->withTrashed()->find($id);
            if ($booking && $booking->trashed()) {
                $this->authorize('restore', $booking);
                $booking->restore();
                AuditLogService::logRestored($booking);
                $restored++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("{$restored} booking(s) restored."),
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
            /** @var Booking|null $booking */
            $booking = Booking::query()->withTrashed()->find($id);
            if ($booking && $booking->trashed()) {
                $this->authorize('forceDelete', $booking);
                AuditLogService::logForceDeleted($booking);
                $booking->forceDelete();
                $deleted++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __("{$deleted} booking(s) permanently deleted."),
        ]);

        return back();
    }

    /**
     * @param  array{asset_id: int, start_at: string, end_at: string, purpose?: string|null}  $validated
     */
    private function createBooking(Request $request, array $validated): Booking
    {
        $startAt = CarbonImmutable::parse($validated['start_at']);
        $endAt = CarbonImmutable::parse($validated['end_at']);

        return DB::transaction(function () use ($request, $validated, $startAt, $endAt): Booking {
            Asset::query()
                ->whereKey($validated['asset_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureAssetWindowIsAvailable(
                assetId: $validated['asset_id'],
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

            Asset::query()
                ->whereKey($lockedBooking->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->ensureAssetWindowIsAvailable(
                assetId: $lockedBooking->asset_id,
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

    private function ensureAssetWindowIsAvailable(
        int $assetId,
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?int $ignoreBookingId = null,
    ): void {
        $conflictExists = Booking::query()
            ->blocking()
            ->forAssetWindow(
                assetId: $assetId,
                startAt: $startAt,
                endAt: $endAt,
                ignoreBookingId: $ignoreBookingId,
            )
            ->lockForUpdate()
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'start_at' => __('This asset is already booked for the selected schedule.'),
            ]);
        }
    }
}
