<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignInventoryAlertRequest;
use App\Http\Requests\Admin\ResolveInventoryAlertRequest;
use App\Models\InventoryAlert;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\InventoryRealtimeService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryAlert::class);

        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'type' => $request->string('type')->trim()->toString(),
            'status' => $request->string('status')->trim()->toString() ?: 'active',
            'assigned_to' => $request->integer('assigned_to'),
        ];

        $query = InventoryAlert::query()
            ->with([
                'product:id,sku,name,reorder_threshold',
                'product.stock:id,product_id,on_hand_qty',
                'stockLot:id,product_id,reference_no,expires_at,qty_remaining',
                'acknowledgedBy:id,name,email',
                'assignedTo:id,name,email',
                'resolvedBy:id,name,email',
            ])
            ->orderByRaw('resolved_at IS NOT NULL')
            ->orderByDesc('detected_at');

        if ($filters['search'] !== '') {
            $query->where(function ($builder) use ($filters): void {
                $builder
                    ->whereLike('message', "%{$filters['search']}%")
                    ->orWhereHas('product', function ($productQuery) use ($filters): void {
                        $productQuery
                            ->whereLike('name', "%{$filters['search']}%")
                            ->orWhereLike('sku', "%{$filters['search']}%");
                    });
            });
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        match ($filters['status']) {
            'active' => $query->active(),
            'acknowledged' => $query->active()->whereNotNull('acknowledged_at'),
            'unacknowledged' => $query->active()->whereNull('acknowledged_at'),
            'resolved' => $query->resolved(),
            default => null,
        };

        if ($filters['assigned_to'] > 0) {
            $query->where('assigned_to', $filters['assigned_to']);
        } elseif ($filters['assigned_to'] === -1) {
            $query->whereNull('assigned_to');
        }

        $alerts = $query
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InventoryAlert $alert): array => $this->presentAlert($alert));

        $types = InventoryAlert::query()
            ->distinct()
            ->orderBy('type')
            ->pluck('type')
            ->values()
            ->all();

        $operators = User::query()
            ->role(['Admin', 'Supply Head'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])
            ->values()
            ->all();

        return Inertia::render('admin/alerts/Index', [
            'filters' => $filters,
            'alerts' => $alerts,
            'types' => $types,
            'operators' => $operators,
            'summary' => $this->buildSummary($request->user()?->id),
            'currentUserId' => $request->user()?->id,
        ]);
    }

    /**
     * @return array{
     *     active: int,
     *     unacknowledged: int,
     *     acknowledged: int,
     *     assigned_to_me: int,
     *     resolved_this_week: int,
     *     active_by_type: array<string, int>
     * }
     */
    private function buildSummary(?int $currentUserId): array
    {
        $weekStart = CarbonImmutable::now()->subDays(7);

        $rows = InventoryAlert::query()
            ->select('type')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) as active_count')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NULL AND acknowledged_at IS NULL THEN 1 ELSE 0 END) as unacknowledged_count')
            ->selectRaw('SUM(CASE WHEN resolved_at IS NULL AND acknowledged_at IS NOT NULL THEN 1 ELSE 0 END) as acknowledged_count')
            ->selectRaw(
                $currentUserId !== null
                    ? 'SUM(CASE WHEN resolved_at IS NULL AND assigned_to = ? THEN 1 ELSE 0 END) as assigned_to_me_count'
                    : '0 as assigned_to_me_count',
                $currentUserId !== null ? [$currentUserId] : []
            )
            ->selectRaw('SUM(CASE WHEN resolved_at IS NOT NULL AND resolved_at >= ? THEN 1 ELSE 0 END) as resolved_this_week_count', [$weekStart])
            ->groupBy('type')
            ->get();

        $active = 0;
        $unacknowledged = 0;
        $acknowledged = 0;
        $assignedToMe = 0;
        $resolvedThisWeek = 0;
        $activeByType = [];

        foreach ($rows as $row) {
            $typeActive = (int) $row->getAttribute('active_count');
            $active += $typeActive;
            $unacknowledged += (int) $row->getAttribute('unacknowledged_count');
            $acknowledged += (int) $row->getAttribute('acknowledged_count');
            $assignedToMe += (int) $row->getAttribute('assigned_to_me_count');
            $resolvedThisWeek += (int) $row->getAttribute('resolved_this_week_count');

            if ($typeActive > 0) {
                $activeByType[(string) $row->type] = $typeActive;
            }
        }

        return [
            'active' => $active,
            'unacknowledged' => $unacknowledged,
            'acknowledged' => $acknowledged,
            'assigned_to_me' => $assignedToMe,
            'resolved_this_week' => $resolvedThisWeek,
            'active_by_type' => $activeByType,
        ];
    }

    public function acknowledge(Request $request, InventoryAlert $alert): RedirectResponse
    {
        $this->authorize('acknowledge', $alert);

        if ($alert->acknowledged_at !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This alert was already acknowledged.')]);

            return back();
        }

        $before = $this->auditState($alert);

        $alert->update([
            'acknowledged_at' => CarbonImmutable::now(),
            'acknowledged_by' => $request->user()->id,
            'assigned_to' => $alert->assigned_to ?? $request->user()->id,
        ]);

        AuditLogService::logCustom(
            'alert_acknowledge',
            __('Alert #:id acknowledged.', ['id' => $alert->id]),
            $alert,
            $before,
            $this->auditState($alert->fresh(['assignedTo:id,name']) ?? $alert),
        );
        app(InventoryRealtimeService::class)->alertUpdated($alert, 'acknowledged');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alert acknowledged.')]);

        return back();
    }

    public function assign(AssignInventoryAlertRequest $request, InventoryAlert $alert): RedirectResponse
    {
        $this->authorize('assign', $alert);

        $assignee = $request->assignee();

        if ($alert->assigned_to === $assignee->id) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('This alert is already assigned to :name.', ['name' => $assignee->name])]);

            return back();
        }

        $before = $this->auditState($alert);

        $alert->update([
            'assigned_to' => $assignee->id,
            'acknowledged_at' => $alert->acknowledged_at ?? CarbonImmutable::now(),
            'acknowledged_by' => $alert->acknowledged_by ?? $request->user()->id,
        ]);

        AuditLogService::logCustom(
            'alert_assign',
            __('Alert #:id assigned to :name.', ['id' => $alert->id, 'name' => $assignee->name]),
            $alert,
            $before,
            $this->auditState($alert->fresh(['assignedTo:id,name']) ?? $alert),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alert assigned to :name.', ['name' => $assignee->name])]);

        return back();
    }

    public function resolve(ResolveInventoryAlertRequest $request, InventoryAlert $alert): RedirectResponse
    {
        $this->authorize('resolve', $alert);

        $before = $this->auditState($alert);

        $alert->update([
            'resolved_at' => CarbonImmutable::now(),
            'resolved_by' => $request->user()->id,
            'resolution_notes' => $request->validated('resolution_notes'),
        ]);

        AuditLogService::logCustom(
            'alert_resolve',
            __('Alert #:id resolved.', ['id' => $alert->id]),
            $alert,
            $before,
            $this->auditState($alert),
        );
        app(InventoryRealtimeService::class)->alertUpdated($alert, 'resolved');

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Alert resolved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAlert(InventoryAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'type' => $alert->type,
            'message' => $alert->message,
            'detected_at' => $alert->detected_at->toIso8601String(),
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'resolution_notes' => $alert->resolution_notes,
            'is_active' => $alert->isActive(),
            'severity' => $alert->severity(),
            'product' => $alert->product ? [
                'id' => $alert->product->id,
                'sku' => $alert->product->sku,
                'name' => $alert->product->name,
                'on_hand_qty' => $alert->product->stock?->on_hand_qty !== null ? (int) $alert->product->stock->on_hand_qty : null,
                'reorder_threshold' => $alert->product->reorder_threshold,
            ] : null,
            'stock_lot' => $alert->stockLot ? [
                'id' => $alert->stockLot->id,
                'reference_no' => $alert->stockLot->reference_no,
                'expires_at' => $alert->stockLot->expires_at?->toDateString(),
                'qty_remaining' => (int) $alert->stockLot->qty_remaining,
            ] : null,
            'acknowledged_by' => $alert->acknowledgedBy ? [
                'id' => $alert->acknowledgedBy->id,
                'name' => $alert->acknowledgedBy->name,
            ] : null,
            'assigned_to' => $alert->assignedTo ? [
                'id' => $alert->assignedTo->id,
                'name' => $alert->assignedTo->name,
            ] : null,
            'resolved_by' => $alert->resolvedBy ? [
                'id' => $alert->resolvedBy->id,
                'name' => $alert->resolvedBy->name,
            ] : null,
        ];
    }

    /**
     * @return array{acknowledged_at: string|null, assigned_to: string|null, resolved_at: string|null, resolution_notes: string|null}
     */
    private function auditState(InventoryAlert $alert): array
    {
        return [
            'acknowledged_at' => $alert->acknowledged_at?->toIso8601String(),
            'assigned_to' => $alert->assigned_to !== null
                ? ($alert->relationLoaded('assignedTo') ? $alert->assignedTo?->name : User::query()->whereKey($alert->assigned_to)->value('name'))
                : null,
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'resolution_notes' => $alert->resolution_notes,
        ];
    }
}
