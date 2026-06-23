<?php

namespace App\Notifications;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PurchaseOrderSentNotification extends Notification
{
    use Queueable;

    public function __construct(public PurchaseOrder $purchaseOrder) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $supplierName = $this->purchaseOrder->supplier?->name ?? __('Supplier');
        $poNumber = $this->purchaseOrder->po_number;

        return (new MailMessage)
            ->subject(__('Purchase order :po has been issued', ['po' => $poNumber]))
            ->greeting(__('Hello :supplier,', ['supplier' => $supplierName]))
            ->line(__('A new purchase order has been sent from :app.', ['app' => config('app.name')]))
            ->line(__('Purchase order number: :po', ['po' => $poNumber]))
            ->line(__('Expected delivery: :date', [
                'date' => $this->purchaseOrder->expected_delivery_at?->format('M d, Y h:i A') ?? __('Not specified'),
            ]))
            ->line(__('Total amount: :amount', [
                'amount' => number_format((float) $this->purchaseOrder->total_amount, 2),
            ]))
            ->line(__('Please coordinate with the requesting office for fulfillment details.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'procurement.purchase-order.sent',
            'category' => 'procurement',
            'severity' => 'info',
            'title' => __('Purchase order sent'),
            'message' => __(':po has been sent to :supplier.', [
                'po' => $this->purchaseOrder->po_number,
                'supplier' => $this->purchaseOrder->supplier?->name ?? __('supplier'),
            ]),
            'url' => route('inventory.purchase-orders.show', $this->purchaseOrder, absolute: false),
            'purchase_order_id' => $this->purchaseOrder->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
