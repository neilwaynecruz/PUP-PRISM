<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\ForecastSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;

class OperationalDataRetentionService
{
    /**
     * @return array<string, int>
     */
    public function prune(bool $dryRun = false): array
    {
        $counts = [
            'audit_logs' => $this->pruneAuditLogs($dryRun),
            'forecast_snapshots' => $this->pruneForecastSnapshots($dryRun),
            'notifications' => $this->pruneNotifications($dryRun),
            'failed_jobs' => $this->pruneFailedJobs($dryRun),
        ];

        return $counts;
    }

    public function pruneAuditLogs(bool $dryRun = false): int
    {
        $days = (int) config('retention.audit_logs_days');

        if ($days <= 0) {
            return 0;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        return $this->deleteInChunks(
            AuditLog::query()->where('created_at', '<', $cutoff),
            $dryRun,
        );
    }

    public function pruneForecastSnapshots(bool $dryRun = false): int
    {
        $days = (int) config('retention.forecast_snapshots_days');

        if ($days <= 0) {
            return 0;
        }

        $cutoffDate = CarbonImmutable::now()->subDays($days)->toDateString();

        return $this->deleteInChunks(
            ForecastSnapshot::query()->where('forecast_date', '<', $cutoffDate),
            $dryRun,
        );
    }

    public function pruneNotifications(bool $dryRun = false): int
    {
        $days = (int) config('retention.notifications_days');

        if ($days <= 0) {
            return 0;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        $query = DatabaseNotification::query()
            ->where('created_at', '<', $cutoff)
            ->where(function ($builder): void {
                $builder
                    ->whereNotNull('read_at')
                    ->orWhereNotNull('digested_at');
            });

        return $this->deleteInChunks($query, $dryRun);
    }

    public function pruneFailedJobs(bool $dryRun = false): int
    {
        $days = (int) config('retention.failed_jobs_days');

        if ($days <= 0) {
            return 0;
        }

        $cutoff = CarbonImmutable::now()->subDays($days);

        if ($dryRun) {
            return (int) DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->count();
        }

        return DB::table('failed_jobs')->where('failed_at', '<', $cutoff)->delete();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function deleteInChunks($query, bool $dryRun): int
    {
        $chunkSize = max(100, (int) config('retention.chunk_size', 1000));

        if ($dryRun) {
            return (int) (clone $query)->count();
        }

        $deleted = 0;

        do {
            $ids = (clone $query)
                ->orderBy('id')
                ->limit($chunkSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += (clone $query)->whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunkSize);

        return $deleted;
    }
}
