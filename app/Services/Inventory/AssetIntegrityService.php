<?php

namespace App\Services\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Booking;
use App\Models\HandoverLog;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class AssetIntegrityService
{
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

        $pendingHandoverExists = HandoverLog::query()
            ->where('asset_id', $asset->id)
            ->pending()
            ->lockForUpdate()
            ->exists();

        if ($pendingHandoverExists) {
            throw ValidationException::withMessages([
                'asset_id' => __('This asset has a handover pending verification.'),
            ]);
        }

        $maintenanceConflict = AssetMaintenance::query()
            ->where('asset_id', $asset->id)
            ->where(function ($query) use ($startAt, $endAt) {
                $query->whereIn('status', [
                    MaintenanceStatus::Reported->value,
                    MaintenanceStatus::InProgress->value,
                ])
                    ->orWhere(function ($query) use ($startAt, $endAt) {
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

        $pendingHandoverExists = HandoverLog::query()
            ->where('asset_id', $asset->id)
            ->pending()
            ->when(
                $ignoreHandoverLogId !== null,
                fn ($query) => $query->whereKeyNot($ignoreHandoverLogId),
            )
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();

        if ($pendingHandoverExists) {
            $blockers[] = __('This asset already has a handover pending verification.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        $maintenanceConflict = AssetMaintenance::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', [
                MaintenanceStatus::Reported->value,
                MaintenanceStatus::Scheduled->value,
                MaintenanceStatus::InProgress->value,
            ])
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();

        if ($maintenanceConflict) {
            $blockers[] = __('This asset has scheduled or active maintenance and cannot be handed over.');

            if ($stopAtFirst) {
                return $blockers;
            }
        }

        $effectiveAt ??= CarbonImmutable::now();

        $futureBookingExists = Booking::query()
            ->blocking()
            ->where('asset_id', $asset->id)
            ->where('end_at', '>', $effectiveAt)
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->exists();

        if ($futureBookingExists) {
            $blockers[] = __('This asset is already committed to an approved booking and cannot be handed over right now.');
        }

        return $blockers;
    }
}
