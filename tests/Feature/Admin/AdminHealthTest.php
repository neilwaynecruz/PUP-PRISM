<?php

use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
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
                SchedulerHeartbeat::COMMAND_TRASH_CLEANUP,
            ],
        ])
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('queue_connection', config('queue.default'))
        ->assertJsonPath(
            'scheduler_last_runs.'.SchedulerHeartbeat::COMMAND_TRASH_CLEANUP,
            fn ($value) => is_string($value),
        );
});

test('non-admin users cannot access admin health endpoint', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->getJson(route('admin.health', absolute: false))
        ->assertForbidden();
});
