<?php

namespace App\Services;

use App\Models\NotificationPreference;
use App\Models\User;

class NotificationPreferenceSeeder
{
    public function __construct(
        private readonly NotificationPreferenceDefaults $defaults,
    ) {}

    public function seedForUser(User $user): void
    {
        foreach ($this->defaults->eventTypesForUser($user) as $eventType) {
            NotificationPreference::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                ],
                [
                    'mail_enabled' => true,
                    'database_enabled' => true,
                    'broadcast_enabled' => true,
                    'digest_frequency' => 'instant',
                ],
            );
        }
    }
}
