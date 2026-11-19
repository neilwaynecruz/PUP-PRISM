<?php

use App\Support\SchedulerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;

test('local preflight is read only and never prints credentials', function () {
    config([
        'app.url' => 'http://localhost:8123',
        'app.debug' => false,
        'app.key' => 'base64:private-key-not-for-output',
        'queue.default' => 'database',
        'queue.connections.database.retry_after' => 90,
        'cache.default' => 'file',
        'mail.default' => 'resend',
        'services.resend.key' => 're_private-not-for-output',
        'retention.scheduling_enabled' => false,
    ]);
    $before = DB::table('users')->count();
    Artisan::call('app:local-runtime-check', ['--url' => 'http://localhost:8123', '--json' => true]);
    $output = Artisan::output();
    $report = json_decode($output, true, flags: JSON_THROW_ON_ERROR);

    expect($report['checks']['matching_url'])->toBeTrue()
        ->and($report['checks']['cleanup_disabled'])->toBeTrue()
        ->and($report['checks']['database_connected'])->toBeTrue()
        ->and($output)->not->toContain('private-key-not-for-output', 're_private-not-for-output')
        ->and(DB::table('users')->count())->toBe($before);
});

test('unsafe local runtime configuration is rejected', function (string $key, mixed $value, string $check) {
    config([$key => $value]);
    $exit = Artisan::call('app:local-runtime-check', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($exit)->toBe(1)->and($report['ready'])->toBeFalse()->and($report['checks'][$check])->toBeFalse();
})->with([
    ['app.url', 'http://192.168.1.9:8000', 'loopback_url'],
    ['app.url', 'http://localhost:8000/unexpected', 'loopback_url'],
    ['queue.default', 'sync', 'database_queue'],
    ['queue.connections.database.retry_after', 60, 'safe_queue_retry_window'],
    ['retention.scheduling_enabled', true, 'cleanup_disabled'],
    ['app.debug', true, 'debug_disabled'],
]);

test('scheduled destructive cleanup requires explicit opt in', function (bool $enabled) {
    config(['retention.scheduling_enabled' => $enabled]);
    $schedule = new Schedule;
    app()->instance(Schedule::class, $schedule);
    ScheduleFacade::clearResolvedInstance(Schedule::class);
    require base_path('routes/console.php');
    $commands = collect($schedule->events())->pluck('command')->implode("\n");

    expect(str_contains($commands, 'trash:cleanup'))->toBe($enabled)
        ->and(str_contains($commands, 'app:prune-operational-data'))->toBe($enabled)
        ->and(in_array(SchedulerHeartbeat::COMMAND_TRASH_CLEANUP, SchedulerHeartbeat::trackedCommands(), true))->toBe($enabled)
        ->and(in_array(SchedulerHeartbeat::COMMAND_PRUNE_OPERATIONAL_DATA, SchedulerHeartbeat::trackedCommands(), true))->toBe($enabled)
        ->and($commands)->toContain('app:generate-demand-forecasts', 'app:inventory-generate-alerts', 'app:send-notification-digests');
})->with([false, true]);
