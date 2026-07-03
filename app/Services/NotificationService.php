<?php

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\ProcurementRecommendationNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class NotificationService
{
    public function __construct(
        private readonly InventoryRealtimeService $realtime,
        private readonly NotificationPreferenceResolver $preferenceResolver,
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

        $this->notifyUsers(
            $recipients,
            new RequisitionSubmittedNotification($requisition),
            NotificationEventType::RequisitionSubmitted->value,
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

        $this->notifyUser(
            $requester,
            new RequisitionStatusChangedNotification($requisition, $action),
            NotificationEventType::RequisitionStatusChanged->value,
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

        $this->notifyUsers(
            $recipients,
            new BookingSubmittedNotification($booking),
            NotificationEventType::BookingSubmitted->value,
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

        $this->notifyUser(
            $requester,
            new BookingStatusChangedNotification($booking, $action),
            NotificationEventType::BookingStatusChanged->value,
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

        $this->notifyUsers(
            $recipients,
            new LowStockAlertNotification($product, $currentStock),
            NotificationEventType::LowStock->value,
        );
    }

    /**
     * Notify Supply Head users when a forecast stockout alert is created for a product.
     */
    public function procurementRecommendation(
        Product $product,
        int $predictedDaysUntilStockout,
        int $recommendedReorderQty,
    ): void {
        $recipients = $this->usersWithRole('Supply Head');

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifyUsers(
            $recipients,
            new ProcurementRecommendationNotification(
                $product,
                $predictedDaysUntilStockout,
                $recommendedReorderQty,
            ),
            NotificationEventType::ProcurementRecommendation->value,
        );
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function notifyUsers(Collection $users, Notification $notification, string $eventType): void
    {
        foreach ($users as $user) {
            $this->notifyUser($user, $notification, $eventType);
        }
    }

    private function notifyUser(User $user, Notification $notification, string $eventType): void
    {
        if (! $this->shouldDeliverToUser($user, $eventType)) {
            return;
        }

        NotificationFacade::send($user, $notification);
    }

    private function shouldDeliverToUser(User $user, string $eventType): bool
    {
        if ($this->preferenceResolver->instantChannels($user, $eventType) !== []) {
            return true;
        }

        return $this->preferenceResolver->usesDailyDigest($user, $eventType);
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
