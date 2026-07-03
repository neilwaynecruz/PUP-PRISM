<?php

namespace App\Services\Operations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueOperationsService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $connection = (string) config('queue.default');
        $retryAfter = $this->retryAfterSeconds($connection);

        return [
            'connection' => $connection,
            'pending_jobs_count' => $this->pendingJobsCount(),
            'pending_by_queue' => $this->pendingJobsByQueue(),
            'reserved_jobs_count' => $this->reservedJobsCount(),
            'possibly_stuck_jobs_count' => $this->possiblyStuckJobsCount($retryAfter),
            'failed_jobs_count' => $this->failedJobsCount(),
            'oldest_pending_job_age_seconds' => $this->oldestPendingJobAgeSeconds(),
            'recent_failed_jobs' => $this->recentFailedJobs(),
            'recommended_worker_command' => 'php artisan queue:work --queue=default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600',
        ];
    }

    public function pendingJobsCount(): int
    {
        if (! $this->hasJobsTable()) {
            return 0;
        }

        return (int) DB::table('jobs')->count();
    }

    /**
     * @return array<string, int>
     */
    public function pendingJobsByQueue(): array
    {
        if (! $this->hasJobsTable()) {
            return [];
        }

        return DB::table('jobs')
            ->select('queue', DB::raw('count(*) as total'))
            ->groupBy('queue')
            ->orderBy('queue')
            ->pluck('total', 'queue')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    public function reservedJobsCount(): int
    {
        if (! $this->hasJobsTable()) {
            return 0;
        }

        return (int) DB::table('jobs')->whereNotNull('reserved_at')->count();
    }

    public function possiblyStuckJobsCount(int $retryAfter): int
    {
        if (! $this->hasJobsTable()) {
            return 0;
        }

        $stuckBefore = CarbonImmutable::now()->subSeconds($retryAfter)->getTimestamp();

        return (int) DB::table('jobs')
            ->whereNotNull('reserved_at')
            ->where('reserved_at', '<', $stuckBefore)
            ->count();
    }

    public function failedJobsCount(): int
    {
        if (! Schema::hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    public function oldestPendingJobAgeSeconds(): ?int
    {
        if (! $this->hasJobsTable()) {
            return null;
        }

        $createdAt = DB::table('jobs')->min('created_at');

        if (! is_string($createdAt) || $createdAt === '') {
            return null;
        }

        return max(0, (int) CarbonImmutable::parse($createdAt)->diffInSeconds(CarbonImmutable::now(), false));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentFailedJobs(int $limit = 5): array
    {
        if (! Schema::hasTable('failed_jobs')) {
            return [];
        }

        return DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get(['uuid', 'connection', 'queue', 'failed_at'])
            ->map(function (object $job): array {
                return [
                    'uuid' => (string) $job->uuid,
                    'connection' => (string) $job->connection,
                    'queue' => (string) $job->queue,
                    'failed_at' => (string) $job->failed_at,
                ];
            })
            ->all();
    }

    private function hasJobsTable(): bool
    {
        return Schema::hasTable('jobs');
    }

    private function retryAfterSeconds(string $connection): int
    {
        $connections = config('queue.connections', []);

        if (! is_array($connections)) {
            return 90;
        }

        $connectionConfig = $connections[$connection] ?? [];

        if (! is_array($connectionConfig)) {
            return 90;
        }

        return max(1, (int) ($connectionConfig['retry_after'] ?? 90));
    }
}
