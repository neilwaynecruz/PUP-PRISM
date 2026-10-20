<?php

namespace App\Services\Inventory;

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetLifecycleService
{
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

            $event = AssetLifecycleEvent::create([
                ...$payload,
                'event_type' => $eventType,
                'status' => ApprovalStatus::Pending,
                'from_lifecycle_status' => $asset->lifecycle_status,
                'to_lifecycle_status' => $toLifecycleStatus,
                'from_condition_status' => $asset->condition_status,
                'to_condition_status' => $payload['condition_status'] ?? $asset->condition_status,
                'initiated_by' => $user->id,
            ]);
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
                $this->ensureNoOperationalConflicts($asset);
            }
            if ($event->event_type === AssetLifecycleEventType::ConditionChange) {
                $this->ensureNoOpenMaintenance($asset);
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
            $event->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => CarbonImmutable::now(),
                'occurred_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('approve', "Lifecycle change approved for asset {$asset->tag_code}.", $event);

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

            $event->update(['status' => ApprovalStatus::Rejected, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'notes' => $notes]);
            AuditLogService::logCustom('reject', 'Asset lifecycle change rejected.', $event);

            return $event->fresh() ?? $event;
        });
    }

    private function ensureNoOperationalConflicts(Asset $asset): void
    {
        if ($asset->position_id !== null) {
            throw new \RuntimeException('Complete the asset handover or return before retirement or disposal.');
        }
        if (Booking::query()->blocking()->where('asset_id', $asset->id)->where('end_at', '>', now())->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has an approved booking that must be resolved first.');
        }
        if (HandoverLog::query()->where('asset_id', $asset->id)->whereNull('verified_at')->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }
        if (AssetTransfer::query()->where('asset_id', $asset->id)->where('status', AssetTransferStatus::Pending->value)->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has a pending location transfer.');
        }
        if (AssetMaintenance::query()->where('asset_id', $asset->id)->whereIn('status', [
            MaintenanceStatus::Reported->value,
            MaintenanceStatus::Scheduled->value,
            MaintenanceStatus::InProgress->value,
        ])->lockForUpdate()->exists()) {
            throw new \RuntimeException('Complete or cancel the open maintenance record first.');
        }
    }

    private function ensureNoOpenMaintenance(Asset $asset): void
    {
        if (AssetMaintenance::query()->where('asset_id', $asset->id)->whereIn('status', [
            MaintenanceStatus::Reported->value,
            MaintenanceStatus::Scheduled->value,
            MaintenanceStatus::InProgress->value,
        ])->lockForUpdate()->exists()) {
            throw new \RuntimeException('Complete or cancel the open maintenance record before changing this asset condition.');
        }
    }
}
