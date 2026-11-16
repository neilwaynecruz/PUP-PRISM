<?php

namespace App\Services\Inventory;

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\StockMovementReasonCode;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\InventoryRealtimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetLifecycleService
{
    public function __construct(private readonly AssetIntegrityService $integrity) {}

    /** @param array<string, mixed> $payload */
    public function propose(User $user, array $payload): AssetLifecycleEvent
    {
        return DB::transaction(function () use ($user, $payload): AssetLifecycleEvent {
            $asset = Asset::query()->whereKey($payload['asset_id'])->lockForUpdate()->firstOrFail();
            if (AssetLifecycleEvent::query()->where('asset_id', $asset->id)->where('status', ApprovalStatus::Pending->value)->lockForUpdate()->exists()) {
                throw new \RuntimeException('This asset already has a lifecycle change waiting for review.');
            }

            $eventType = AssetLifecycleEventType::from($payload['event_type']);
            $toLifecycleStatus = match ($eventType) {
                AssetLifecycleEventType::Retirement => AssetLifecycleStatus::Retired,
                AssetLifecycleEventType::Disposal => AssetLifecycleStatus::Disposed,
                default => $asset->lifecycle_status,
            };

            if ($eventType === AssetLifecycleEventType::Retirement && $asset->lifecycle_status !== AssetLifecycleStatus::Active) {
                throw new \RuntimeException('Only active assets can be proposed for retirement.');
            }
            if ($eventType === AssetLifecycleEventType::Disposal && $asset->lifecycle_status !== AssetLifecycleStatus::Retired) {
                throw new \RuntimeException('Retire the asset before proposing its disposal.');
            }
            if ($eventType === AssetLifecycleEventType::ConditionChange && $asset->lifecycle_status !== AssetLifecycleStatus::Active) {
                throw new \RuntimeException('Condition changes can only be recorded for active assets.');
            }

            $event = new AssetLifecycleEvent;
            $event->fill($payload);
            $event->forceFill([
                'event_type' => $eventType,
                'status' => ApprovalStatus::Pending,
                'from_lifecycle_status' => $asset->lifecycle_status,
                'to_lifecycle_status' => $toLifecycleStatus,
                'from_condition_status' => $asset->condition_status,
                'to_condition_status' => $payload['condition_status'] ?? $asset->condition_status,
                'initiated_by' => $user->id,
            ])->save();
            AuditLogService::logCreated($event, "Lifecycle change proposed for asset {$asset->tag_code}.");

            return $event;
        });
    }

    public function approve(User $approver, AssetLifecycleEvent $event): AssetLifecycleEvent
    {
        return DB::transaction(function () use ($approver, $event): AssetLifecycleEvent {
            $event = AssetLifecycleEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->status !== ApprovalStatus::Pending) {
                throw new \RuntimeException('Only pending lifecycle changes can be approved.');
            }
            if ($event->initiated_by === $approver->id) {
                throw new \RuntimeException('A lifecycle change must be approved by someone other than the requester.');
            }

            $asset = Asset::query()->whereKey($event->asset_id)->lockForUpdate()->firstOrFail();
            if ($asset->lifecycle_status !== $event->from_lifecycle_status
                || $asset->condition_status !== $event->from_condition_status) {
                throw new \RuntimeException('The asset changed after this request was created. Review and submit a new request.');
            }

            if (in_array($event->event_type, [AssetLifecycleEventType::Retirement, AssetLifecycleEventType::Disposal], true)) {
                $this->integrity->ensureNoOperationalConflicts($asset, requireUnassigned: true);
            }
            if ($event->event_type === AssetLifecycleEventType::ConditionChange
                && $this->integrity->hasInProgressMaintenance($asset, lock: true)) {
                throw new \RuntimeException('Complete or cancel the active in-progress maintenance record before changing this asset condition.');
            }

            $nextStatus = $asset->status;
            if (in_array($event->to_lifecycle_status, [AssetLifecycleStatus::Retired, AssetLifecycleStatus::Disposed], true)) {
                $nextStatus = AssetStatus::Condemned;
            } elseif ($event->event_type === AssetLifecycleEventType::ConditionChange) {
                $nextStatus = $event->to_condition_status === AssetConditionStatus::Serviceable
                    ? ($asset->position_id === null ? AssetStatus::Available : AssetStatus::CheckedOut)
                    : AssetStatus::Unserviceable;
            }

            $asset->update([
                'lifecycle_status' => $event->to_lifecycle_status,
                'condition_status' => $event->to_condition_status,
                'status' => $nextStatus,
            ]);
            $event->forceFill([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => CarbonImmutable::now(),
                'occurred_at' => CarbonImmutable::now(),
            ])->save();
            AuditLogService::logCustom('approve', "Lifecycle change approved for asset {$asset->tag_code}.", $event);
            app(InventoryRealtimeService::class)->assetOperationUpdated($asset->id, 'lifecycle_approved');

            return $event->fresh() ?? $event;
        });
    }

    public function reject(User $reviewer, AssetLifecycleEvent $event, ?string $notes): AssetLifecycleEvent
    {
        return DB::transaction(function () use ($reviewer, $event, $notes): AssetLifecycleEvent {
            $event = AssetLifecycleEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if ($event->status !== ApprovalStatus::Pending) {
                throw new \RuntimeException('Only pending lifecycle changes can be rejected.');
            }

            $event->forceFill(['status' => ApprovalStatus::Rejected, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'notes' => $notes])->save();
            AuditLogService::logCustom('reject', 'Asset lifecycle change rejected.', $event);

            return $event->fresh() ?? $event;
        });
    }

    /**
     * Check a custodian-assigned / checked-out asset back into stock atomically.
     *
     * @param  array{location_id?: int|null, condition_status?: string|null, notes?: string|null}  $payload
     */
    public function returnToStock(
        User $user,
        Asset $asset,
        array $payload = [],
        ?string $ipAddress = null,
    ): Asset {
        return DB::transaction(function () use ($user, $asset, $payload, $ipAddress): Asset {
            /** @var Asset $lockedAsset */
            $lockedAsset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $this->integrity->ensureCanReturnToStock($lockedAsset);

            $targetLocationId = $lockedAsset->current_location_id;

            if (! empty($payload['location_id'])) {
                /** @var Location $location */
                $location = Location::query()
                    ->whereKey((int) $payload['location_id'])
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $targetLocationId = $location->id;
            }

            $nextCondition = ! empty($payload['condition_status'])
                ? AssetConditionStatus::from((string) $payload['condition_status'])
                : ($lockedAsset->condition_status ?? AssetConditionStatus::Serviceable);

            $nextStatus = $nextCondition === AssetConditionStatus::Serviceable
                ? AssetStatus::Available
                : AssetStatus::Unserviceable;

            $previousPositionId = $lockedAsset->position_id;
            $oldValues = $lockedAsset->only(['position_id', 'current_location_id', 'condition_status', 'status']);

            $lockedAsset->update([
                'position_id' => null,
                'current_location_id' => $targetLocationId,
                'condition_status' => $nextCondition,
                'status' => $nextStatus,
            ]);

            $notes = trim((string) ($payload['notes'] ?? ''));

            StockMovement::create([
                'movement_type' => 'return',
                'reason_code' => StockMovementReasonCode::ReturnToStock->value,
                'product_id' => $lockedAsset->product_id,
                'stock_lot_id' => null,
                'asset_id' => $lockedAsset->id,
                'requisition_id' => null,
                'qty_delta' => 1,
                'qty_before' => 0,
                'qty_after' => 1,
                'performed_by' => $user->id,
                'accountable_position_id' => $previousPositionId,
                'ip_address' => $ipAddress,
                'performed_at' => CarbonImmutable::now(),
                'notes' => $notes !== '' ? $notes : "Asset {$lockedAsset->tag_code} returned to stock.",
            ]);

            AuditLogService::logUpdated(
                $lockedAsset,
                $oldValues,
                "Asset {$lockedAsset->tag_code} checked back in to stock.",
            );

            return $lockedAsset->fresh() ?? $lockedAsset;
        });
    }
}
