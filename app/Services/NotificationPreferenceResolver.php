<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Support\NotificationPreferenceData;

class NotificationPreferenceResolver
{
    public function __construct(
        private readonly NotificationPreferenceDefaults $defaults,
    ) {}

    public function resolve(User $user, string $eventType): NotificationPreferenceData
    {
        $stored = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_type', $eventType)
            ->first();

        if ($stored instanceof NotificationPreference) {
            return NotificationPreferenceData::fromModel($stored);
        }

        return $this->defaults->forUserEvent($user, $eventType);
    }

    /**
     * Channels delivered immediately when a notification is triggered.
     *
     * @return array<int, string>
     */
    public function instantChannels(User $user, string $eventType): array
    {
        if (! $this->defaults->userReceivesEvent($user, $eventType)) {
            $stored = NotificationPreference::query()
                ->where('user_id', $user->id)
                ->where('event_type', $eventType)
                ->exists();

            if (! $stored) {
                return [];
            }
        }

        $preference = $this->resolve($user, $eventType);
        $channels = [];

        if ($preference->mailEnabled && $preference->digestFrequency === 'instant') {
            $channels[] = 'mail';
        }

        if ($preference->databaseEnabled) {
            $channels[] = 'database';
        }

        if ($preference->broadcastEnabled) {
            $channels[] = 'broadcast';
        }

        return $channels;
    }

    public function usesDailyDigest(User $user, string $eventType): bool
    {
        $preference = $this->resolve($user, $eventType);

        return $preference->mailEnabled && $preference->digestFrequency === 'daily';
    }
}
