<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\SystemHealthService;
use App\Support\SchedulerHeartbeat;
use Inertia\Inertia;
use Inertia\Response;

class OperationsHealthController extends Controller
{
    public function __invoke(SystemHealthService $systemHealth): Response
    {
        $report = $systemHealth->report();

        return Inertia::render('admin/operations/Health', [
            'health' => [
                ...$report,
                'failed_jobs_count' => $report['queue_operations']['failed_jobs_count'],
                'queue_connection' => $report['queue_operations']['connection'],
                'scheduler_last_runs' => SchedulerHeartbeat::lastRuns(),
            ],
            'tracked_commands' => SchedulerHeartbeat::trackedCommands(),
        ]);
    }
}
