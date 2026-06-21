<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HandoverVerificationNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $handoverLogId,
        public string $token,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        /** @var string $url */
        $url = route('inventory.handover.verify', [
            'handoverLog' => $this->handoverLogId,
            'token' => $this->token,
        ], absolute: false);

        return (new MailMessage)
            ->subject(__('Internal asset handover verification required'))
            ->line(__('A property custodian initiated an internal asset handover to you.'))
            ->line(__('To finalize institutional accountability, please verify this handover while logged in with a verified email address.'))
            ->line(__('This acknowledgement is for internal accountability only and does not replace external legal contracts or notarized documents.'))
            ->action(__('Verify internal handover'), $url);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        /** @var string $url */
        $url = route('inventory.handover.verify', [
            'handoverLog' => $this->handoverLogId,
            'token' => $this->token,
        ], absolute: false);

        return [
            'type' => 'handover.verification-required',
            'category' => 'handover',
            'severity' => 'info',
            'title' => __('Handover verification required'),
            'message' => __('An internal asset handover is awaiting your verification.'),
            'url' => $url,
            'handover_log_id' => $this->handoverLogId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
