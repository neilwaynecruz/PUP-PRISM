<?php

namespace App\Console\Commands;

use App\Services\Operations\OperationalDataRetentionService;
use App\Support\SchedulerHeartbeat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:prune-operational-data
    {--dry-run : Report eligible rows without deleting}
    {--only= : Limit pruning to audit_logs, forecast_snapshots, notifications, or failed_jobs}')]
#[Description('Prune aged audit logs, forecast snapshots, notifications, and failed jobs')]
class PruneOperationalData extends Command
{
    public function handle(OperationalDataRetentionService $retentionService): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $only = $this->option('only');

        if ($dryRun) {
            $this->components->warn('Dry run mode — no rows will be deleted.');
        }

        $counts = $this->resolveCounts($retentionService, $dryRun, is_string($only) ? $only : null);

        foreach ($counts as $dataset => $count) {
            $this->components->info(sprintf(
                '%s %d %s row(s).',
                $dryRun ? 'Would prune' : 'Pruned',
                $count,
                str_replace('_', ' ', $dataset),
            ));
        }

        if (! $dryRun) {
            SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_PRUNE_OPERATIONAL_DATA);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    private function resolveCounts(
        OperationalDataRetentionService $retentionService,
        bool $dryRun,
        ?string $only,
    ): array {
        if ($only === null || $only === '') {
            return $retentionService->prune($dryRun);
        }

        return match ($only) {
            'audit_logs' => ['audit_logs' => $retentionService->pruneAuditLogs($dryRun)],
            'forecast_snapshots' => ['forecast_snapshots' => $retentionService->pruneForecastSnapshots($dryRun)],
            'notifications' => ['notifications' => $retentionService->pruneNotifications($dryRun)],
            'failed_jobs' => ['failed_jobs' => $retentionService->pruneFailedJobs($dryRun)],
            default => $this->invalidOnlyOption($only),
        };
    }

    /**
     * @return array<string, int>
     */
    private function invalidOnlyOption(string $only): array
    {
        $this->components->error("Unknown --only value [{$only}]. Use audit_logs, forecast_snapshots, notifications, or failed_jobs.");

        exit(self::FAILURE);
    }
}
