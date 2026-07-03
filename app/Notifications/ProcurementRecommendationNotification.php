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

class ProcurementRecommendationNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use ResolvesViaNotificationPreferences;

    public int $tries = 3;

    public function __construct(
        public Product $product,
        public int $predictedDaysUntilStockout,
        public int $recommendedReorderQty,
    ) {
        $this->onQueue('notifications');
    }

    public function notificationEventType(): string
    {
        return NotificationEventType::ProcurementRecommendation->value;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('inventory.forecasting.show', $this->product, absolute: false);

        return (new MailMessage)
            ->subject(__('Procurement recommendation: :sku forecast stockout', ['sku' => $this->product->sku]))
            ->line(__('Product :name (:sku) is forecast to stock out in :days day(s) while still above its reorder threshold.', [
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'days' => $this->predictedDaysUntilStockout,
            ]))
            ->line(__('Recommended reorder quantity: :qty unit(s).', [
                'qty' => $this->recommendedReorderQty,
            ]))
            ->action(__('Review forecast'), $url)
            ->line(__('Consider generating draft purchase orders before stock drops below the reorder threshold.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $url = route('inventory.forecasting.show', $this->product, absolute: false);

        return [
            'event_type' => $this->notificationEventType(),
            'type' => 'inventory.procurement-recommendation',
            'category' => 'inventory',
            'severity' => 'warning',
            'title' => __('Forecast procurement recommendation'),
            'message' => __(':sku may stock out in :days day(s). Reorder :qty unit(s).', [
                'sku' => $this->product->sku,
                'days' => $this->predictedDaysUntilStockout,
                'qty' => $this->recommendedReorderQty,
            ]),
            'url' => $url,
            'product_id' => $this->product->id,
            'predicted_days_until_stockout' => $this->predictedDaysUntilStockout,
            'recommended_reorder_qty' => $this->recommendedReorderQty,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
