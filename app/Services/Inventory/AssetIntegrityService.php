<?php

namespace App\Services\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AssetIntegrityService
{
    /**
     * @var list<string>
     */
    private const OPEN_MAINTENANCE_STATUSES = [
        MaintenanceStatus::Reported->value,
        MaintenanceStatus::Scheduled->value,
        MaintenanceStatus::InProgress->value,
    ];

    public function ensureBookable(
        Asset $asset,
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?int $ignoreBookingId = null,
    ): void {
        if ($asset->status !== AssetStatus::Available || $asset->lifecycle_status !== AssetLifecycleStatus::Active) {
            throw ValidationException::withMessages([
                'asset_id' => __('Only available assets can be booked.'),
            ]);
        }

        if ($this->hasPendingHandover($asset, lock: true)) {
            throw ValidationException::withMessages([
                'asset_id' => __('This asset has a handover pending verification.'),
            ]);
        }

        if ($this->hasPendingTransfer($asset, lock: true)) {
            throw ValidationException::withMessages([
                'asset_id' => __('This asset has a pending location transfer awaiting approval.'),
            ]);
        }

        $maintenanceConflict = AssetMaintenance::query()
            ->where('asset_id', $asset->id)
            ->where(function ($query) use ($startAt, $endAt): void {
                $query->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::InProgress->value,
                ])
                    ->orWhere(function ($query) use ($startAt, $endAt): void {
                        $query->where('status', MaintenanceStatus::Scheduled->value)
                            ->whereDate('scheduled_for', '>=', $startAt)
                            ->whereDate('scheduled_for', '<=', $endAt);
                    });
            })
            ->lockForUpdate()
            ->exists();

        if ($maintenanceConflict) {
            throw ValidationException::withMessages([
                'asset_id' => __('This asset is unavailable while it has open maintenance during the selected schedule.'),
            ]);
        }

        $conflictExists = Booking::query()
            ->blocking()
            ->forAssetWindow(
                assetId: $asset->id,
                startAt: $startAt,
                endAt: $endAt,
                ignoreBookingId: $ignoreBookingId,
            )
            ->lockForUpdate()
            ->exists();

        if ($conflictExists) {
            throw ValidationException::withMessages([
                'start_at' => __('This asset is already booked for part of the selected schedule. Choose different dates or select another asset.'),
            ]);
        }
    }

    public function ensureHandOverable(
        Asset $asset,
        string $errorKey = 'asset_tag_code',
        ?int $ignoreHandoverLogId = null,
        ?CarbonImmutable $effectiveAt = null,
    ): void {
        $blockers = $this->collectHandoverBlockers($asset, $ignoreHandoverLogId, $effectiveAt, lock: true, stopAtFirst: true);

        if ($blockers !== []) {
            throw ValidationException::withMessages([
                $errorKey => $blockers[0],
            ]);
        }
    }

    /**
     * Read-only preview of every reason an asset cannot be handed over right now.
     *
     * Used to explain eligibility before submission; ensureHandOverable() re-checks
     * the same rules under row locks when the handover is actually created or verified.
     *
     * @return list<string>
     */
    public function handoverBlockers(Asset $asset): array
    {
        return $this->collectHandoverBlockers($asset, null, null, lock: false, stopAtFirst: false);
    }

    public function ensureCanStartMaintenance(Asset $asset): void
    {
        if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
            throw new \RuntimeException('Only active assets can enter maintenance.');
        }

        if (! in_array($asset->status, [AssetStatus::Available, AssetStatus::Unserviceable], true)) {
            throw new \RuntimeException('The asset must be available or unserviceable before maintenance can begin.');
        }

        if ($this->hasFutureBlockingBooking($asset, lock: true)) {
            throw new \RuntimeException('This asset has an approved booking. Resolve the booking before starting maintenance.');
        }

        if ($this->hasPendingHandover($asset, lock: true)) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }

        if ($this->hasPendingTransfer($asset, lock: true)) {
            throw new \RuntimeException('This asset has a pending location transfer awaiting approval.');
        }
    }

    public function ensureCanReturnToStock(Asset $asset): void
    {
        if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
            throw new \RuntimeException('Only active assets can be returned to stock.');
        }

        if ($asset->position_id === null && $asset->status !== AssetStatus::CheckedOut) {
            throw new \RuntimeException('Only checked-out or custodian-assigned assets can be returned to stock.');
        }

        if ($this->hasPendingHandover($asset, lock: true)) {
            throw new \RuntimeException('Cancel or verify the pending handover before returning this asset to stock.');
        }

        if ($this->hasPendingTransfer($asset, lock: true)) {
            throw new \RuntimeException('Resolve the pending location transfer before returning this asset to stock.');
        }

        if ($this->hasFutureBlockingBooking($asset, lock: true)) {
            throw new \RuntimeException('This asset has an approved booking that must be resolved before returning to stock.');
        }

        if ($this->hasInProgressMaintenance($asset, lock: true)) {
            throw new \RuntimeException('Complete or cancel active repair work before returning this asset to stock.');
        }
    }

    public function ensureCanTransfer(Asset $asset, Location $destination, ?int $ignoreTransferId = null): void
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

        if ($this->hasPendingTransfer($asset, $ignoreTransferId, lock: true)) {
            throw new \RuntimeException('This asset already has a pending location transfer.');
        }

        if ($this->hasPendingHandover($asset, lock: true)) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }

        if ($this->hasFutureBlockingBooking($asset, lock: true)) {
            throw new \RuntimeException('This asset has an approved booking. Resolve the booking before transferring it.');
        }

        if ($this->hasOpenMaintenance($asset, lock: true)) {
            throw new \RuntimeException('Complete or cancel the open maintenance record before transferring this asset.');
        }
    }

    public function ensureNoOperationalConflicts(Asset $asset, bool $requireUnassigned = false): void
    {
        if ($requireUnassigned && $asset->position_id !== null) {
            throw new \RuntimeException('Complete the asset handover or return before retirement or disposal.');
        }

        if ($this->hasFutureBlockingBooking($asset, lock: true)) {
            throw new \RuntimeException('This asset has an approved booking that must be resolved first.');
        }

        if ($this->hasPendingHandover($asset, lock: true)) {
            throw new \RuntimeException('This asset has a handover waiting for verification.');
        }

        if ($this->hasPendingTransfer($asset, lock: true)) {
            throw new \RuntimeException('This asset has a pending location transfer.');
        }

        if ($this->hasOpenMaintenance($asset, lock: true)) {
            throw new \RuntimeException('Complete or cancel the open maintenance record first.');
        }
    }

    public function hasInProgressMaintenance(Asset $asset, bool $lock = false): bool
    {
        return AssetMaintenance::query()
            ->where('asset_id', $asset->id)
            ->where('status', MaintenanceStatus::InProgress->value)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    private function hasPendingHandover(Asset $asset, ?int $ignoreHandoverLogId = null, bool $lock = false): bool
    {
        return HandoverLog::query()
            ->where('asset_id', $asset->id)
            ->pending()
            ->when($ignoreHandoverLogId !== null, fn ($query) => $query->whereKeyNot($ignoreHandoverLogId))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    private function hasPendingTransfer(Asset $asset, ?int $ignoreTransferId = null, bool $lock = false): bool
    {
        return AssetTransfer::query()
            ->where('asset_id', $asset->id)
            ->where('status', AssetTransferStatus::Pending->value)
            ->when($ignoreTransferId !== null, fn ($query) => $query->whereKeyNot($ignoreTransferId))
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    private function hasOpenMaintenance(Asset $asset, bool $lock = false): bool
    {
        return AssetMaintenance::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', self::OPEN_MAINTENANCE_STATUSES)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    private function hasFutureBlockingBooking(Asset $asset, ?CarbonImmutable $effectiveAt = null, bool $lock = false): bool
    {
        return Booking::query()
            ->blocking()
            ->where('asset_id', $asset->id)
            ->where('end_at', '>', $effectiveAt ?? CarbonImmutable::now())
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();
    }

    /**
     * @return list<string>
     */
    private function collectHandoverBlockers(
        Asset $asset,
        ?int $ignoreHandoverLogId,
        ?CarbonImmutable $effectiveAt,
        bool $lock,
        bool $stopAtFirst,
    ): array {
        $blockers = [];

        if ($asset->lifecycle_status !== AssetLifecycleStatus::Active
            || in_array($asset->status, [AssetStatus::Condemned, AssetStatus::Unserviceable], true)) {
            $blockers[] = __('Only active assets can be handed over.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        if ($this->hasPendingHandover($asset, $ignoreHandoverLogId, $lock)) {
            $blockers[] = __('This asset already has a handover pending verification.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        if ($this->hasPendingTransfer($asset, lock: $lock)) {
            $blockers[] = __('This asset has a pending location transfer awaiting approval.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        if ($this->hasOpenMaintenance($asset, $lock)) {
            $blockers[] = __('This asset has scheduled or active maintenance and cannot be handed over.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        if ($this->hasFutureBlockingBooking($asset, $effectiveAt, $lock)) {
            $blockers[] = __('This asset is already committed to an approved booking and cannot be handed over right now.');
        }

        return $blockers;
    }
}
