<?php

namespace App\Services\Inventory;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetMaintenanceService
{
    /** @param array<string, mixed> $payload */
    public function create(User $user, array $payload): AssetMaintenance
    {
        return DB::transaction(function () use ($user, $payload): AssetMaintenance {
            $asset = Asset::query()->whereKey($payload['asset_id'])->lockForUpdate()->firstOrFail();
            if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
                throw new \RuntimeException('Maintenance can only be recorded for active assets.');
            }

            $hasOpenRecord = AssetMaintenance::query()
                ->where('asset_id', $asset->id)
                ->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::Scheduled->value,
                    MaintenanceStatus::InProgress->value,
                ])
                ->lockForUpdate()
                ->exists();
            if ($hasOpenRecord) {
                throw new \RuntimeException('This asset already has an open maintenance record.');
            }

            $maintenance = AssetMaintenance::create([
                ...$payload,
                'status' => MaintenanceStatus::Reported,
                'reported_by' => $user->id,
            ]);
            AuditLogService::logCreated($maintenance, "Maintenance request created for asset {$asset->tag_code}.");

            return $maintenance;
        });
    }

    /** @param array<string, mixed> $payload */
    public function transition(AssetMaintenance $maintenance, MaintenanceStatus $nextStatus, array $payload): AssetMaintenance
    {
        return DB::transaction(function () use ($maintenance, $nextStatus, $payload): AssetMaintenance {
            $maintenance = AssetMaintenance::query()->whereKey($maintenance->id)->lockForUpdate()->firstOrFail();
            $asset = Asset::query()->whereKey($maintenance->asset_id)->lockForUpdate()->firstOrFail();

            $allowed = match ($maintenance->status) {
                MaintenanceStatus::Reported => [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress, MaintenanceStatus::Cancelled],
                MaintenanceStatus::Scheduled => [MaintenanceStatus::InProgress, MaintenanceStatus::Cancelled],
                MaintenanceStatus::InProgress => [MaintenanceStatus::Completed, MaintenanceStatus::Cancelled],
                default => [],
            };
            if (! in_array($nextStatus, $allowed, true)) {
                throw new \RuntimeException("Maintenance cannot move from {$maintenance->status->value} to {$nextStatus->value}.");
            }

            if ($nextStatus === MaintenanceStatus::Scheduled
                && empty($payload['scheduled_for'])
                && $maintenance->scheduled_for === null) {
                throw new \RuntimeException('Choose a service date before scheduling maintenance.');
            }

            if ($nextStatus === MaintenanceStatus::InProgress) {
                $this->ensureAssetCanEnterMaintenance($asset);
                $asset->update(['status' => AssetStatus::Unserviceable]);
            }

            if (in_array($nextStatus, [MaintenanceStatus::Completed, MaintenanceStatus::Cancelled], true)
                && $maintenance->status === MaintenanceStatus::InProgress
                && $asset->lifecycle_status === AssetLifecycleStatus::Active) {
                $asset->update([
                    'status' => $asset->position_id === null ? AssetStatus::Available : AssetStatus::CheckedOut,
                    'condition_status' => $nextStatus === MaintenanceStatus::Completed
                        ? AssetConditionStatus::Serviceable
                        : $asset->condition_status,
                ]);
            }

            $oldValues = $maintenance->toArray();
            $maintenance->update([
                'status' => $nextStatus,
                'assigned_to' => $payload['assigned_to'] ?? $maintenance->assigned_to,
                'service_provider' => $payload['service_provider'] ?? $maintenance->service_provider,
                'scheduled_for' => $payload['scheduled_for'] ?? $maintenance->scheduled_for,
                'repair_cost' => $payload['repair_cost'] ?? $maintenance->repair_cost,
                'findings' => $payload['findings'] ?? $maintenance->findings,
                'notes' => $payload['notes'] ?? $maintenance->notes,
                'completed_at' => $nextStatus === MaintenanceStatus::Completed ? CarbonImmutable::now() : null,
            ]);
            AuditLogService::logUpdated($maintenance, $oldValues, "Maintenance status changed to {$nextStatus->value}.");

            return $maintenance->fresh() ?? $maintenance;
        });
    }

    private function ensureAssetCanEnterMaintenance(Asset $asset): void
    {
        if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
            throw new \RuntimeException('Only active assets can enter maintenance.');
        }

        if ($asset->status !== AssetStatus::Available) {
            throw new \RuntimeException('The asset must be available before maintenance can begin.');
        }

        if (Booking::query()->blocking()->where('asset_id', $asset->id)->where('end_at', '>', now())->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has an approved booking. Resolve the booking before starting maintenance.');
        }

        if (HandoverLog::query()->where('asset_id', $asset->id)->whereNull('verified_at')->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }
    }
}
