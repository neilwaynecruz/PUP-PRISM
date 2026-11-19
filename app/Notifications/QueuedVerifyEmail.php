<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\HasReliableMailDelivery;
use App\Services\Operations\EmailDeliveryService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

class QueuedVerifyEmail extends VerifyEmail implements ShouldBeEncrypted, ShouldQueue
{
    use HasReliableMailDelivery;
    use Queueable;

    public int $tries = 0;

    private string $url;

    public function __construct(private string $email, User $user)
    {
        $this->initializeMailDelivery();
        $this->url = parent::verificationUrl($user);
        $this->mailDeadline = now()->addMinutes((int) config('auth.verification.expire', 60))->subMinute()->getTimestamp();
    }

    protected function verificationUrl($notifiable): string
    {
        return $this->url;
    }

    protected function mailQueue(): string
    {
        return 'security';
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        if (now()->getTimestamp() >= $this->mailDeadline) {
            return app(EmailDeliveryService::class)->skip($notifiable, $this, 'expired');
        }

        if (! $notifiable->is_active || $notifiable->hasVerifiedEmail() || $notifiable->getEmailForVerification() !== $this->email) {
            return app(EmailDeliveryService::class)->skip($notifiable, $this, 'superseded');
        }

        return true;
    }
}
