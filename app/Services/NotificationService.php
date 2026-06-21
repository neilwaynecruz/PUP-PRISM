<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotificationService
{
    public function __construct(
        private readonly InventoryRealtimeService $realtime,
    ) {}

    /**
     * Notify Supply Head users when a new requisition is submitted.
     */
    public function requisitionSubmitted(Requisition $requisition): void
    {
        $recipients = $this->usersWithRole('Supply Head');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new RequisitionSubmittedNotification($requisition),
        );

        $this->realtime->requisitionSubmitted($requisition);
    }

    /**
     * Notify the requester when their requisition status changes.
     */
    public function requisitionStatusChanged(Requisition $requisition, string $action): void
    {
        $requester = $requisition->requester;

        if (! $requester instanceof User) {
            return;
        }

        $requester->notify(
            new RequisitionStatusChangedNotification($requisition, $action),
        );

        $this->realtime->requisitionStatusChanged($requisition, $action);
    }

    /**
     * Notify Property Custodian users when a new booking is requested.
     */
    public function bookingSubmitted(Booking $booking): void
    {
        $recipients = $this->usersWithRole('Property Custodian');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new BookingSubmittedNotification($booking),
        );

        $this->realtime->bookingSubmitted($booking);
    }

    /**
     * Notify the requester when their booking status changes.
     */
    public function bookingStatusChanged(Booking $booking, string $action): void
    {
        $requester = $booking->requester;

        if (! $requester instanceof User) {
            return;
        }

        $requester->notify(
            new BookingStatusChangedNotification($booking, $action),
        );

        $this->realtime->bookingStatusChanged($booking, $action);
    }

    /**
     * Notify Supply Head users when a product drops below its reorder threshold.
     */
    public function lowStockAlert(Product $product, int $currentStock): void
    {
        $recipients = $this->usersWithRole('Supply Head');

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send(
            $recipients,
            new LowStockAlertNotification($product, $currentStock),
        );
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRole(string $role): Collection
    {
        return User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', $role))
            ->get();
    }
}
