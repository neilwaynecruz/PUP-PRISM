<?php

namespace App\Support;

use App\Models\NotificationPreference;

readonly class NotificationPreferenceData
{
    public function __construct(
        public bool $mailEnabled,
        public bool $databaseEnabled,
        public bool $broadcastEnabled,
        public string $digestFrequency,
    ) {}

    public static function fromModel(NotificationPreference $preference): self
    {
        return new self(
            mailEnabled: $preference->mail_enabled,
            databaseEnabled: $preference->database_enabled,
            broadcastEnabled: $preference->broadcast_enabled,
            digestFrequency: $preference->digest_frequency,
        );
    }

    public static function instantAll(): self
    {
        return new self(
            mailEnabled: true,
            databaseEnabled: true,
            broadcastEnabled: true,
            digestFrequency: 'instant',
        );
    }

    public static function disabled(): self
    {
        return new self(
            mailEnabled: false,
            databaseEnabled: false,
            broadcastEnabled: false,
            digestFrequency: 'instant',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'mail_enabled' => $this->mailEnabled,
            'database_enabled' => $this->databaseEnabled,
            'broadcast_enabled' => $this->broadcastEnabled,
            'digest_frequency' => $this->digestFrequency,
        ];
    }
}
