<?php

namespace App\Services\Inventory;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\Position;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\InventoryRealtimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AssetTransferService
{
    public function __construct(private readonly AssetIntegrityService $integrity) {}

    /** @param array<string, mixed> $payload */
    public function create(User $user, array $payload): AssetTransfer
    {
        return DB::transaction(function () use ($user, $payload): AssetTransfer {
            $asset = Asset::query()->whereKey($payload['asset_id'])->lockForUpdate()->firstOrFail();
            $destination = Location::query()->whereKey($payload['to_location_id'])->lockForUpdate()->firstOrFail();
            $this->integrity->ensureCanTransfer($asset, $destination);

            if (! empty($payload['to_position_id'])) {
                $targetPosition = Position::query()
                    ->whereKey((int) $payload['to_position_id'])
                    ->where('is_active', true)
                    ->first();

                if (! $targetPosition instanceof Position) {
                    throw new \RuntimeException('Select an active custodian position for the destination.');
                }
            }

            $transfer = new AssetTransfer;
            $transfer->fill($payload);
            $transfer->forceFill([
                'from_location_id' => $asset->current_location_id,
                'from_position_id' => $asset->position_id,
                'status' => AssetTransferStatus::Pending,
                'initiated_by' => $user->id,
            ])->save();
            AuditLogService::logCreated($transfer, "Location transfer requested for asset {$asset->tag_code}.");
            app(InventoryRealtimeService::class)->assetOperationUpdated($asset->id, 'transfer_requested');

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
            if ($asset->current_location_id !== $transfer->from_location_id
                || $asset->position_id !== $transfer->from_position_id) {
                throw new \RuntimeException('The asset location or assignment changed after this request was created. Create a new transfer.');
            }
            $this->integrity->ensureCanTransfer($asset, $destination, $transfer->id);

            $nextPositionId = $transfer->to_position_id;
            if ($nextPositionId !== null) {
                $targetPosition = Position::query()
                    ->whereKey($nextPositionId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->first();

                if (! $targetPosition instanceof Position) {
                    throw new \RuntimeException('The destination position is inactive. Reject or cancel this transfer.');
                }
            }

            $nextStatus = $asset->status;
            if ($asset->condition_status === AssetConditionStatus::Serviceable
                && in_array($asset->status, [AssetStatus::Available, AssetStatus::CheckedOut], true)) {
                $nextStatus = $nextPositionId !== null
                    ? AssetStatus::CheckedOut
                    : AssetStatus::Available;
            }

            $asset->update([
                'current_location_id' => $destination->id,
                'position_id' => $nextPositionId,
                'status' => $nextStatus,
            ]);
            $transfer->forceFill([
                'status' => AssetTransferStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => CarbonImmutable::now(),
            ])->save();
            AuditLogService::logCustom('approve', "Asset {$asset->tag_code} transferred to {$destination->name}.", $transfer);
            app(InventoryRealtimeService::class)->assetOperationUpdated($asset->id, 'transfer_approved');

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

            $transfer->forceFill(['status' => AssetTransferStatus::Rejected, 'approved_by' => $reviewer->id, 'approved_at' => now(), 'notes' => $notes])->save();
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

            $transfer->forceFill([
                'status' => AssetTransferStatus::Cancelled,
                'cancelled_by' => $user->id,
                'cancelled_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ])->save();
            AuditLogService::logCustom('cancel', 'Asset location transfer cancelled.', $transfer);

            return $transfer->fresh() ?? $transfer;
        });
    }
}
