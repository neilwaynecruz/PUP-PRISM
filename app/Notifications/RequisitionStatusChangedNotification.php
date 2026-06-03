<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisitionStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Requisition $requisition,
        public string $action,
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
        $status = match ($this->action) {
            'approved' => __('approved'),
            'rejected' => __('rejected'),
            'issued' => __('issued and ready for pickup'),
            default => __('updated'),
        };

        $subject = match ($this->action) {
            'approved' => __('Your requisition has been approved'),
            'rejected' => __('Your requisition has been rejected'),
            'issued' => __('Your requisition is ready for pickup'),
            default => __('Your requisition status changed'),
        };

        $url = route('inventory.requisitions.show', $this->requisition, absolute: false);

        $message = (new MailMessage)
            ->subject($subject)
            ->line(__('Requisition #:id has been :status.', [
                'id' => $this->requisition->id,
                'status' => $status,
            ]));

        if ($this->action === 'rejected' && $this->requisition->notes) {
            $message->line(__('Reason: :reason', ['reason' => $this->requisition->notes]));
        }

        $message->action(__('View requisition'), $url)
            ->line(__('This is an automated message from the inventory system.'));

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'requisition_id' => $this->requisition->id,
            'action' => $this->action,
        ];
    }
}
