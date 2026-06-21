<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Booking $booking,
        public string $action,
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
        $status = match ($this->action) {
            'approved' => __('approved'),
            'rejected' => __('rejected'),
            default => __('updated'),
        };

        $subject = match ($this->action) {
            'approved' => __('Your booking request has been approved'),
            'rejected' => __('Your booking request has been rejected'),
            default => __('Your booking request status changed'),
        };

        $assetTag = $this->booking->asset?->tag_code ?? 'Unknown asset';
        $url = route('inventory.bookings.index', absolute: false);

        $message = (new MailMessage)
            ->subject($subject)
            ->line(__('Your booking for asset :tag has been :status.', [
                'tag' => $assetTag,
                'status' => $status,
            ]));

        if ($this->action === 'approved') {
            $message->line(__('Booked from :start to :end', [
                'start' => $this->booking->start_at?->format('Y-m-d H:i') ?? '',
                'end' => $this->booking->end_at?->format('Y-m-d H:i') ?? '',
            ]));
        }

        $message->action(__('View bookings'), $url)
            ->line(__('This is an automated message from the inventory system.'));

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $assetTag = $this->booking->asset?->tag_code ?? 'Unknown asset';
        $url = route('inventory.bookings.show', $this->booking->id, absolute: false);

        return [
            'type' => 'booking.status-changed',
            'category' => 'booking',
            'severity' => $this->action === 'rejected' ? 'warning' : 'success',
            'title' => __('Booking status updated'),
            'message' => __('Your booking for asset :tag was :action.', [
                'tag' => $assetTag,
                'action' => $this->action,
            ]),
            'url' => $url,
            'booking_id' => $this->booking->id,
            'action' => $this->action,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
