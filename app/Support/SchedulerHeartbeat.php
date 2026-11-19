<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

class SchedulerHeartbeat
{
    public const COMMAND_GENERATE_DEMAND_FORECASTS = 'app:generate-demand-forecasts';

    public const COMMAND_INVENTORY_GENERATE_ALERTS = 'app:inventory-generate-alerts';

    public const COMMAND_TRASH_CLEANUP = 'trash:cleanup';

    public const COMMAND_SEND_NOTIFICATION_DIGESTS = 'app:send-notification-digests';

    public const COMMAND_PRUNE_OPERATIONAL_DATA = 'app:prune-operational-data';

    /**
     * @return array<int, string>
     */
    public static function trackedCommands(): array
    {
        return array_keys(self::expectations());
    }

    /**
     * How each tracked command is scheduled in routes/console.php, with the
     * longest gap (in hours) allowed before its heartbeat is considered stale.
     *
     * @return array<string, array{label: string, schedule: string, max_gap_hours: int}>
     */
    public static function expectations(): array
    {
        $expectations = [
            self::COMMAND_GENERATE_DEMAND_FORECASTS => ['label' => 'Demand forecasts', 'schedule' => 'Daily at 1:30 AM', 'max_gap_hours' => 26],
            self::COMMAND_INVENTORY_GENERATE_ALERTS => ['label' => 'Inventory alerts', 'schedule' => 'Daily at 2:00 AM', 'max_gap_hours' => 26],
            self::COMMAND_SEND_NOTIFICATION_DIGESTS => ['label' => 'Notification digests', 'schedule' => 'Daily at 8:00 AM', 'max_gap_hours' => 26],
            self::COMMAND_TRASH_CLEANUP => ['label' => 'Trash cleanup', 'schedule' => 'Daily at midnight', 'max_gap_hours' => 26],
            self::COMMAND_PRUNE_OPERATIONAL_DATA => ['label' => 'Operational data pruning', 'schedule' => 'Weekly, Sunday 3:30 AM', 'max_gap_hours' => 180],
        ];

        if (! config('retention.scheduling_enabled')) {
            unset($expectations[self::COMMAND_TRASH_CLEANUP], $expectations[self::COMMAND_PRUNE_OPERATIONAL_DATA]);
        }

        return $expectations;
    }

    public static function record(string $command): void
    {
        Cache::put(
            self::cacheKey($command),
            now()->toIso8601String(),
            now()->addDays(30),
        );
    }

    /**
     * @return array<string, string|null>
     */
    public static function lastRuns(): array
    {
        $runs = [];

        foreach (self::trackedCommands() as $command) {
            $value = Cache::get(self::cacheKey($command));

            $runs[$command] = is_string($value) ? $value : null;
        }

        return $runs;
    }

    public static function cacheKey(string $command): string
    {
        return "scheduler:last_run:{$command}";
    }
}
