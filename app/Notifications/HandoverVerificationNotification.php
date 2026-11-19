<?php

namespace App\Notifications;

use App\Enums\NotificationEventType;
use App\Models\HandoverLog;
use App\Models\User;
use App\Notifications\Concerns\HasReliableMailDelivery;
use App\Notifications\Concerns\ResolvesViaNotificationPreferences;
use App\Services\Operations\EmailDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class HandoverVerificationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use HasReliableMailDelivery;
    use Queueable;
    use ResolvesViaNotificationPreferences { via as preferredChannels; }

    public int $tries = 0;

    public function __construct(
        public int $handoverLogId,
        public string $token,
    ) {
        $this->initializeMailDelivery();
    }

    public function notificationEventType(): string
    {
        return NotificationEventType::HandoverVerification->value;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return array_values(array_unique(['mail', ...$this->preferredChannels($notifiable)]));
    }

    protected function mailQueue(): string
    {
        return 'security';
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return true;
        }

        if (now()->getTimestamp() >= $this->mailDeadline) {
            return app(EmailDeliveryService::class)->skip($notifiable, $this, 'expired');
        }

        $handover = HandoverLog::query()->find($this->handoverLogId);
        if (! $notifiable->is_active || $handover === null || ! $handover->isPending()
            || $handover->to_user_id !== $notifiable->id
            || ! hash_equals((string) $handover->verification_token_hash, hash('sha256', $this->token))) {
            return app(EmailDeliveryService::class)->skip($notifiable, $this, 'superseded');
        }

        return true;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $_notifiable): MailMessage
    {
        /** @var string $url */
        $url = route('inventory.handover.verify', [
            'handoverLog' => $this->handoverLogId,
            'token' => $this->token,
        ]);

        return (new MailMessage)
            ->subject(__('Internal asset handover verification required'))
            ->line(__('A property custodian initiated an internal asset handover to you.'))
            ->line(__('To finalize institutional accountability, please verify this handover while logged in with a verified email address.'))
            ->line(__('Use the account selected as the recipient, not the administrator or custodian account that sent the handover. If a new link is sent, use the latest email; cancelled handovers cannot be accepted.'))
            ->line(__('This acknowledgement is for internal accountability only and does not replace external legal contracts or notarized documents.'))
            ->action(__('Verify internal handover'), $url);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $_notifiable): array
    {
        return [
            'event_type' => $this->notificationEventType(),
            'type' => 'handover.verification-required',
            'category' => 'handover',
            'severity' => 'info',
            'title' => __('Handover verification required'),
            'message' => __('An internal asset handover is awaiting your verification. A secure email link has been queued; delivery may be delayed.'),
            'url' => null,
            'handover_log_id' => $this->handoverLogId,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
