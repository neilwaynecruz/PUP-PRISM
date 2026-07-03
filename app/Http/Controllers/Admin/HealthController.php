<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Operations\QueueOperationsService;
use App\Support\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(QueueOperationsService $queueOperations): JsonResponse
    {
        $queueSnapshot = $queueOperations->snapshot();

        return response()->json([
            'status' => 'ok',
            'failed_jobs_count' => $queueSnapshot['failed_jobs_count'],
            'queue_connection' => $queueSnapshot['connection'],
            'scheduler_last_runs' => SchedulerHeartbeat::lastRuns(),
            'queue_operations' => $queueSnapshot,
        ]);
    }
}
