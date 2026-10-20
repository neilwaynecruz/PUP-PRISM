<?php

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Department;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\E2eSeeder;
use Database\Seeders\UatInventorySeeder;
use Database\Seeders\UatMonitoringSeeder;
use Database\Seeders\UatOrganizationSeeder;
use Database\Seeders\UatProcurementSeeder;
use Database\Seeders\UatWorkflowSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

test('database seeder creates uat organization and walkthrough data', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Department::query()->where('name', 'College of Engineering')->exists())->toBeTrue();
    expect(Position::query()->where('code', 'POS-COE-PC')->exists())->toBeTrue();
    expect(User::query()->where('email', 'admin@local.test')->whereNotNull('position_id')->exists())->toBeTrue();
    expect(User::query()->where('email', 'eng.custodian@local.test')->exists())->toBeTrue();
    expect(Asset::query()->where('tag_code', 'AST-COE-0001')->whereNotNull('position_id')->exists())->toBeTrue();
    expect(Booking::withTrashed()->count())->toBeGreaterThanOrEqual(5);
    expect(Requisition::withTrashed()->count())->toBeGreaterThanOrEqual(7);
    expect(Product::withTrashed()->where('sku', 'CON-MARKER-BLK')->whereNotNull('deleted_at')->exists())->toBeTrue();
    expect(Booking::onlyTrashed()->exists())->toBeTrue();
    expect(Requisition::onlyTrashed()->exists())->toBeTrue();
    expect(RequisitionTemplate::query()->count())->toBeGreaterThanOrEqual(3);
    expect(Supplier::query()->count())->toBeGreaterThanOrEqual(4);
    expect(PurchaseOrder::query()->count())->toBeGreaterThanOrEqual(5);
    expect(ForecastSnapshot::query()->count())->toBeGreaterThanOrEqual(1);
    expect(InventoryAlert::query()->count())->toBeGreaterThanOrEqual(3);
    expect(AuditLog::query()->count())->toBeGreaterThanOrEqual(5);
    expect(DB::table('notifications')->count())->toBeGreaterThanOrEqual(5);
    expect(StockMovement::query()->distinct()->pluck('movement_type')->sort()->values()->all())
        ->toEqualCanonicalizing(['condemn', 'issue', 'receive', 'return', 'transfer']);
});

test('database seeder can be rerun without duplicating seeded positions', function () {
    $this->seed(DatabaseSeeder::class);

    $spmo = Department::query()->where('code', 'SPMO')->firstOrFail();
    $positionCount = Position::query()->count();
    $userCount = User::query()->count();
    $notificationCount = DB::table('notifications')->count();
    $templateCount = RequisitionTemplate::query()->count();

    $this->seed(DatabaseSeeder::class);

    expect(Position::query()->where('department_id', $spmo->id)->where('title', 'Supply Head')->count())->toBe(1);
    expect(Position::query()->where('code', 'POS-SUP-HEAD')->count())->toBe(1);
    expect(Position::query()->count())->toBe($positionCount);
    expect(User::query()->count())->toBe($userCount);
    expect(DB::table('notifications')->count())->toBe($notificationCount);
    expect(RequisitionTemplate::query()->count())->toBe($templateCount);
});

test('production database seeding excludes demo users and direct demo seeders are blocked', function () {
    $originalEnvironment = $this->app->environment();
    $this->app['env'] = 'production';

    try {
        (new DatabaseSeeder)->setContainer($this->app)->run();

        expect(User::query()->where('email', 'admin@local.test')->exists())->toBeFalse();
        foreach ([
            UatOrganizationSeeder::class,
            UatInventorySeeder::class,
            UatProcurementSeeder::class,
            UatWorkflowSeeder::class,
            UatMonitoringSeeder::class,
        ] as $seederClass) {
            expect(fn () => (new $seederClass)->setContainer($this->app)->run())
                ->toThrow(RuntimeException::class, 'production');
        }

        expect(fn () => (new E2eSeeder)->setContainer($this->app)->run())
            ->toThrow(RuntimeException::class, 'production');
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
});

test('the e2e setup command refuses to run in production', function () {
    $originalEnvironment = $this->app->environment();
    $this->app['env'] = 'production';

    try {
        $this->artisan('e2e:setup --fresh')
            ->expectsOutputToContain('cannot run in production')
            ->assertFailed();
    } finally {
        $this->app['env'] = $originalEnvironment;
    }
});

test('the e2e setup command refuses non dedicated databases', function () {
    $this->artisan('e2e:setup --fresh')
        ->expectsOutputToContain('requires the dedicated database/e2e.sqlite')
        ->assertFailed();
});

test('the e2e two factor helper command is restricted to the e2e environment', function () {
    $this->artisan('e2e:configure-two-factor admin@example.test')
        ->expectsOutputToContain('restricted to the E2E environment')
        ->assertFailed();
});

test('the e2e setup command refuses similarly named sqlite databases outside the dedicated path', function () {
    $unsafeDirectory = storage_path('framework/testing/e2e-guard/database');
    $unsafeDatabase = $unsafeDirectory.DIRECTORY_SEPARATOR.'e2e.sqlite';
    $originalDatabase = config('database.connections.sqlite.database');

    File::ensureDirectoryExists($unsafeDirectory);
    File::put($unsafeDatabase, '');
    config()->set('database.connections.sqlite.database', $unsafeDatabase);

    try {
        $this->artisan('e2e:setup --fresh')
            ->expectsOutputToContain('requires the dedicated database/e2e.sqlite')
            ->assertFailed();
    } finally {
        config()->set('database.connections.sqlite.database', $originalDatabase);
        File::deleteDirectory(storage_path('framework/testing/e2e-guard'));
    }
});

test('rerunning the uat organization seeder preserves existing user security settings', function () {
    $this->seed(UatOrganizationSeeder::class);

    $user = User::query()->where('email', 'admin@local.test')->firstOrFail();
    $user->forceFill([
        'password' => 'a-user-selected-password',
        'email_verified_at' => null,
        'two_factor_secret' => encrypt('existing-two-factor-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['existing-code'], JSON_THROW_ON_ERROR)),
    ])->save();
    $user->syncRoles(['Supply Head']);

    $passwordHash = $user->getRawOriginal('password');
    $twoFactorSecret = $user->getRawOriginal('two_factor_secret');
    $twoFactorRecoveryCodes = $user->getRawOriginal('two_factor_recovery_codes');

    $this->seed(UatOrganizationSeeder::class);

    $user->refresh();

    expect($user->getRawOriginal('password'))->toBe($passwordHash);
    expect(Hash::check('a-user-selected-password', $user->password))->toBeTrue();
    expect($user->email_verified_at)->toBeNull();
    expect($user->getRawOriginal('two_factor_secret'))->toBe($twoFactorSecret);
    expect($user->getRawOriginal('two_factor_recovery_codes'))->toBe($twoFactorRecoveryCodes);
    expect($user->getRoleNames()->all())->toBe(['Supply Head']);
});
