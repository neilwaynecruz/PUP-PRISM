<?php

namespace App\Notifications;

use App\Enums\NotificationEventType;
use App\Models\StockLot;
use App\Notifications\Concerns\ResolvesViaNotificationPreferences;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpiringStockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use ResolvesViaNotificationPreferences;

    public int $tries = 3;

    public function __construct(
        public StockLot $lot,
        public int $daysUntilExpiration,
    ) {
        $this->onQueue('notifications');
    }

    public function notificationEventType(): string
    {
        return NotificationEventType::ExpiringStock->value;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $product = $this->lot->product;
        $productName = $product?->name ?? 'Unknown product';
        $sku = $product?->sku ?? 'N/A';
        $ref = $this->lot->reference_no ?? "Lot #{$this->lot->id}";
        $url = route('inventory.forecasting.show', $this->lot->product_id, absolute: false);

        $urgencyText = $this->daysUntilExpiration <= 0
            ? __('has already expired')
            : __('expires in :days day(s)', ['days' => $this->daysUntilExpiration]);

        return (new MailMessage)
            ->subject(__('Inventory alert: :sku lot is expiring soon', ['sku' => $sku]))
            ->line(__('Lot :ref of product :name (:sku) :urgency.', [
                'ref' => $ref,
                'name' => $productName,
                'sku' => $sku,
                'urgency' => $urgencyText,
            ]))
            ->line(__('Remaining quantity: :qty unit(s) · Expiration date: :date', [
                'qty' => $this->lot->qty_remaining,
                'date' => CarbonImmutable::parse((string) $this->lot->expires_at)->toDateString(),
            ]))
            ->action(__('Review inventory lot'), $url)
            ->line(__('Apply FEFO issuance or initiate stock adjustment to minimize waste.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $product = $this->lot->product;
        $sku = $product?->sku ?? 'N/A';
        $ref = $this->lot->reference_no ?? "Lot #{$this->lot->id}";
        $url = route('inventory.forecasting.show', $this->lot->product_id, absolute: false);

        $urgencyText = $this->daysUntilExpiration <= 0
            ? __('already expired')
            : __(':days day(s) left', ['days' => $this->daysUntilExpiration]);

        return [
            'event_type' => $this->notificationEventType(),
            'type' => 'inventory.expiring-stock',
            'category' => 'inventory',
            'severity' => $this->daysUntilExpiration <= 7 ? 'danger' : 'warning',
            'title' => __('Expiring stock alert'),
            'message' => __(':ref (:sku) has :qty unit(s) expiring (:urgency).', [
                'ref' => $ref,
                'sku' => $sku,
                'qty' => $this->lot->qty_remaining,
                'urgency' => $urgencyText,
            ]),
            'url' => $url,
            'stock_lot_id' => $this->lot->id,
            'product_id' => $this->lot->product_id,
            'days_until_expiration' => $this->daysUntilExpiration,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
