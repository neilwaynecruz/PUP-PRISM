<?php

use App\Models\AuditLog;
use App\Models\ForecastSnapshot;
use App\Models\Product;
use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('operational data prune removes aged audit logs forecast snapshots notifications and failed jobs', function () {
    config([
        'retention.audit_logs_days' => 30,
        'retention.forecast_snapshots_days' => 30,
        'retention.notifications_days' => 30,
        'retention.failed_jobs_days' => 30,
    ]);

    $auditCutoff = Carbon::now()->subDays(31);
    $auditLog = AuditLog::query()->create([
        'action' => 'update',
        'model_type' => Product::class,
        'model_id' => 1,
        'description' => 'Stale audit entry',
    ]);
    AuditLog::query()->whereKey($auditLog->id)->update([
        'created_at' => $auditCutoff,
        'updated_at' => $auditCutoff,
    ]);

    ForecastSnapshot::factory()->create([
        'forecast_date' => Carbon::now()->subDays(31)->toDateString(),
    ]);

    $user = User::factory()->create();
    $notificationId = (string) Str::uuid();
    $notificationCutoff = Carbon::now()->subDays(31);

    DB::table('notifications')->insert([
        'id' => $notificationId,
        'type' => 'App\\Notifications\\LowStockAlertNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['event_type' => 'inventory.low_stock']),
        'read_at' => $notificationCutoff,
        'digested_at' => null,
        'created_at' => $notificationCutoff,
        'updated_at' => $notificationCutoff,
    ]);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'database',
        'queue' => 'notifications',
        'payload' => '{}',
        'exception' => 'Test exception',
        'failed_at' => $notificationCutoff,
    ]);

    $this->artisan('app:prune-operational-data')
        ->assertExitCode(0);

    expect(AuditLog::query()->count())->toBe(0);
    expect(ForecastSnapshot::query()->count())->toBe(0);
    expect(DatabaseNotification::query()->count())->toBe(0);
    expect(DB::table('failed_jobs')->count())->toBe(0);
});

test('operational data prune dry run does not delete rows', function () {
    config(['retention.audit_logs_days' => 30]);

    $cutoff = Carbon::now()->subDays(31);
    $auditLog = AuditLog::query()->create([
        'action' => 'create',
        'model_type' => Product::class,
        'model_id' => 1,
        'description' => 'Retained until dry run completes',
    ]);
    AuditLog::query()->whereKey($auditLog->id)->update([
        'created_at' => $cutoff,
        'updated_at' => $cutoff,
    ]);

    $this->artisan('app:prune-operational-data', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(AuditLog::query()->count())->toBe(1);
});

test('operational data prune keeps unread undigested notifications', function () {
    config(['retention.notifications_days' => 30]);

    $user = User::factory()->create();
    $cutoff = Carbon::now()->subDays(31);

    DB::table('notifications')->insert([
        'id' => (string) Str::uuid(),
        'type' => 'App\\Notifications\\LowStockAlertNotification',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => json_encode(['event_type' => 'inventory.low_stock']),
        'read_at' => null,
        'digested_at' => null,
        'created_at' => $cutoff,
        'updated_at' => $cutoff,
    ]);

    $this->artisan('app:prune-operational-data', ['--only' => 'notifications'])
        ->assertExitCode(0);

    expect(DatabaseNotification::query()->count())->toBe(1);
});

test('operational data prune records scheduler heartbeat', function () {
    Cache::flush();

    config(['retention.audit_logs_days' => 0, 'retention.forecast_snapshots_days' => 0, 'retention.notifications_days' => 0, 'retention.failed_jobs_days' => 0]);

    $this->artisan('app:prune-operational-data')
        ->assertExitCode(0);

    expect(Cache::get(SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_PRUNE_OPERATIONAL_DATA)))
        ->toBeString();
});
