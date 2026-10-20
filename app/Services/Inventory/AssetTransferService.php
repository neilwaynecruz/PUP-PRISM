<?php

namespace App\Services\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Location;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetTransferService
{
    /** @param array<string, mixed> $payload */
    public function create(User $user, array $payload): AssetTransfer
    {
        return DB::transaction(function () use ($user, $payload): AssetTransfer {
            $asset = Asset::query()->whereKey($payload['asset_id'])->lockForUpdate()->firstOrFail();
            $destination = Location::query()->whereKey($payload['to_location_id'])->lockForUpdate()->firstOrFail();
            $this->ensureEligible($asset, $destination);

            $transfer = AssetTransfer::create([
                ...$payload,
                'from_location_id' => $asset->current_location_id,
                'from_position_id' => $asset->position_id,
                'status' => AssetTransferStatus::Pending,
                'initiated_by' => $user->id,
            ]);
            AuditLogService::logCreated($transfer, "Location transfer requested for asset {$asset->tag_code}.");

            return $transfer;
        });
    }

    public function approve(User $approver, AssetTransfer $transfer): AssetTransfer
    {
        return DB::transaction(function () use ($approver, $transfer): AssetTransfer {
            $transfer = AssetTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== AssetTransferStatus::Pending) {
                throw new \RuntimeException('Only pending transfers can be approved.');
            }
            if ($transfer->initiated_by === $approver->id) {
                throw new \RuntimeException('A transfer must be approved by someone other than the requester.');
            }

            $asset = Asset::query()->whereKey($transfer->asset_id)->lockForUpdate()->firstOrFail();
            $destination = Location::query()->whereKey($transfer->to_location_id)->lockForUpdate()->firstOrFail();
            if ($asset->current_location_id !== $transfer->from_location_id) {
                throw new \RuntimeException('The asset location changed after this request was created. Create a new transfer.');
            }
            $this->ensureEligible($asset, $destination, $transfer->id);

            $asset->update(['current_location_id' => $destination->id]);
            $transfer->update([
                'status' => AssetTransferStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('approve', "Asset {$asset->tag_code} transferred to {$destination->name}.", $transfer);

            return $transfer->fresh() ?? $transfer;
        });
    }

    public function reject(User $reviewer, AssetTransfer $transfer, ?string $notes): AssetTransfer
    {
        return DB::transaction(function () use ($reviewer, $transfer, $notes): AssetTransfer {
            $transfer = AssetTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== AssetTransferStatus::Pending) {
                throw new \RuntimeException('Only pending transfers can be rejected.');
            }

            $transfer->update(['status' => AssetTransferStatus::Rejected, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'notes' => $notes]);
            AuditLogService::logCustom('reject', 'Asset location transfer rejected.', $transfer);

            return $transfer->fresh() ?? $transfer;
        });
    }

    public function cancel(User $user, AssetTransfer $transfer, ?string $notes): AssetTransfer
    {
        return DB::transaction(function () use ($user, $transfer, $notes): AssetTransfer {
            $transfer = AssetTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($transfer->status !== AssetTransferStatus::Pending) {
                throw new \RuntimeException('Only pending transfers can be cancelled.');
            }
            if (! $user->hasRole('Admin') && $transfer->initiated_by !== $user->id) {
                throw new \RuntimeException("You don't have permission to cancel this transfer.");
            }

            $transfer->update([
                'status' => AssetTransferStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);
            AuditLogService::logCustom('cancel', 'Asset location transfer cancelled.', $transfer);

            return $transfer->fresh() ?? $transfer;
        });
    }

    private function ensureEligible(Asset $asset, Location $destination, ?int $ignoreTransferId = null): void
    {
        if (! $destination->is_active) {
            throw new \RuntimeException('Choose an active destination.');
        }
        if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
            throw new \RuntimeException('Only active assets can be transferred.');
        }
        if ($asset->current_location_id === $destination->id) {
            throw new \RuntimeException('The destination must be different from the current location.');
        }
        if (AssetTransfer::query()->where('asset_id', $asset->id)->where('status', AssetTransferStatus::Pending)->when(
            $ignoreTransferId,
            fn ($query) => $query->whereKeyNot($ignoreTransferId),
        )->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset already has a pending location transfer.');
        }
        if (HandoverLog::query()->where('asset_id', $asset->id)->whereNull('verified_at')->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }
        if (Booking::query()->blocking()->where('asset_id', $asset->id)->where('end_at', '>', now())->lockForUpdate()->exists()) {
            throw new \RuntimeException('This asset has an approved booking. Resolve the booking before transferring it.');
        }
        if (AssetMaintenance::query()->where('asset_id', $asset->id)->whereIn('status', [
            MaintenanceStatus::Reported->value,
            MaintenanceStatus::Scheduled->value,
            MaintenanceStatus::InProgress->value,
        ])->lockForUpdate()->exists()) {
            throw new \RuntimeException('Complete or cancel the open maintenance record before transferring this asset.');
        }
    }
}
