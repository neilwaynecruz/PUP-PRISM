<?php

namespace App\Services;

use App\Enums\NotificationEventType;
use App\Models\User;
use App\Support\NotificationPreferenceData;
use Illuminate\Support\Collection;

class NotificationPreferenceDefaults
{
    /**
     * @var array<string, array<int, NotificationEventType>>
     */
    private const ROLE_EVENTS = [
        'Admin' => [
            NotificationEventType::RequisitionSubmitted,
            NotificationEventType::RequisitionStatusChanged,
            NotificationEventType::BookingSubmitted,
            NotificationEventType::BookingStatusChanged,
            NotificationEventType::LowStock,
            NotificationEventType::ProcurementRecommendation,
            NotificationEventType::HandoverVerification,
        ],
        'Supply Head' => [
            NotificationEventType::RequisitionSubmitted,
            NotificationEventType::RequisitionStatusChanged,
            NotificationEventType::LowStock,
            NotificationEventType::ProcurementRecommendation,
            NotificationEventType::HandoverVerification,
        ],
        'Property Custodian' => [
            NotificationEventType::BookingSubmitted,
            NotificationEventType::BookingStatusChanged,
            NotificationEventType::HandoverVerification,
            NotificationEventType::RequisitionStatusChanged,
        ],
    ];

    /**
     * @var array<int, NotificationEventType>
     */
    private const BASE_EVENTS = [
        NotificationEventType::RequisitionStatusChanged,
        NotificationEventType::BookingStatusChanged,
        NotificationEventType::HandoverVerification,
    ];

    public function userReceivesEvent(User $user, string $eventType): bool
    {
        return in_array($eventType, $this->eventTypesForUser($user), true);
    }

    public function forUserEvent(User $user, string $eventType): NotificationPreferenceData
    {
        if (! $this->userReceivesEvent($user, $eventType)) {
            return NotificationPreferenceData::disabled();
        }

        return NotificationPreferenceData::instantAll();
    }

    /**
     * @return array<int, string>
     */
    public function eventTypesForUser(User $user): array
    {
        $events = collect(self::BASE_EVENTS);

        foreach ($user->getRoleNames() as $roleName) {
            $roleEvents = self::ROLE_EVENTS[(string) $roleName] ?? [];

            $events = $events->merge($roleEvents);
        }

        return $events
            ->map(static fn (NotificationEventType $eventType): string => $eventType->value)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public function optionsForUser(User $user): array
    {
        $allowed = $this->eventTypesForUser($user);

        return array_values(array_filter(
            NotificationEventType::options(),
            static fn (array $option): bool => in_array($option['value'], $allowed, true),
        ));
    }

    /**
     * @return Collection<int, NotificationEventType>
     */
    public function eventTypeEnumsForUser(User $user): Collection
    {
        return collect($this->eventTypesForUser($user))
            ->map(static fn (string $eventType): NotificationEventType => NotificationEventType::from($eventType));
    }
}
