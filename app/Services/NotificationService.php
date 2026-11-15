<?php

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\ExpiringStockAlertNotification;
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
     * Notify Admin and Supply Head users when a new requisition is submitted.
     */
    public function requisitionSubmitted(Requisition $requisition): void
    {
        $recipients = $this->usersWithRoles(['Admin', 'Supply Head'])
            ->reject(fn (User $user) => $user->id === $requisition->requester_id);

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
     * Notify Admin and Property Custodian users when a new booking is requested.
     */
    public function bookingSubmitted(Booking $booking): void
    {
        $recipients = $this->usersWithRoles(['Admin', 'Property Custodian'])
            ->reject(fn (User $user) => $user->id === $booking->requester_id);

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
     * Notify Admin and Supply Head users when a product drops below its reorder threshold.
     */
    public function lowStockAlert(Product $product, int $currentStock): void
    {
        $recipients = $this->usersWithRoles(['Admin', 'Supply Head']);

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
     * Notify Admin and Supply Head users when a stock lot is expiring soon.
     */
    public function expiringStockAlert(StockLot $lot, int $daysUntilExpiration): void
    {
        $recipients = $this->usersWithRoles(['Admin', 'Supply Head']);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->notifyUsers(
            $recipients,
            new ExpiringStockAlertNotification($lot, $daysUntilExpiration),
            NotificationEventType::ExpiringStock->value,
        );
    }

    /**
     * Notify Admin and Supply Head users when a forecast stockout alert is created for a product.
     */
    public function procurementRecommendation(
        Product $product,
        int $predictedDaysUntilStockout,
        int $recommendedReorderQty,
    ): void {
        $this->procurementRecommendationsBatch([
            [
                'product' => $product,
                'days_until_stockout' => $predictedDaysUntilStockout,
                'recommended_reorder_qty' => $recommendedReorderQty,
            ],
        ]);
    }

    /**
     * Send forecast stockout recommendations for a batch of products without repeated recipient or preference queries.
     *
     * @param  array<int, array{product: Product, days_until_stockout: int, recommended_reorder_qty: int}>  $items
     */
    public function procurementRecommendationsBatch(array $items): void
    {
        if ($items === []) {
            return;
        }

        $recipients = $this->usersWithRoles(['Admin', 'Supply Head']);

        if ($recipients->isEmpty()) {
            return;
        }

        $eventType = NotificationEventType::ProcurementRecommendation->value;
        $eligibleRecipients = $recipients
            ->filter(fn (User $user): bool => $this->shouldDeliverToUser($user, $eventType))
            ->values();

        if ($eligibleRecipients->isEmpty()) {
            return;
        }

        foreach ($items as $item) {
            NotificationFacade::send(
                $eligibleRecipients,
                new ProcurementRecommendationNotification(
                    $item['product'],
                    $item['days_until_stockout'],
                    $item['recommended_reorder_qty'],
                ),
            );
        }
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
     * @param  array<int, string>|string  $roles
     * @return Collection<int, User>
     */
    private function usersWithRoles(array|string $roles): Collection
    {
        $roleList = (array) $roles;

        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleList))
            ->with(['roles', 'notificationPreferences'])
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    private function usersWithRole(string $role): Collection
    {
        return $this->usersWithRoles([$role]);
    }
}
