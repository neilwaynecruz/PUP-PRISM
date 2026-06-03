<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlertNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Product $product,
        public int $currentStock,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
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
        return [
            'product_id' => $this->product->id,
            'current_stock' => $this->currentStock,
        ];
    }
}
