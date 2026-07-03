<?php

namespace App\Notifications;

use App\Enums\NotificationEventType;
use App\Models\Product;
use App\Notifications\Concerns\ResolvesViaNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use ResolvesViaNotificationPreferences;

    public int $tries = 3;

    public function __construct(
        public Product $product,
        public int $currentStock,
    ) {
        $this->onQueue('notifications');
    }

    public function notificationEventType(): string
    {
        return NotificationEventType::LowStock->value;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $threshold = $this->product->reorder_threshold ?? 0;
        $url = route('inventory.products.index', absolute: false);

        return (new MailMessage)
            ->subject(__('Inventory alert: :sku stock is low', ['sku' => $this->product->sku]))
            ->line(__('Product :name (:sku) has dropped below its reorder threshold.', [
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]))
            ->line(__('Current stock: :current / Reorder threshold: :threshold', [
                'current' => $this->currentStock,
                'threshold' => $threshold,
            ]))
            ->action(__('View products'), $url)
            ->line(__('Please reorder this item to avoid stockouts.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = route('inventory.products.show', $this->product, absolute: false);

        return [
            'event_type' => $this->notificationEventType(),
            'type' => 'inventory.low-stock',
            'category' => 'inventory',
            'severity' => 'warning',
            'title' => __('Low stock alert'),
            'message' => __(':sku is down to :current on hand.', [
                'sku' => $this->product->sku,
                'current' => $this->currentStock,
            ]),
            'url' => $url,
            'product_id' => $this->product->id,
            'current_stock' => $this->currentStock,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
