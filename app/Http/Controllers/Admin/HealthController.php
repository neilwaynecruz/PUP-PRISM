<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SchedulerHeartbeat;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'failed_jobs_count' => (int) DB::table('failed_jobs')->count(),
            'queue_connection' => (string) config('queue.default'),
            'scheduler_last_runs' => SchedulerHeartbeat::lastRuns(),
        ]);
    }
}
