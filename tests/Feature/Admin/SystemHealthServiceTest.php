<?php

use App\Models\User;
use App\Services\Operations\SystemHealthService;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @return array<string, array<string, mixed>>
 */
function healthChecksByKey(array $report): array
{
    return collect($report['checks'])->keyBy('key')->all();
}

test('healthy dependencies are verified rather than assumed', function () {
    config(['queue.default' => 'database']);

    $report = app(SystemHealthService::class)->report();
    $checks = healthChecksByKey($report);

    expect($checks['database']['status'])->toBe('healthy')
        ->and($checks['cache']['status'])->toBe('healthy')
        ->and($checks['queue']['status'])->toBe('healthy')
        ->and($checks['storage']['status'])->toBeIn(['healthy', 'warning', 'unknown']);
});

test('unrecorded scheduler heartbeats and disabled broadcasting are reported as not verified', function () {
    config(['broadcasting.default' => 'null']);

    $checks = healthChecksByKey(app(SystemHealthService::class)->report());

    expect($checks['scheduler']['status'])->toBe('unknown')
        ->and($checks['realtime']['status'])->toBe('unknown');
});

test('an overdue scheduled task degrades overall status', function () {
    config(['queue.default' => 'database']);

    foreach (SchedulerHeartbeat::trackedCommands() as $command) {
        Cache::put(SchedulerHeartbeat::cacheKey($command), now()->toIso8601String());
    }

    Cache::put(
        SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_INVENTORY_GENERATE_ALERTS),
        now()->subDays(3)->toIso8601String(),
    );

    $report = app(SystemHealthService::class)->report();
    $checks = healthChecksByKey($report);
    $alertsRow = collect($report['scheduler'])->firstWhere('command', SchedulerHeartbeat::COMMAND_INVENTORY_GENERATE_ALERTS);

    expect($checks['scheduler']['status'])->toBe('warning')
        ->and($alertsRow['status'])->toBe('warning')
        ->and($report['status'])->toBe('degraded');
});

test('failed jobs produce a queue warning without exposing exception messages', function () {
    config(['queue.default' => 'database']);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'notifications',
        'payload' => json_encode(['displayName' => 'App\\Notifications\\LowStockNotification']),
        'exception' => "RuntimeException: SMTP password=secret123 rejected\n#0 trace",
        'failed_at' => now(),
    ]);

    $report = app(SystemHealthService::class)->report();
    $failedJob = $report['queue_operations']['recent_failed_jobs'][0];

    expect(healthChecksByKey($report)['queue']['status'])->toBe('warning')
        ->and($failedJob['job_name'])->toBe('LowStockNotification')
        ->and($failedJob['exception_class'])->toBe('RuntimeException')
        ->and(json_encode($report))->not->toContain('secret123');
});

test('an unreachable reverb server is reported as a warning', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.options.host' => '127.0.0.1',
        'broadcasting.connections.reverb.options.port' => 1,
        'broadcasting.connections.reverb.secret' => 'reverb-secret-value',
        'broadcasting.connections.reverb.key' => 'reverb-key-value',
    ]);

    $checks = healthChecksByKey(app(SystemHealthService::class)->report());

    expect($checks['realtime']['status'])->toBe('warning')
        ->and(json_encode($checks['realtime']))->not->toContain('reverb-secret-value')
        ->and(json_encode($checks['realtime']))->not->toContain('reverb-key-value');
});

test('a broken database connection is reported as critical', function () {
    config([
        'database.connections.health_broken' => [
            'driver' => 'sqlite',
            'database' => storage_path('framework/testing/missing-dir/does-not-exist.sqlite'),
            'foreign_key_constraints' => false,
        ],
        'database.default' => 'health_broken',
        'queue.default' => 'sync',
    ]);
    DB::purge('health_broken');

    $report = app(SystemHealthService::class)->report();

    expect(healthChecksByKey($report)['database']['status'])->toBe('critical')
        ->and($report['status'])->toBe('critical');
});

test('the operations health page renders computed checks for admins', function () {
    Role::findOrCreate('Admin');
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('admin.operations.health', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/operations/Health')
            ->has('health.checks', 7)
            ->has('health.scheduler', count(SchedulerHeartbeat::trackedCommands()))
            ->where('health.status', fn (string $status) => in_array($status, ['ok', 'degraded', 'critical'], true)));
});
