<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Concerns\HasReliableMailDelivery;
use App\Services\Operations\EmailDeliveryService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Password;

class QueuedResetPassword extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use HasReliableMailDelivery;
    use Queueable;

    public int $tries = 0;

    private string $url;

    public function __construct(string $token, private string $email, User $user)
    {
        parent::__construct($token);
        $this->initializeMailDelivery();
        $this->url = parent::resetUrl($user);
        $broker = config('auth.defaults.passwords');
        $this->mailDeadline = now()->addMinutes((int) config("auth.passwords.{$broker}.expire", 60))->subMinute()->getTimestamp();
    }

    protected function resetUrl($notifiable): string
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

        if (! $notifiable->is_active || $notifiable->getEmailForPasswordReset() !== $this->email || ! Password::tokenExists($notifiable, $this->token)) {
            return app(EmailDeliveryService::class)->skip($notifiable, $this, 'superseded');
        }

        return true;
    }
}
