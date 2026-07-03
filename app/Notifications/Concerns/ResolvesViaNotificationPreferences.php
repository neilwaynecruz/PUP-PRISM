<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Services\NotificationPreferenceResolver;

trait ResolvesViaNotificationPreferences
{
    abstract public function notificationEventType(): string;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return $this->defaultNotificationChannels();
        }

        return app(NotificationPreferenceResolver::class)
            ->instantChannels($notifiable, $this->notificationEventType());
    }

    /**
     * @return array<int, string>
     */
    protected function defaultNotificationChannels(): array
    {
        return ['mail', 'database', 'broadcast'];
    }
}
