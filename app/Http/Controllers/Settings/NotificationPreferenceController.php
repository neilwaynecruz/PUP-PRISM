<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateNotificationPreferencesRequest;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationPreferenceDefaults;
use App\Support\NotificationPreferenceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    public function edit(Request $request, NotificationPreferenceDefaults $defaults): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $this->authorize('viewAny', NotificationPreference::class);

        $storedPreferences = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('event_type');

        $preferences = collect($defaults->eventTypesForUser($user))
            ->map(function (string $eventType) use ($user, $defaults, $storedPreferences): array {
                $stored = $storedPreferences->get($eventType);
                $resolved = $stored instanceof NotificationPreference
                    ? NotificationPreferenceData::fromModel($stored)
                    : $defaults->forUserEvent($user, $eventType);

                return [
                    'event_type' => $eventType,
                    ...$resolved->toArray(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('settings/NotificationPreferences', [
            'preferences' => $preferences,
            'eventTypes' => $defaults->optionsForUser($user),
        ]);
    }

    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        foreach ($request->validated('preferences') as $preference) {
            $notificationPreference = NotificationPreference::query()->firstOrNew([
                'user_id' => $user->id,
                'event_type' => $preference['event_type'],
            ]);

            $this->authorize('update', $notificationPreference);

            $notificationPreference->fill([
                'mail_enabled' => $preference['mail_enabled'],
                'database_enabled' => $preference['database_enabled'],
                'broadcast_enabled' => $preference['broadcast_enabled'],
                'digest_frequency' => $preference['digest_frequency'],
            ])->save();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Notification preferences updated.'),
        ]);

        return to_route('notification-preferences.edit');
    }
}
