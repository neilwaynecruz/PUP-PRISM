<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BookingApproveRequest;
use App\Http\Requests\Inventory\BookingStoreRequest;
use App\Services\NotificationService;
use App\Http\Resources\AssetResource;
use App\Http\Resources\BookingCollection;
use App\Http\Resources\BookingResource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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

    public function store(BookingStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $startAt = CarbonImmutable::parse($validated['start_at']);
        $endAt = CarbonImmutable::parse($validated['end_at']);

        $overlapsApproved = Booking::query()
            ->where('asset_id', $validated['asset_id'])
            ->where('status', BookingStatus::Approved)
            ->where(function ($q) use ($startAt, $endAt) {
                $q->whereBetween('start_at', [$startAt, $endAt])
                    ->orWhereBetween('end_at', [$startAt, $endAt])
                    ->orWhere(function ($q2) use ($startAt, $endAt) {
                        $q2->where('start_at', '<=', $startAt)->where('end_at', '>=', $endAt);
                    });
            })
            ->exists();

        if ($overlapsApproved) {
            return back()
                ->withErrors(['start_at' => __('This asset is already booked for the selected time range.')])
                ->withInput();
        }

        $booking = Booking::create([
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

        $this->notifications->bookingSubmitted($booking);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking request submitted.')]);

        return back();
    }

    public function update(BookingApproveRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('approve', $booking);

        $action = $request->string('action')->toString();

        if ($action === 'approve') {
            $booking->update([
                'status' => BookingStatus::Approved,
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);

            $this->notifications->bookingStatusChanged($booking, 'approved');
        } else {
            $booking->update([
                'status' => BookingStatus::Rejected,
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);

            $this->notifications->bookingStatusChanged($booking, 'rejected');
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }

    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorize('delete', $booking);

        $booking->deleted_by = $request->user()?->id;
        $booking->deletion_reason = $request->string('deletion_reason')->trim()->toString() ?: null;
        $booking->save();
        $booking->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking moved to trash.')]);

        return back();
    }

    public function trash(Request $request): Response
    {
        $this->authorize('trash', Booking::class);

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
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('inventory/bookings/Trash', [
            'bookings' => (new BookingCollection($bookings))->toArray($request),
        ]);
    }

    public function restore(int $booking): RedirectResponse
    {
        $booking = Booking::query()->withTrashed()->findOrFail($booking);

        $this->authorize('restore', $booking);

        $booking->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking restored.')]);

        return back();
    }
}
