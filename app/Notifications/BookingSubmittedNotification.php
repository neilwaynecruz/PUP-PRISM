<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
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
        return ['mail'];
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
        return [
            'booking_id' => $this->booking->id,
            'requester_id' => $this->booking->requester_id,
        ];
    }
}
