<?php

namespace App\Notifications;

use App\Enums\NotificationEventType;
use App\Models\Requisition;
use App\Notifications\Concerns\ResolvesViaNotificationPreferences;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisitionStatusChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use ResolvesViaNotificationPreferences;

    public int $tries = 3;

    public function __construct(
        public Requisition $requisition,
        public string $action,
    ) {
        $this->onQueue('notifications');
    }

    public function notificationEventType(): string
    {
        return NotificationEventType::RequisitionStatusChanged->value;
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
        $url = route('inventory.requisitions.show', $this->requisition, absolute: false);
        $severity = match ($this->action) {
            'rejected' => 'warning',
            'issued' => 'success',
            default => 'info',
        };

        return [
            'event_type' => $this->notificationEventType(),
            'type' => 'requisition.status-changed',
            'category' => 'requisition',
            'severity' => $severity,
            'title' => __('Requisition status updated'),
            'message' => __('Requisition #:id was :action.', [
                'id' => $this->requisition->id,
                'action' => $this->action,
            ]),
            'url' => $url,
            'requisition_id' => $this->requisition->id,
            'action' => $this->action,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
