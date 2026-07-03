<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatus;
use App\Models\Asset;
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
        if ($asset->status !== AssetStatus::Available) {
            throw ValidationException::withMessages([
                'asset_id' => __('Only available assets can be booked.'),
            ]);
        }

        $pendingHandoverExists = HandoverLog::query()
            ->where('asset_id', $asset->id)
            ->whereNull('verified_at')
            ->lockForUpdate()
            ->exists();

        if ($pendingHandoverExists) {
            throw ValidationException::withMessages([
                'asset_id' => __('This asset has a handover pending verification.'),
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
                'start_at' => __('This asset is already booked for the selected schedule.'),
            ]);
        }
    }

    public function ensureHandOverable(
        Asset $asset,
        string $errorKey = 'asset_tag_code',
        ?int $ignoreHandoverLogId = null,
        ?CarbonImmutable $effectiveAt = null,
    ): void {
        if (in_array($asset->status, [AssetStatus::Condemned, AssetStatus::Unserviceable], true)) {
            throw ValidationException::withMessages([
                $errorKey => __('Only active assets can be handed over.'),
            ]);
        }

        $pendingHandoverExists = HandoverLog::query()
            ->where('asset_id', $asset->id)
            ->whereNull('verified_at')
            ->when(
                $ignoreHandoverLogId !== null,
                fn ($query) => $query->whereKeyNot($ignoreHandoverLogId),
            )
            ->lockForUpdate()
            ->exists();

        if ($pendingHandoverExists) {
            throw ValidationException::withMessages([
                $errorKey => __('This asset already has a handover pending verification.'),
            ]);
        }

        $effectiveAt ??= CarbonImmutable::now();

        $futureBookingExists = Booking::query()
            ->blocking()
            ->where('asset_id', $asset->id)
            ->where('end_at', '>', $effectiveAt)
            ->lockForUpdate()
            ->exists();

        if ($futureBookingExists) {
            throw ValidationException::withMessages([
                $errorKey => __('This asset is already committed to an approved booking and cannot be handed over right now.'),
            ]);
        }
    }
}
