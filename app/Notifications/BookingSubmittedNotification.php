<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
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
        $requesterName = $this->booking->requester?->name ?? 'A user';
        $assetTag = $this->booking->asset?->tag_code ?? 'Unknown asset';
        $url = route('inventory.bookings.index', absolute: false);

        return (new MailMessage)
            ->subject(__('New asset booking request'))
            ->line(__(':name requested to book asset :tag.', [
                'name' => $requesterName,
                'tag' => $assetTag,
            ]))
            ->line(__('From :start to :end', [
                'start' => $this->booking->start_at?->format('Y-m-d H:i') ?? '',
                'end' => $this->booking->end_at?->format('Y-m-d H:i') ?? '',
            ]))
            ->action(__('Review booking'), $url)
            ->line(__('This is an automated message from the inventory system.'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requesterName = $this->booking->requester?->name ?? 'A user';
        $assetTag = $this->booking->asset?->tag_code ?? 'Unknown asset';
        $url = route('inventory.bookings.show', $this->booking->id, absolute: false);

        return [
            'type' => 'booking.submitted',
            'category' => 'booking',
            'severity' => 'info',
            'title' => __('New booking request'),
            'message' => __(':name requested asset :tag.', [
                'name' => $requesterName,
                'tag' => $assetTag,
            ]),
            'url' => $url,
            'booking_id' => $this->booking->id,
            'requester_id' => $this->booking->requester_id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
