<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\BookingApproveRequest;
use App\Http\Requests\Inventory\BookingStoreRequest;
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
    public function index(Request $request): Response
    {
        $currentUser = Auth::user();
        $assetSearch = $request->string('asset_search')->trim()->toString();

        $assets = Asset::query()
            ->where('status', 'Available')
            ->whereHas('product', fn ($q) => $q->where('type', 'asset')->where('is_active', true))
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
            ->where('status', 'Requested')
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
            'assets' => $assets->map(fn (Asset $a) => [
                'id' => $a->id,
                'tag_code' => $a->tag_code,
                'status' => $a->status,
                'name' => $a->product?->name,
                'position' => $a->position ? [
                    'title' => $a->position->title,
                    'department' => $a->position->department?->name,
                ] : null,
            ]),
            'calendar_events' => $calendarEvents->map(fn (Booking $b) => [
                'id' => $b->id,
                'asset_id' => $b->asset_id,
                'title' => ($b->asset?->product?->name ?? 'Asset').' - '.$b->status,
                'start' => $b->start_at?->toIso8601String(),
                'end' => $b->end_at?->toIso8601String(),
                'status' => $b->status,
            ]),
            'approval_queue' => $approvalQueue->map(fn (Booking $b) => [
                'id' => $b->id,
                'asset_id' => $b->asset_id,
                'title' => ($b->asset?->product?->name ?? 'Asset').' - '.$b->status,
                'start' => $b->start_at?->toIso8601String(),
                'end' => $b->end_at?->toIso8601String(),
                'status' => $b->status,
                'requester' => $b->requester ? [
                    'name' => $b->requester->name,
                    'email' => $b->requester->email,
                ] : null,
                'requester_position' => $b->requesterPosition ? [
                    'title' => $b->requesterPosition->title,
                    'department' => $b->requesterPosition->department?->name,
                ] : null,
                'approver' => $b->approver ? [
                    'name' => $b->approver->name,
                    'email' => $b->approver->email,
                ] : null,
            ]),
            'bookings' => $bookings->through(fn (Booking $b) => [
                'id' => $b->id,
                'asset_id' => $b->asset_id,
                'asset_label' => $b->asset?->tag_code,
                'title' => ($b->asset?->product?->name ?? 'Asset').' - '.$b->status,
                'start' => $b->start_at?->toIso8601String(),
                'end' => $b->end_at?->toIso8601String(),
                'status' => $b->status,
                'requester_id' => $b->requester_id,
                'requester' => $b->requester ? [
                    'name' => $b->requester->name,
                    'email' => $b->requester->email,
                ] : null,
                'requester_position' => $b->requesterPosition ? [
                    'title' => $b->requesterPosition->title,
                    'department' => $b->requesterPosition->department?->name,
                ] : null,
                'approver' => $b->approver ? [
                    'name' => $b->approver->name,
                    'email' => $b->approver->email,
                ] : null,
                'requested_ip_address' => $b->requested_ip_address,
                'approved_ip_address' => $b->approved_ip_address,
            ]),
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
            ->where('status', 'Approved')
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

        Booking::create([
            'asset_id' => $validated['asset_id'],
            'requester_id' => $request->user()->id,
            'requester_position_id' => $request->user()->position_id,
            'approver_id' => null,
            'approver_position_id' => null,
            'requested_ip_address' => $request->ip(),
            'approved_ip_address' => null,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'status' => 'Requested',
            'purpose' => $validated['purpose'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking request submitted.')]);

        return back();
    }

    public function update(BookingApproveRequest $request, Booking $booking): RedirectResponse
    {
        $this->authorize('approve', $booking);

        $action = $request->string('action')->toString();

        if ($action === 'approve') {
            $booking->update([
                'status' => 'Approved',
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);
        } else {
            $booking->update([
                'status' => 'Rejected',
                'approver_id' => $request->user()->id,
                'approver_position_id' => $request->user()->position_id,
                'approved_ip_address' => $request->ip(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Booking updated.')]);

        return back();
    }
}
