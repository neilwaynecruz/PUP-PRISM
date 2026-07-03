<?php

use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
});

test('admin health endpoint returns queue and scheduler metadata', function () {
    Cache::put(
        SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_TRASH_CLEANUP),
        now()->toIso8601String(),
    );

    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->getJson(route('admin.health', absolute: false))
        ->assertOk()
        ->assertJsonStructure([
            'status',
            'failed_jobs_count',
            'queue_connection',
            'scheduler_last_runs' => [
                SchedulerHeartbeat::COMMAND_GENERATE_DEMAND_FORECASTS,
                SchedulerHeartbeat::COMMAND_INVENTORY_GENERATE_ALERTS,
                SchedulerHeartbeat::COMMAND_SEND_NOTIFICATION_DIGESTS,
                SchedulerHeartbeat::COMMAND_TRASH_CLEANUP,
                SchedulerHeartbeat::COMMAND_PRUNE_OPERATIONAL_DATA,
            ],
            'queue_operations' => [
                'connection',
                'pending_jobs_count',
                'pending_by_queue',
                'reserved_jobs_count',
                'possibly_stuck_jobs_count',
                'failed_jobs_count',
                'oldest_pending_job_age_seconds',
                'recent_failed_jobs',
                'recommended_worker_command',
            ],
        ])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('queue_connection', config('queue.default'))
        ->assertJsonPath(
            'scheduler_last_runs.'.SchedulerHeartbeat::COMMAND_TRASH_CLEANUP,
            fn ($value) => is_string($value),
        );
});

test('admin health endpoint includes recent failed jobs', function () {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'notifications',
        'payload' => '{}',
        'exception' => 'Example failure',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->getJson(route('admin.health', absolute: false))
        ->assertOk()
        ->assertJsonPath('failed_jobs_count', 1)
        ->assertJsonPath('queue_operations.failed_jobs_count', 1)
        ->assertJsonCount(1, 'queue_operations.recent_failed_jobs');
});

test('non-admin users cannot access admin health endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson(route('admin.health', absolute: false))
        ->assertForbidden();
});
