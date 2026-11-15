<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\DailyNotificationDigest;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('app:send-notification-digests')]
#[Description('Send daily email digests for users with digest-enabled notification preferences')]
class SendNotificationDigests extends Command
{
    public function handle(): int
    {
        $since = CarbonImmutable::now()->subDay();
        $sentCount = 0;

        User::query()
            ->where('is_active', true)
            ->whereHas('notificationPreferences', function (Builder $query): void {
                $query
                    ->where('digest_frequency', 'daily')
                    ->where('mail_enabled', true);
            })
            ->with([
                'notificationPreferences' => function ($query): void {
                    $query
                        ->select(['id', 'user_id', 'event_type'])
                        ->where('digest_frequency', 'daily')
                        ->where('mail_enabled', true);
                },
            ])
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($since, &$sentCount): void {
                $sentCount += $this->sendDigestsForChunk($users, $since);
            });

        $this->components->info(sprintf('Sent %d notification digest email(s).', $sentCount));

        SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_SEND_NOTIFICATION_DIGESTS);

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, User>  $users
     */
    private function sendDigestsForChunk(Collection $users, CarbonImmutable $since): int
    {
        /** @var array<string, array<string, true>> $eventTypesByUserId */
        $eventTypesByUserId = [];
        /** @var array<string, true> $allChunkEventTypes */
        $allChunkEventTypes = [];
        /** @var list<User> $eligibleUsers */
        $eligibleUsers = [];

        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            /** @var array<string, true> $userEventTypes */
            $userEventTypes = [];

            foreach ($user->notificationPreferences as $preference) {
                if (is_string($preference->event_type) && $preference->event_type !== '') {
                    $userEventTypes[$preference->event_type] = true;
                    $allChunkEventTypes[$preference->event_type] = true;
                }
            }

            if ($userEventTypes === []) {
                continue;
            }

            $eventTypesByUserId[(string) $user->id] = $userEventTypes;
            $eligibleUsers[] = $user;
        }

        if ($eligibleUsers === [] || $allChunkEventTypes === []) {
            return 0;
        }

        $chunkEventTypes = array_keys($allChunkEventTypes);
        $userIds = array_map(static fn (User $user): int|string => $user->getKey(), $eligibleUsers);

        $notificationsQuery = DatabaseNotification::query()
            ->where('notifiable_type', $eligibleUsers[0]->getMorphClass())
            ->whereIn('notifiable_id', $userIds)
            ->whereNull('digested_at')
            ->where('created_at', '>=', $since);

        $this->applyEventTypeFilter($notificationsQuery, $chunkEventTypes);

        /** @var Collection<string, Collection<int, DatabaseNotification>> $notificationsByUserId */
        $notificationsByUserId = $notificationsQuery
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(static fn (DatabaseNotification $notification): string => (string) $notification->notifiable_id);

        $sentCount = 0;
        /** @var list<string> $digestedIds */
        $digestedIds = [];

        foreach ($eligibleUsers as $user) {
            $userKey = (string) $user->getKey();
            $allowedEventTypes = $eventTypesByUserId[$userKey] ?? [];
            /** @var Collection<int, DatabaseNotification> $candidates */
            $candidates = $notificationsByUserId->get($userKey, collect());

            $userNotifications = $candidates
                ->filter(static function (DatabaseNotification $notification) use ($allowedEventTypes): bool {
                    $data = is_array($notification->data) ? $notification->data : [];
                    $eventType = $data['event_type'] ?? null;

                    return is_string($eventType) && isset($allowedEventTypes[$eventType]);
                })
                ->values();

            if ($userNotifications->isEmpty()) {
                continue;
            }

            $user->notify(new DailyNotificationDigest($userNotifications));
            $sentCount++;

            foreach ($userNotifications as $notification) {
                $digestedIds[] = (string) $notification->id;
            }
        }

        if ($digestedIds !== []) {
            $digestedAt = now();

            foreach (array_chunk($digestedIds, 500) as $idChunk) {
                DatabaseNotification::query()
                    ->whereIn('id', $idChunk)
                    ->update(['digested_at' => $digestedAt]);
            }
        }

        return $sentCount;
    }

    /**
     * @param  Builder<DatabaseNotification>  $query
     * @param  list<string>  $eventTypes
     */
    private function applyEventTypeFilter(Builder $query, array $eventTypes): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            $placeholders = implode(', ', array_fill(0, count($eventTypes), '?'));
            $query->whereRaw("(data::jsonb ->> 'event_type') IN ({$placeholders})", $eventTypes);

            return;
        }

        $query->whereIn('data->event_type', $eventTypes);
    }
}
