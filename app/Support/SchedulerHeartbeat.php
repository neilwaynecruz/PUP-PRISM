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
        return [
            self::COMMAND_GENERATE_DEMAND_FORECASTS,
            self::COMMAND_INVENTORY_GENERATE_ALERTS,
            self::COMMAND_SEND_NOTIFICATION_DIGESTS,
            self::COMMAND_TRASH_CLEANUP,
            self::COMMAND_PRUNE_OPERATIONAL_DATA,
        ];
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
