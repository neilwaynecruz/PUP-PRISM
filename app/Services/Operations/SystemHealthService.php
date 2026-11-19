<?php

namespace App\Services\Operations;

use App\Models\EmailDelivery;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs real, read-only checks against PRISM's runtime dependencies.
 *
 * Every check reports one of four states. "unknown" is used whenever a
 * service could not be verified, so nothing is ever reported healthy by default.
 */
class SystemHealthService
{
    public const HEALTHY = 'healthy';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const UNKNOWN = 'unknown';

    /**
     * Queue jobs waiting longer than this suggest no worker is running.
     */
    private const STALE_QUEUE_SECONDS = 900;

    private const LOW_DISK_RATIO = 0.10;

    public function __construct(
        private readonly QueueOperationsService $queueOperations,
    ) {}

    /**
     * @return array{
     *     status: string,
     *     summary: string,
     *     checked_at: string,
     *     counts: array<string, int>,
     *     checks: list<array<string, mixed>>,
     *     scheduler: list<array<string, mixed>>,
     *     queue_operations: array<string, mixed>
     * }
     */
    public function report(): array
    {
        $queueSnapshot = $this->safeQueueSnapshot();
        $scheduler = $this->schedulerHeartbeats();

        $checks = [
            $this->applicationCheck(),
            $this->databaseCheck(),
            $this->cacheCheck(),
            $this->queueCheck($queueSnapshot),
            $this->schedulerCheck($scheduler),
            $this->realtimeCheck(),
            $this->storageCheck(),
            $this->emailCheck(),
        ];

        $counts = [
            self::HEALTHY => 0,
            self::WARNING => 0,
            self::CRITICAL => 0,
            self::UNKNOWN => 0,
        ];

        foreach ($checks as $check) {
            $counts[$check['status']]++;
        }

        $status = match (true) {
            $counts[self::CRITICAL] > 0 => 'critical',
            $counts[self::WARNING] > 0 => 'degraded',
            default => 'ok',
        };

        return [
            'status' => $status,
            'summary' => $this->overallSummary($status, $counts),
            'checked_at' => CarbonImmutable::now()->toIso8601String(),
            'counts' => $counts,
            'checks' => $checks,
            'scheduler' => $scheduler,
            'queue_operations' => $queueSnapshot,
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function overallSummary(string $status, array $counts): string
    {
        $unverified = $counts[self::UNKNOWN] > 0
            ? ' '.trans_choice('{1} :count check could not be verified.|[2,*] :count checks could not be verified.', $counts[self::UNKNOWN], ['count' => $counts[self::UNKNOWN]])
            : '';

        return match ($status) {
            'critical' => __('One or more core services are failing and need immediate attention.').$unverified,
            'degraded' => __('PRISM is running, but some services need attention.').$unverified,
            default => __('All verified services are operating normally.').$unverified,
        };
    }

    /** @return array<string, mixed> */
    private function emailCheck(): array
    {
        if (config('mail.default') !== 'resend') {
            return $this->check('email', 'Email delivery', self::WARNING,
                __('Resend delivery tracking is not enabled. Log and array mailers do not deliver email.'), [],
                __('Configure Resend server-side and start the security and mail queue workers.'));
        }

        try {
            $counts = EmailDelivery::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
            $details = [];
            foreach ([
                'queued' => 'Queued', 'processing' => 'Processing', 'accepted' => 'Accepted by Resend (not proof of delivery)',
                'retry_scheduled' => 'Retry scheduled', 'failed' => 'Failed', 'expired' => 'Expired', 'superseded' => 'Superseded',
                'cancelled' => 'Cancelled before transport',
            ] as $status => $label) {
                $details[] = ['label' => $label, 'value' => (string) ($counts[$status] ?? 0)];
            }

            $hasFailures = ($counts['failed'] ?? 0) > 0;

            return $this->check('email', 'Email delivery', $hasFailures ? self::WARNING : self::UNKNOWN,
                __('Local delivery status only. Inbox delivery and provider connectivity have not been verified.'), $details,
                __('Use security and mail workers. Review failed jobs; request a fresh link if expired.'));
        } catch (Throwable) {
            return $this->check('email', 'Email delivery', self::WARNING,
                __('Delivery tracking is unavailable.'), [], __('Review pending migrations and database access.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function applicationCheck(): array
    {
        $details = [
            ['label' => 'Environment', 'value' => (string) config('app.env')],
            ['label' => 'Laravel', 'value' => App::version()],
            ['label' => 'PHP', 'value' => PHP_VERSION],
            ['label' => 'Timezone', 'value' => (string) config('app.timezone')],
        ];

        if (App::isDownForMaintenance()) {
            return $this->check('application', 'Application', self::WARNING,
                __('Maintenance mode is on. Only users with the bypass secret can sign in.'),
                $details,
                __('Run "php artisan up" when maintenance is finished.'),
            );
        }

        if (App::isProduction() && (bool) config('app.debug')) {
            return $this->check('application', 'Application', self::WARNING,
                __('Debug mode is enabled in production, which can expose error details to users.'),
                $details,
                __('Set APP_DEBUG=false in the environment file and clear the config cache.'),
            );
        }

        return $this->check('application', 'Application', self::HEALTHY,
            __('The application is serving requests normally.'),
            $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function databaseCheck(): array
    {
        $connection = (string) config('database.default');

        try {
            $started = hrtime(true);
            DB::connection()->select('select 1');
            $latencyMs = round((hrtime(true) - $started) / 1_000_000, 1);
        } catch (Throwable $exception) {
            return $this->check('database', 'Database', self::CRITICAL,
                __('PRISM could not reach the database.'),
                [
                    ['label' => 'Connection', 'value' => $connection],
                    ['label' => 'Error type', 'value' => class_basename($exception)],
                ],
                __('Confirm the database server is running and the DB_* settings are correct.'),
            );
        }

        $status = $latencyMs > 500 ? self::WARNING : self::HEALTHY;

        return $this->check('database', 'Database', $status,
            $status === self::HEALTHY
                ? __('Connected and responding.')
                : __('Connected, but responses are slow.'),
            [
                ['label' => 'Connection', 'value' => $connection],
                ['label' => 'Driver', 'value' => (string) DB::connection()->getDriverName()],
                ['label' => 'Response time', 'value' => "{$latencyMs} ms"],
            ],
            $status === self::WARNING ? __('Check the database server load and network latency.') : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function cacheCheck(): array
    {
        $store = (string) config('cache.default');
        $key = 'health:probe:'.Str::random(12);

        try {
            Cache::put($key, 'ok', 30);
            $readBack = Cache::get($key);
            Cache::forget($key);
        } catch (Throwable $exception) {
            return $this->check('cache', 'Cache', self::CRITICAL,
                __('The cache store could not be written to or read from.'),
                [
                    ['label' => 'Store', 'value' => $store],
                    ['label' => 'Error type', 'value' => class_basename($exception)],
                ],
                __('Scheduler heartbeats and dashboard statistics depend on the cache. Check the CACHE_STORE settings.'),
            );
        }

        if ($readBack !== 'ok') {
            return $this->check('cache', 'Cache', self::CRITICAL,
                __('Values written to the cache could not be read back.'),
                [['label' => 'Store', 'value' => $store]],
                __('Check the cache store configuration and permissions.'),
            );
        }

        return $this->check('cache', 'Cache', self::HEALTHY,
            __('Read and write test passed.'),
            [['label' => 'Store', 'value' => $store]],
        );
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function queueCheck(array $snapshot): array
    {
        $connection = (string) config('queue.default');
        $details = [['label' => 'Connection', 'value' => $connection]];

        if (($snapshot['error'] ?? null) !== null) {
            return $this->check('queue', 'Queue', self::CRITICAL,
                __('Queue statistics could not be read.'),
                [...$details, ['label' => 'Error type', 'value' => (string) $snapshot['error']]],
                __('Confirm the jobs and failed_jobs tables exist and the database is reachable.'),
            );
        }

        if ($connection === 'sync') {
            return $this->check('queue', 'Queue', self::WARNING,
                __('Jobs run immediately inside web requests, so slow notifications can delay pages.'),
                $details,
                __('Use the database queue with a running worker in production.'),
            );
        }

        if ($connection !== 'database') {
            return $this->check('queue', 'Queue', self::UNKNOWN,
                __('Queue depth is only measured for the database queue driver.'),
                $details,
            );
        }

        $details = [
            ...$details,
            ['label' => 'Waiting', 'value' => (string) $snapshot['pending_jobs_count']],
            ['label' => 'Failed', 'value' => (string) $snapshot['failed_jobs_count']],
        ];

        $oldestAge = $snapshot['oldest_pending_job_age_seconds'];

        if (is_int($oldestAge) && $oldestAge > self::STALE_QUEUE_SECONDS) {
            return $this->check('queue', 'Queue', self::WARNING,
                __('Jobs have been waiting for more than :minutes minutes. A queue worker may not be running.', ['minutes' => intdiv(self::STALE_QUEUE_SECONDS, 60)]),
                $details,
                __('Start a worker with the recommended command below, or check your process supervisor.'),
            );
        }

        if ($snapshot['possibly_stuck_jobs_count'] > 0) {
            return $this->check('queue', 'Queue', self::WARNING,
                __('Some jobs were reserved by a worker but never finished.'),
                $details,
                __('Restart the queue workers. Stuck jobs are retried after the retry window passes.'),
            );
        }

        if ($snapshot['failed_jobs_count'] > 0) {
            return $this->check('queue', 'Queue', self::WARNING,
                trans_choice('{1} :count job has failed and needs review.|[2,*] :count jobs have failed and need review.', $snapshot['failed_jobs_count'], ['count' => $snapshot['failed_jobs_count']]),
                $details,
                __('Review the failures below, then run "php artisan queue:retry all" once the cause is fixed.'),
            );
        }

        return $this->check('queue', 'Queue', self::HEALTHY,
            __('Jobs are being processed with no failures recorded.'),
            $details,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $heartbeats
     * @return array<string, mixed>
     */
    private function schedulerCheck(array $heartbeats): array
    {
        $stale = array_values(array_filter($heartbeats, fn (array $row): bool => $row['status'] === self::WARNING));
        $neverRun = array_values(array_filter($heartbeats, fn (array $row): bool => $row['status'] === self::UNKNOWN));
        $details = [
            ['label' => 'On schedule', 'value' => (string) (count($heartbeats) - count($stale) - count($neverRun))],
            ['label' => 'Overdue', 'value' => (string) count($stale)],
            ['label' => 'Not yet recorded', 'value' => (string) count($neverRun)],
        ];

        if ($stale !== []) {
            return $this->check('scheduler', 'Scheduled tasks', self::WARNING,
                trans_choice('{1} :count scheduled task is overdue.|[2,*] :count scheduled tasks are overdue.', count($stale), ['count' => count($stale)]),
                $details,
                __('Make sure "php artisan schedule:run" runs every minute (cron or Task Scheduler).'),
            );
        }

        if (count($neverRun) === count($heartbeats)) {
            return $this->check('scheduler', 'Scheduled tasks', self::UNKNOWN,
                __('No scheduled task has reported a run yet, so the scheduler cannot be verified.'),
                $details,
                __('Make sure "php artisan schedule:run" runs every minute (cron or Task Scheduler).'),
            );
        }

        return $this->check('scheduler', 'Scheduled tasks', self::HEALTHY,
            $neverRun === []
                ? __('Every tracked task ran within its expected window.')
                : __('Tasks that have reported are on schedule. Some have not run yet.'),
            $details,
        );
    }

    /**
     * @return list<array{command: string, label: string, schedule: string, last_run_at: string|null, status: string, message: string}>
     */
    private function schedulerHeartbeats(): array
    {
        try {
            $lastRuns = SchedulerHeartbeat::lastRuns();
        } catch (Throwable) {
            $lastRuns = [];
        }

        $now = CarbonImmutable::now();
        $rows = [];

        foreach (SchedulerHeartbeat::expectations() as $command => $expectation) {
            $lastRun = $lastRuns[$command] ?? null;

            if ($lastRun === null) {
                $rows[] = [
                    'command' => $command,
                    'label' => $expectation['label'],
                    'schedule' => $expectation['schedule'],
                    'last_run_at' => null,
                    'status' => self::UNKNOWN,
                    'message' => __('No run recorded yet.'),
                ];

                continue;
            }

            $hoursSince = CarbonImmutable::parse($lastRun)->diffInHours($now);
            $isStale = $hoursSince > $expectation['max_gap_hours'];

            $rows[] = [
                'command' => $command,
                'label' => $expectation['label'],
                'schedule' => $expectation['schedule'],
                'last_run_at' => $lastRun,
                'status' => $isStale ? self::WARNING : self::HEALTHY,
                'message' => $isStale
                    ? __('Overdue. Expected at least every :hours hours.', ['hours' => $expectation['max_gap_hours']])
                    : __('Ran on schedule.'),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function realtimeCheck(): array
    {
        $driver = (string) config('broadcasting.default');
        $details = [['label' => 'Driver', 'value' => $driver]];

        if (in_array($driver, ['null', 'log'], true)) {
            return $this->check('realtime', 'Realtime updates', self::UNKNOWN,
                __('Broadcasting is turned off, so pages will not update live.'),
                $details,
                __('Set BROADCAST_CONNECTION=reverb and run "php artisan reverb:start" to enable live updates.'),
            );
        }

        if ($driver !== 'reverb') {
            return $this->check('realtime', 'Realtime updates', self::UNKNOWN,
                __('Connectivity is only tested for the Reverb driver.'),
                $details,
            );
        }

        $host = (string) config('broadcasting.connections.reverb.options.host', '');
        $port = (int) config('broadcasting.connections.reverb.options.port', 0);

        if ($host === '' || $port <= 0) {
            return $this->check('realtime', 'Realtime updates', self::WARNING,
                __('The Reverb host or port is not configured.'),
                $details,
                __('Set REVERB_HOST and REVERB_PORT in the environment file.'),
            );
        }

        $details[] = ['label' => 'Server', 'value' => "{$host}:{$port}"];

        $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 1.0);

        if ($socket === false && in_array(strtolower($host), ['localhost', '::1'], true)) {
            $socket = @fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 1.0);
        }

        if ($socket === false) {
            return $this->check('realtime', 'Realtime updates', self::WARNING,
                __('The Reverb server is not accepting connections. PRISM still works, but pages will not refresh live.'),
                $details,
                __('Start it with "php artisan reverb:start" or check the process supervisor.'),
            );
        }

        fclose($socket);

        return $this->check('realtime', 'Realtime updates', self::HEALTHY,
            __('The Reverb server is accepting connections.'),
            $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function storageCheck(): array
    {
        $path = storage_path();

        if (! is_writable($path)) {
            return $this->check('storage', 'File storage', self::CRITICAL,
                __('The storage folder is not writable. Uploads, logs, and receipts will fail.'),
                [],
                __('Grant the web server write permission to the storage directory.'),
            );
        }

        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if (! is_float($free) || ! is_float($total) || $total <= 0) {
            return $this->check('storage', 'File storage', self::UNKNOWN,
                __('Storage is writable, but free disk space could not be measured.'),
                [],
            );
        }

        $ratio = $free / $total;
        $details = [
            ['label' => 'Free space', 'value' => $this->formatBytes($free)],
            ['label' => 'Free', 'value' => round($ratio * 100).'%'],
        ];

        if ($ratio < self::LOW_DISK_RATIO) {
            return $this->check('storage', 'File storage', self::WARNING,
                __('Disk space is running low.'),
                $details,
                __('Free up space or run "php artisan app:prune-operational-data".'),
            );
        }

        return $this->check('storage', 'File storage', self::HEALTHY,
            __('Storage is writable with enough free space.'),
            $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function safeQueueSnapshot(): array
    {
        try {
            return [...$this->queueOperations->snapshot(), 'error' => null];
        } catch (Throwable $exception) {
            return [
                'connection' => (string) config('queue.default'),
                'pending_jobs_count' => 0,
                'pending_by_queue' => [],
                'reserved_jobs_count' => 0,
                'possibly_stuck_jobs_count' => 0,
                'failed_jobs_count' => 0,
                'oldest_pending_job_age_seconds' => null,
                'recent_failed_jobs' => [],
                'recommended_worker_command' => 'php artisan queue:work --queue=security,mail,default,notifications,broadcast --sleep=3 --tries=3 --max-time=3600',
                'error' => class_basename($exception),
            ];
        }
    }

    /**
     * @param  list<array{label: string, value: string}>  $details
     * @return array{key: string, label: string, status: string, summary: string, details: list<array{label: string, value: string}>, hint: string|null}
     */
    private function check(string $key, string $label, string $status, string $summary, array $details = [], ?string $hint = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'summary' => $summary,
            'details' => $details,
            'hint' => $hint,
        ];
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 1).' '.$units[$index];
    }
}
