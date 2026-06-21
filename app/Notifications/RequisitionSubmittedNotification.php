<?php

namespace App\Notifications;

use App\Models\Requisition;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RequisitionSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Requisition $requisition,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $requesterName = $this->requisition->requester?->name ?? 'A user';
        $url = route('inventory.requisitions.show', $this->requisition, absolute: false);

        return (new MailMessage)
            ->subject(__('New requisition submitted'))
            ->line(__(':name submitted a new requisition that requires your review.', ['name' => $requesterName]))
            ->line(__('Requisition ID: :id', ['id' => $this->requisition->id]))
            ->action(__('Review requisition'), $url)
            ->line(__('This is an automated message from the inventory system.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requesterName = $this->requisition->requester?->name ?? 'A user';
        $url = route('inventory.requisitions.show', $this->requisition, absolute: false);

        return [
            'type' => 'requisition.submitted',
            'category' => 'requisition',
            'severity' => 'info',
            'title' => __('New requisition submitted'),
            'message' => __(':name submitted requisition #:id.', [
                'name' => $requesterName,
                'id' => $this->requisition->id,
            ]),
            'url' => $url,
            'requisition_id' => $this->requisition->id,
            'requester_id' => $this->requisition->requester_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
