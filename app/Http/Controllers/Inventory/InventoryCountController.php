<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\InventoryCountReviewStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\InventoryCountLineRequest;
use App\Http\Requests\Inventory\InventoryCountReviewRequest;
use App\Http\Requests\Inventory\InventoryCountStoreRequest;
use App\Models\Asset;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Services\Inventory\PhysicalCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InventoryCountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryCountSession::class);

        $user = $request->user();
        $sessions = InventoryCountSession::query()
            ->with(['location:id,code,name', 'creator:id,name', 'assignedUser:id,name'])
            ->withCount([
                'lines',
                'lines as counted_lines_count' => fn ($query) => $query->whereNotNull('counted_qty'),
                'lines as discrepancy_lines_count' => fn ($query) => $query->where('variance_qty', '!=', 0),
            ])
            ->when(! $user->hasRole('Admin'), fn ($query) => $query->where(
                fn ($query) => $query->where('created_by', $user->id)->orWhere('assigned_to', $user->id),
            ))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $products = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereHas('lots', fn ($query) => $query->where('qty_remaining', '>', 0))
            ->withSum('lots as countable_qty', 'qty_remaining')
            ->orderBy('name')
            ->get(['id', 'sku', 'name']);

        $assets = Asset::query()
            ->where('lifecycle_status', AssetLifecycleStatus::Active)
            ->with(['product:id,name', 'currentLocation:id,code,name'])
            ->orderBy('tag_code')
            ->get(['id', 'product_id', 'current_location_id', 'tag_code', 'status']);

        return Inertia::render('inventory/counts/Index', [
            'sessions' => $sessions,
            'products' => $products,
            'assets' => $assets,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'users' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn ($query) => $query->whereIn('name', ['Admin', 'Supply Head', 'Property Custodian']))
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(InventoryCountStoreRequest $request, PhysicalCountService $service): RedirectResponse
    {
        try {
            $session = $service->createSession($request->user(), $request->validated());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['product_ids' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The physical count session has been created.')]);

        return to_route('inventory.counts.show', $session);
    }

    public function show(InventoryCountSession $inventoryCountSession): Response
    {
        $this->authorize('view', $inventoryCountSession);

        $inventoryCountSession->load([
            'location:id,code,name,building,room',
            'creator:id,name',
            'assignedUser:id,name',
            'approver:id,name',
            'lines.product:id,sku,name',
            'lines.stockLot:id,product_id,reference_no,received_at,expires_at,qty_remaining',
            'lines.asset:id,product_id,current_location_id,tag_code,status,lifecycle_status,condition_status',
            'lines.countedBy:id,name',
            'lines.reviewedBy:id,name',
            'lines.adjustmentMovement:id,qty_delta,qty_before,qty_after,performed_at',
        ]);

        return Inertia::render('inventory/counts/Show', [
            'session' => $inventoryCountSession,
            'can' => [
                'update' => request()->user()->can('update', $inventoryCountSession),
                'review' => request()->user()->can('review', $inventoryCountSession),
                'complete' => request()->user()->can('complete', $inventoryCountSession),
                'cancel' => request()->user()->can('cancel', $inventoryCountSession),
            ],
        ]);
    }

    public function updateLine(
        InventoryCountLineRequest $request,
        InventoryCountSession $inventoryCountSession,
        InventoryCountLine $inventoryCountLine,
        PhysicalCountService $service,
    ): RedirectResponse {
        try {
            $service->recordLine(
                $request->user(),
                $inventoryCountSession,
                $inventoryCountLine,
                (int) $request->validated('counted_qty'),
                $request->validated('count_notes'),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['counted_qty' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The counted quantity has been saved.')]);

        return back();
    }

    public function submit(Request $request, InventoryCountSession $inventoryCountSession, PhysicalCountService $service): RedirectResponse
    {
        $this->authorize('submit', $inventoryCountSession);

        try {
            $service->submit($request->user(), $inventoryCountSession);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The count is ready for review.')]);

        return back();
    }

    public function reviewLine(
        InventoryCountReviewRequest $request,
        InventoryCountSession $inventoryCountSession,
        InventoryCountLine $inventoryCountLine,
        PhysicalCountService $service,
    ): RedirectResponse {
        try {
            $service->reviewLine(
                $request->user(),
                $inventoryCountSession,
                $inventoryCountLine,
                InventoryCountReviewStatus::from($request->validated('review_status')),
                $request->validated('review_notes'),
            );
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['review_status' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The discrepancy review has been saved.')]);

        return back();
    }

    public function approve(Request $request, InventoryCountSession $inventoryCountSession, PhysicalCountService $service): RedirectResponse
    {
        $this->authorize('review', $inventoryCountSession);

        try {
            $service->approve($request->user(), $inventoryCountSession, $request->ip());
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The reviewed stock adjustments have been posted.')]);

        return back();
    }

    public function complete(InventoryCountSession $inventoryCountSession, PhysicalCountService $service): RedirectResponse
    {
        $this->authorize('complete', $inventoryCountSession);

        try {
            $service->complete($inventoryCountSession);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The physical count session is complete.')]);

        return back();
    }

    public function cancel(InventoryCountSession $inventoryCountSession, PhysicalCountService $service): RedirectResponse
    {
        $this->authorize('cancel', $inventoryCountSession);

        try {
            $service->cancel($inventoryCountSession);
        } catch (\RuntimeException $exception) {
            return back()->withErrors(['session' => $exception->getMessage()]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The physical count session has been cancelled.')]);

        return back();
    }
}
