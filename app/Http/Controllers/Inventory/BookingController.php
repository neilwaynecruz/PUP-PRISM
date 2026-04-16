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
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(): Response
    {
        $currentUser = Auth::user();

        $assets = Asset::query()
            ->whereHas('product', fn ($q) => $q->where('type', 'asset')->where('is_active', true))
            ->with(['product:id,name', 'position:id,department_id,title', 'position.department:id,name'])
            ->orderBy('tag_code')
            ->get(['id', 'product_id', 'position_id', 'tag_code', 'status']);

        $bookings = Booking::query()
            ->with([
                'asset:id,product_id,position_id,tag_code',
                'asset.product:id,name',
                'requester:id,name,email,position_id',
                'requesterPosition:id,department_id,title',
                'approver:id,name,email,position_id',
                'approverPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'approverPosition.department:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return Inertia::render('inventory/bookings/Index', [
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
            'bookings' => $bookings->map(fn (Booking $b) => [
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
