<?php

namespace App\Services\Inventory;

use App\Enums\BookingStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\DashboardStatsCache;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class BookingWorkflowService
{
    public function __construct(
        private readonly AssetIntegrityService $assetIntegrity,
        private readonly NotificationService $notifications,
        private readonly DashboardStatsCache $dashboardStatsCache,
        private readonly BulkTrashService $bulkTrash,
    ) {}

    /**
     * Create a new asset booking request with row-level conflict checks.
     */
    public function createBooking(
        User $actor,
        int $assetId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
        ?string $purpose = null,
        ?string $ipAddress = null,
    ): Booking {
        $booking = DB::transaction(function () use ($actor, $assetId, $startAt, $endAt, $purpose, $ipAddress): Booking {
            $asset = Asset::query()
                ->whereKey($assetId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assetIntegrity->ensureBookable(
                asset: $asset,
                startAt: $startAt,
                endAt: $endAt,
            );

            $booking = new Booking([
                'asset_id' => $assetId,
                'requester_id' => $actor->id,
                'requester_position_id' => $actor->position_id,
                'start_at' => $startAt,
                'end_at' => $endAt,
                'purpose' => $purpose,
            ]);
            $booking->forceFill([
                'approver_id' => null,
                'approver_position_id' => null,
                'requested_ip_address' => $ipAddress,
                'approved_ip_address' => null,
                'status' => BookingStatus::Requested,
            ])->save();

            return $booking;
        });

        AuditLogService::logCreated($booking, "Booking created for asset #{$assetId}.");
        $this->dashboardStatsCache->invalidate();
        $booking->loadMissing(['asset', 'requester']);
        $this->notifications->bookingSubmitted($booking);

        return $booking;
    }

    /**
     * Approve a requested booking after re-checking schedule and asset integrity under row lock.
     */
    public function approveBooking(Booking $booking, User $actor, ?string $ipAddress = null): Booking
    {
        $approved = $this->approveBookingInTransaction($booking, $actor, $ipAddress);

        AuditLogService::logCustom('approve', "Booking #{$approved->id} approved.", $approved);
        $this->dashboardStatsCache->invalidate();
        $approved->loadMissing(['asset', 'requester']);
        $this->notifications->bookingStatusChanged($approved, 'approved');

        return $approved;
    }

    /**
     * Reject a requested booking inside a locked transaction.
     */
    public function rejectBooking(Booking $booking, User $actor, ?string $ipAddress = null): ?Booking
    {
        /** @var Booking|null $rejectedBooking */
        $rejectedBooking = DB::transaction(function () use ($booking, $actor, $ipAddress): ?Booking {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()->whereKey($booking->id)->lockForUpdate()->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::Requested) {
                return null;
            }

            $lockedBooking->forceFill([
                'status' => BookingStatus::Rejected,
                'approver_id' => $actor->id,
                'approver_position_id' => $actor->position_id,
                'approved_ip_address' => $ipAddress,
            ])->save();

            AuditLogService::logCustom('reject', "Booking #{$lockedBooking->id} rejected.", $lockedBooking);

            return $lockedBooking;
        });

        if (! $rejectedBooking instanceof Booking) {
            return null;
        }

        $this->dashboardStatsCache->invalidate();
        $rejectedBooking->loadMissing(['asset', 'requester']);
        $this->notifications->bookingStatusChanged($rejectedBooking, 'rejected');

        return $rejectedBooking;
    }

    /**
     * @param  list<int>  $ids
     * @return array{approved: int, conflicts: int, skipped: int}
     */
    public function bulkApprove(array $ids, User $actor, ?string $ipAddress = null): array
    {
        /** @var array{approved: array<int, Booking>, conflicts: int, skipped: int} $result */
        $result = DB::transaction(function () use ($ids, $actor, $ipAddress): array {
            $bookings = Booking::query()
                ->with(['asset', 'requester'])
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->sortBy('start_at')
                ->values();

            foreach ($bookings as $booking) {
                Gate::forUser($actor)->authorize('approve', $booking);
            }

            $approved = [];
            $conflicts = 0;
            $skipped = 0;

            foreach ($bookings as $booking) {
                if ($booking->status !== BookingStatus::Requested) {
                    $skipped++;

                    continue;
                }

                try {
                    $freshBooking = $this->approveBookingInTransaction($booking, $actor, $ipAddress);
                    AuditLogService::logCustom('approve', "Booking #{$booking->id} approved.", $freshBooking);
                    $approved[] = $freshBooking;
                } catch (ValidationException) {
                    $conflicts++;
                }
            }

            return ['approved' => $approved, 'conflicts' => $conflicts, 'skipped' => $skipped];
        });

        if ($result['approved'] !== []) {
            $this->dashboardStatsCache->invalidate();
        }

        foreach ($result['approved'] as $approvedBooking) {
            $approvedBooking->loadMissing(['asset', 'requester']);
            $this->notifications->bookingStatusChanged($approvedBooking, 'approved');
        }

        return [
            'approved' => count($result['approved']),
            'conflicts' => $result['conflicts'],
            'skipped' => $result['skipped'],
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array{rejected: int, skipped: int}
     */
    public function bulkReject(array $ids, User $actor, ?string $ipAddress = null): array
    {
        /** @var array{rejected: array<int, Booking>, skipped: int} $result */
        $result = DB::transaction(function () use ($ids, $actor, $ipAddress): array {
            $bookings = Booking::query()
                ->with('requester')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($bookings as $booking) {
                Gate::forUser($actor)->authorize('reject', $booking);
            }

            $rejected = [];
            $rejectedIds = [];
            $auditEntries = [];
            $skipped = 0;
            $now = now()->toImmutable();

            foreach ($bookings as $booking) {
                if ($booking->status === BookingStatus::Requested) {
                    $booking->forceFill([
                        'status' => BookingStatus::Rejected,
                        'approver_id' => $actor->id,
                        'approver_position_id' => $actor->position_id,
                        'approved_ip_address' => $ipAddress,
                    ]);
                    $rejectedIds[] = $booking->id;
                    $auditEntries[] = [
                        'action' => 'reject',
                        'description' => "Booking #{$booking->id} rejected.",
                        'model' => $booking,
                    ];
                    $rejected[] = $booking;
                } else {
                    $skipped++;
                }
            }

            if ($rejectedIds !== []) {
                Booking::query()
                    ->whereIn('id', $rejectedIds)
                    ->update([
                        'status' => BookingStatus::Rejected->value,
                        'approver_id' => $actor->id,
                        'approver_position_id' => $actor->position_id,
                        'approved_ip_address' => $ipAddress,
                        'updated_at' => $now,
                    ]);

                AuditLogService::logBatch($auditEntries);
            }

            return ['rejected' => $rejected, 'skipped' => $skipped];
        });

        if ($result['rejected'] !== []) {
            $this->dashboardStatsCache->invalidate();
        }

        foreach ($result['rejected'] as $rejectedBooking) {
            $rejectedBooking->loadMissing(['asset', 'requester']);
            $this->notifications->bookingStatusChanged($rejectedBooking, 'rejected');
        }

        return [
            'rejected' => count($result['rejected']),
            'skipped' => $result['skipped'],
        ];
    }

    public function softDelete(Booking $booking, ?User $actor, ?string $deletionReason = null): void
    {
        $trimmedReason = $deletionReason !== null ? trim($deletionReason) : null;

        $booking->forceFill([
            'deleted_by' => $actor?->id,
            'deletion_reason' => $trimmedReason !== '' ? $trimmedReason : null,
        ])->save();
        $booking->delete();

        AuditLogService::logDeleted($booking);
        $this->dashboardStatsCache->invalidate();
    }

    public function restore(Booking $booking): void
    {
        $booking->restore();

        AuditLogService::logRestored($booking);
        $this->dashboardStatsCache->invalidate();
    }

    public function forceDelete(Booking $booking): void
    {
        AuditLogService::logForceDeleted($booking);
        $booking->forceDelete();

        $this->dashboardStatsCache->invalidate();
    }

    /**
     * @param  list<int>  $ids
     */
    public function bulkRestore(array $ids, User $actor): int
    {
        return $this->bulkTrash->bulkRestore(
            Booking::class,
            $ids,
            $actor,
            fn (Booking $booking): string => $booking->id ? "#{$booking->id}" : '',
        );
    }

    /**
     * @param  list<int>  $ids
     * @return array{deleted: int, skipped: int}
     */
    public function bulkForceDelete(array $ids, User $actor): array
    {
        return $this->bulkTrash->bulkForceDelete(Booking::class, $ids, $actor);
    }

    private function approveBookingInTransaction(Booking $booking, User $actor, ?string $ipAddress = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $ipAddress): Booking {
            /** @var Booking $lockedBooking */
            $lockedBooking = Booking::query()
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBooking->status !== BookingStatus::Requested) {
                throw ValidationException::withMessages([
                    'action' => __('Only pending booking requests can be approved.'),
                ]);
            }

            if ($lockedBooking->end_at !== null && $lockedBooking->end_at->isPast()) {
                throw ValidationException::withMessages([
                    'start_at' => __('This request can no longer be approved because its schedule has already ended.'),
                ]);
            }

            $asset = Asset::query()
                ->whereKey($lockedBooking->asset_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assetIntegrity->ensureBookable(
                asset: $asset,
                startAt: $lockedBooking->start_at,
                endAt: $lockedBooking->end_at,
                ignoreBookingId: $lockedBooking->id,
            );

            $lockedBooking->forceFill([
                'status' => BookingStatus::Approved,
                'approver_id' => $actor->id,
                'approver_position_id' => $actor->position_id,
                'approved_ip_address' => $ipAddress,
            ])->save();

            $booking->refresh();

            return $lockedBooking->fresh() ?? $lockedBooking;
        });
    }
}
