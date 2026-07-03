<?php

namespace App\Console\Commands;

use App\Models\NotificationPreference;
use App\Models\User;
use App\Notifications\DailyNotificationDigest;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

#[Signature('app:send-notification-digests')]
#[Description('Send daily email digests for users with digest-enabled notification preferences')]
class SendNotificationDigests extends Command
{
    public function handle(): int
    {
        $since = CarbonImmutable::now()->subDay();
        $sentCount = 0;

        User::query()
            ->whereHas('notificationPreferences', function ($query): void {
                $query
                    ->where('digest_frequency', 'daily')
                    ->where('mail_enabled', true);
            })
            ->orderBy('id')
            ->chunkById(100, function (Collection $users) use ($since, &$sentCount): void {
                foreach ($users as $user) {
                    if (! $user instanceof User) {
                        continue;
                    }

                    if ($this->sendDigestForUser($user, $since)) {
                        $sentCount++;
                    }
                }
            });

        $this->components->info(sprintf('Sent %d notification digest email(s).', $sentCount));

        SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_SEND_NOTIFICATION_DIGESTS);

        return self::SUCCESS;
    }

    private function sendDigestForUser(User $user, CarbonImmutable $since): bool
    {
        $dailyEventTypes = NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('digest_frequency', 'daily')
            ->where('mail_enabled', true)
            ->pluck('event_type');

        if ($dailyEventTypes->isEmpty()) {
            return false;
        }

        /** @var Collection<int, DatabaseNotification> $notifications */
        $notifications = $user->notifications()
            ->whereNull('digested_at')
            ->where('created_at', '>=', $since)
            ->get()
            ->filter(function (DatabaseNotification $notification) use ($dailyEventTypes): bool {
                $data = is_array($notification->data) ? $notification->data : [];
                $eventType = $data['event_type'] ?? null;

                return is_string($eventType) && $dailyEventTypes->contains($eventType);
            })
            ->values();

        if ($notifications->isEmpty()) {
            return false;
        }

        $user->notify(new DailyNotificationDigest($notifications));

        DatabaseNotification::query()
            ->whereIn('id', $notifications->pluck('id'))
            ->update(['digested_at' => now()]);

        return true;
    }
}
