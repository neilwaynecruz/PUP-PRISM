<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\QueueOperationsService;
use App\Support\SchedulerHeartbeat;
use Inertia\Inertia;
use Inertia\Response;

class OperationsHealthController extends Controller
{
    public function __invoke(QueueOperationsService $queueOperations): Response
    {
        $queueSnapshot = $queueOperations->snapshot();

        return Inertia::render('admin/operations/Health', [
            'health' => [
                'status' => 'ok',
                'failed_jobs_count' => $queueSnapshot['failed_jobs_count'],
                'queue_connection' => $queueSnapshot['connection'],
                'scheduler_last_runs' => SchedulerHeartbeat::lastRuns(),
                'queue_operations' => $queueSnapshot,
            ],
            'tracked_commands' => SchedulerHeartbeat::trackedCommands(),
        ]);
    }
}
