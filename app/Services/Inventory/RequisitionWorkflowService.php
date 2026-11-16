<?php

namespace App\Services\Inventory;

use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DashboardStatsCache;
use App\Services\NotificationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequisitionWorkflowService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly NotificationService $notifications,
        private readonly DashboardStatsCache $dashboardStatsCache,
        private readonly BulkTrashService $bulkTrash,
    ) {}

    /**
     * Submit a new requisition from Web SKU lines inside a locked transaction.
     *
     * @param  array<int, array{sku: string, qty_requested: int|string}>  $linesInput
     */
    public function submitFromSkus(
        User $actor,
        array $linesInput,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): Requisition {
        $requisition = DB::transaction(function () use ($actor, $linesInput, $notes, $ipAddress): Requisition {
            $requestedSkus = collect($linesInput)->pluck('sku')->all();
            $productsBySku = Product::query()
                ->whereIn('sku', $requestedSkus)
                ->where('type', ProductType::Consumable)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get(['id', 'sku'])
                ->keyBy('sku');

            foreach ($linesInput as $index => $line) {
                if (! $productsBySku->has($line['sku'])) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.sku" => 'This product is no longer available for requisitions. Choose another product.',
                    ]);
                }
            }

            $requisition = new Requisition([
                'requester_id' => $actor->id,
                'requester_position_id' => $actor->position_id,
                'notes' => $notes,
            ]);
            $requisition->forceFill([
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => $ipAddress,
                'approved_ip_address' => null,
                'approved_at' => null,
                'issued_by' => null,
                'issued_position_id' => null,
                'issued_ip_address' => null,
                'issued_at' => null,
                'status' => RequisitionStatus::Submitted,
            ])->save();

            foreach ($linesInput as $line) {
                /** @var Product $product */
                $product = $productsBySku->get($line['sku']);

                RequisitionLine::create([
                    'requisition_id' => $requisition->id,
                    'product_id' => $product->id,
                    'qty_requested' => (int) $line['qty_requested'],
                    'qty_issued' => 0,
                ]);
            }

            AuditLogService::logCreated($requisition, "Requisition #{$requisition->id} submitted.");

            return $requisition;
        });

        $this->dashboardStatsCache->invalidate();
        $requisition->loadMissing('requester');
        $this->notifications->requisitionSubmitted($requisition);

        return $requisition;
    }

    /**
     * Submit a new requisition from API product ID lines with identical auditing and notifications as Web.
     *
     * @param  array<int, array{product_id: int|string, qty_requested: int|string}>  $linesInput
     */
    public function submitFromProductIds(
        User $actor,
        array $linesInput,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): Requisition {
        $requisition = DB::transaction(function () use ($actor, $linesInput, $notes, $ipAddress): Requisition {
            $requisition = new Requisition([
                'requester_id' => $actor->id,
                'requester_position_id' => $actor->position_id,
                'notes' => $notes,
            ]);
            $requisition->forceFill([
                'status' => RequisitionStatus::Submitted,
                'requested_ip_address' => $ipAddress,
            ])->save();

            foreach ($linesInput as $line) {
                $requisition->lines()->create([
                    'product_id' => (int) $line['product_id'],
                    'qty_requested' => (int) $line['qty_requested'],
                ]);
            }

            return $requisition;
        });

        $requisition->load(['requester:id,name,email', 'lines.product:id,sku,name']);

        AuditLogService::logCreated($requisition, "Requisition #{$requisition->id} submitted via API.");
        $this->dashboardStatsCache->invalidate();
        $this->notifications->requisitionSubmitted($requisition);

        return $requisition;
    }

    /**
     * Approve a submitted requisition while enforcing separation of duties and locking.
     *
     * @return array{approved: bool, requisition: Requisition}
     */
    public function approve(
        Requisition $requisition,
        User $actor,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): array {
        /** @var array{approved: bool, requisition: Requisition} $result */
        $result = DB::transaction(function () use ($requisition, $actor, $notes, $ipAddress): array {
            $locked = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if ($actor->id === $locked->requester_id) {
                abort(403, __('You cannot approve your own requisition.'));
            }

            if ($locked->status !== RequisitionStatus::Submitted) {
                return ['approved' => false, 'requisition' => $locked];
            }

            $locked->forceFill([
                'status' => RequisitionStatus::Approved,
                'approver_id' => $actor->id,
                'approver_position_id' => $actor->position_id,
                'approved_ip_address' => $ipAddress,
                'approved_at' => now()->toImmutable(),
                'notes' => $notes ?? $locked->notes,
            ])->save();

            AuditLogService::logCustom('approve', "Requisition #{$locked->id} approved.", $locked);

            return ['approved' => true, 'requisition' => $locked];
        });

        if ($result['approved']) {
            $this->dashboardStatsCache->invalidate();
            $result['requisition']->loadMissing('requester');
            $this->notifications->requisitionStatusChanged($result['requisition'], 'approved');
        }

        return $result;
    }

    /**
     * Reject a submitted requisition while enforcing separation of duties and preserving existing notes.
     *
     * @return array{rejected: bool, requisition: Requisition}
     */
    public function reject(Requisition $requisition, User $actor, string $reason): array
    {
        $trimmedReason = trim($reason);

        /** @var array{rejected: bool, requisition: Requisition} $result */
        $result = DB::transaction(function () use ($requisition, $actor, $trimmedReason): array {
            $locked = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if ($actor->id === $locked->requester_id) {
                abort(403, __('You cannot reject your own requisition.'));
            }

            if ($locked->status !== RequisitionStatus::Submitted) {
                return ['rejected' => false, 'requisition' => $locked];
            }

            $existingNotes = trim((string) $locked->notes);

            $locked->forceFill([
                'status' => RequisitionStatus::Rejected,
                'notes' => $existingNotes === ''
                    ? "Rejection reason: {$trimmedReason}"
                    : "{$existingNotes}\n\nRejection reason: {$trimmedReason}",
            ])->save();

            AuditLogService::logCustom('reject', "Requisition #{$locked->id} rejected.", $locked);

            return ['rejected' => true, 'requisition' => $locked];
        });

        if ($result['rejected']) {
            $this->dashboardStatsCache->invalidate();
            $result['requisition']->loadMissing('requester');
            $this->notifications->requisitionStatusChanged($result['requisition'], 'rejected');
        }

        return $result;
    }

    /**
     * Issue items against an approved, partially issued, or backordered requisition.
     *
     * @param  array<int, array{id: int, qty_to_issue: int}>|null  $linePayloads
     */
    public function issue(
        Requisition $requisition,
        User $actor,
        ?array $linePayloads = null,
        bool $markAsBackordered = false,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): Requisition {
        $issued = $this->inventory->issueRequisition(
            user: $actor,
            linePayloads: $linePayloads,
            markAsBackordered: $markAsBackordered,
            requisition: $requisition,
            notes: $notes,
            ipAddress: $ipAddress,
        );

        AuditLogService::logCustom(
            'issue',
            "Requisition #{$issued->id} issued.",
            $issued,
            null,
            [
                'status' => $issued->status->value,
                'issued_at' => $issued->issued_at?->toIso8601String(),
                'line_count' => $issued->lines()->count(),
            ],
        );

        $this->dashboardStatsCache->invalidate();
        $issued->loadMissing('requester');
        $this->notifications->requisitionStatusChanged($issued, 'issued');

        return $issued;
    }

    /**
     * @param  list<int>  $ids
     * @return array{approved: int, skipped: int}
     */
    public function bulkApprove(array $ids, User $actor, ?string $ipAddress = null): array
    {
        /** @var array{approved: array<int, Requisition>, skipped: int} $result */
        $result = DB::transaction(function () use ($ids, $actor, $ipAddress): array {
            $requisitions = Requisition::query()
                ->with('requester')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortBy('created_at')
                ->values();

            $approved = [];
            $approvedIds = [];
            $auditEntries = [];
            $skipped = 0;
            $approvedAt = now()->toImmutable();

            foreach ($requisitions as $requisition) {
                if (! $actor->can('approve', $requisition)) {
                    $skipped++;

                    continue;
                }

                $requisition->forceFill([
                    'status' => RequisitionStatus::Approved,
                    'approver_id' => $actor->id,
                    'approver_position_id' => $actor->position_id,
                    'approved_ip_address' => $ipAddress,
                    'approved_at' => $approvedAt,
                ]);
                $approvedIds[] = $requisition->id;
                $auditEntries[] = [
                    'action' => 'approve',
                    'description' => "Requisition #{$requisition->id} approved.",
                    'model' => $requisition,
                ];
                $approved[] = $requisition;
            }

            if ($approvedIds !== []) {
                Requisition::query()
                    ->whereIn('id', $approvedIds)
                    ->update([
                        'status' => RequisitionStatus::Approved->value,
                        'approver_id' => $actor->id,
                        'approver_position_id' => $actor->position_id,
                        'approved_ip_address' => $ipAddress,
                        'approved_at' => $approvedAt,
                        'updated_at' => $approvedAt,
                    ]);

                AuditLogService::logBatch($auditEntries);
            }

            return ['approved' => $approved, 'skipped' => $skipped];
        });

        if ($result['approved'] !== []) {
            $this->dashboardStatsCache->invalidate();
        }

        foreach ($result['approved'] as $approvedRequisition) {
            $approvedRequisition->loadMissing('requester');
            $this->notifications->requisitionStatusChanged($approvedRequisition, 'approved');
        }

        return [
            'approved' => count($result['approved']),
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{issued: int, skipped: int, failed: int}
     */
    public function bulkIssue(array $ids, User $actor, ?string $ipAddress = null): array
    {
        $requisitions = Requisition::query()
            ->with('requester')
            ->whereIn('id', $ids)
            ->orderBy('created_at')
            ->get();

        $issued = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($requisitions as $requisition) {
            if (! $actor->can('issue', $requisition)) {
                $skipped++;

                continue;
            }

            try {
                $issuedRequisition = $this->inventory->issueRequisition(
                    user: $actor,
                    requisition: $requisition,
                    linePayloads: null,
                    markAsBackordered: false,
                    notes: null,
                    ipAddress: $ipAddress,
                );
                AuditLogService::logCustom('issue', "Requisition #{$requisition->id} issued.", $issuedRequisition);
                $issuedRequisition->loadMissing('requester');
                $this->notifications->requisitionStatusChanged($issuedRequisition, 'issued');
                $issued++;
            } catch (\RuntimeException $exception) {
                report($exception);
                $failed++;
            }
        }

        if ($issued > 0) {
            $this->dashboardStatsCache->invalidate();
        }

        return [
            'issued' => $issued,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    public function softDelete(Requisition $requisition, ?User $actor, ?string $deletionReason = null): void
    {
        $trimmedReason = $deletionReason !== null ? trim($deletionReason) : null;

        $requisition->forceFill([
            'deleted_by' => $actor?->id,
            'deletion_reason' => $trimmedReason !== '' ? $trimmedReason : null,
        ])->save();
        $requisition->delete();

        AuditLogService::logDeleted($requisition);
        $this->dashboardStatsCache->invalidate();
    }

    public function restore(Requisition $requisition): void
    {
        $requisition->restore();

        AuditLogService::logRestored($requisition);
        $this->dashboardStatsCache->invalidate();
    }

    /**
     * Permanently delete a single soft-deleted requisition if it has no issuance history or references.
     *
     * @return 'deleted'|'has_history'|'referenced'
     */
    public function forceDelete(Requisition $requisition): string
    {
        if ($this->hasHistoricalActivity($requisition)) {
            return 'has_history';
        }

        try {
            AuditLogService::logForceDeleted($requisition);
            $requisition->forceDelete();
            $this->dashboardStatsCache->invalidate();

            return 'deleted';
        } catch (QueryException $e) {
            report($e);

            return 'referenced';
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function bulkRestore(array $ids, User $actor): int
    {
        return $this->bulkTrash->bulkRestore(
            Requisition::class,
            $ids,
            $actor,
            fn (Requisition $requisition): string => $requisition->id ? "#{$requisition->id}" : '',
        );
    }

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: int}
     */
    public function bulkForceDelete(array $ids, User $actor): array
    {
        if ($ids === []) {
            return ['deleted' => 0, 'skipped' => 0];
        }

        $movementReqIds = StockMovement::query()
            ->whereIn('requisition_id', $ids)
            ->distinct()
            ->pluck('requisition_id')
            ->flip();

        $issuedLineReqIds = RequisitionLine::query()
            ->whereIn('requisition_id', $ids)
            ->where('qty_issued', '>', 0)
            ->distinct()
            ->pluck('requisition_id')
            ->flip();

        return $this->bulkTrash->bulkForceDelete(
            Requisition::class,
            $ids,
            $actor,
            function (Requisition $requisition) use ($movementReqIds, $issuedLineReqIds): bool {
                $hasHistoricalActivity = $movementReqIds->has($requisition->id)
                    || $issuedLineReqIds->has($requisition->id)
                    || in_array($requisition->status, [
                        RequisitionStatus::Issued,
                        RequisitionStatus::PartiallyIssued,
                        RequisitionStatus::Backordered,
                    ], true);

                return ! $hasHistoricalActivity;
            },
        );
    }

    public function hasHistoricalActivity(Requisition $requisition): bool
    {
        return StockMovement::query()->where('requisition_id', $requisition->id)->exists()
            || $requisition->lines()->where('qty_issued', '>', 0)->exists()
            || in_array($requisition->status, [
                RequisitionStatus::Issued,
                RequisitionStatus::PartiallyIssued,
                RequisitionStatus::Backordered,
            ], true);
    }
}
