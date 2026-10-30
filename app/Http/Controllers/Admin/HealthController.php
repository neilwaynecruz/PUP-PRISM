<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\SystemHealthService;
use App\Support\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(SystemHealthService $systemHealth): JsonResponse
    {
        $report = $systemHealth->report();

        return response()->json([
            'status' => $report['status'],
            'summary' => $report['summary'],
            'checked_at' => $report['checked_at'],
            'checks' => collect($report['checks'])
                ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['status']])
                ->all(),
            'failed_jobs_count' => $report['queue_operations']['failed_jobs_count'],
            'queue_connection' => $report['queue_operations']['connection'],
            'scheduler_last_runs' => SchedulerHeartbeat::lastRuns(),
            'queue_operations' => $report['queue_operations'],
        ], headers: ['Cache-Control' => 'no-store, private']);
    }
}
