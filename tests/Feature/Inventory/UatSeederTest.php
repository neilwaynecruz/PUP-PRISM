<?php

use App\Enums\ApprovalStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetTransferStatus;
use App\Enums\InventoryCountStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Department;
use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\InventoryAlert;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\NotificationPreference;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\E2eSeeder;
use Database\Seeders\SupplierSeeder;
use Database\Seeders\UatAssetOperationsSeeder;
use Database\Seeders\UatInventorySeeder;
use Database\Seeders\UatMonitoringSeeder;
use Database\Seeders\UatOrganizationSeeder;
use Database\Seeders\UatProcurementSeeder;
use Database\Seeders\UatWorkflowSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\seed;

beforeEach(function () {
    Storage::fake('local');
});

test('database seeder creates uat organization and walkthrough data', function () {
    seed(DatabaseSeeder::class);

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
        ->toEqualCanonicalizing(['adjustment', 'condemn', 'cycle_count', 'issue', 'receive', 'return', 'transfer']);
});

test('origin factory can create a broad set of realistic unique sources', function () {
    $origins = Origin::factory()->count(12)->create();

    expect($origins)->toHaveCount(12)
        ->and($origins->pluck('name')->unique())->toHaveCount(12)
        ->and($origins->every(fn (Origin $origin): bool => $origin->is_active))->toBeTrue();
});

test('database seeder covers physical counts and the complete asset lifecycle', function () {
    seed(DatabaseSeeder::class);

    expect(Location::query()->count())->toBeGreaterThanOrEqual(14)
        ->and(Location::query()->where('is_active', false)->exists())->toBeTrue()
        ->and(Asset::query()->whereNull('current_location_id')->exists())->toBeFalse()
        ->and(InventoryCountSession::query()->pluck('status')->map(fn (InventoryCountStatus $status): string => $status->value)->unique()->all())
        ->toEqualCanonicalizing(['draft', 'in_progress', 'submitted', 'completed', 'cancelled'])
        ->and(AssetMaintenance::query()->pluck('status')->map(fn (MaintenanceStatus $status): string => $status->value)->unique()->all())
        ->toEqualCanonicalizing(['reported', 'scheduled', 'in_progress', 'completed', 'cancelled'])
        ->and(AssetTransfer::query()->pluck('status')->map(fn (AssetTransferStatus $status): string => $status->value)->unique()->all())
        ->toEqualCanonicalizing(['pending', 'approved', 'rejected', 'cancelled'])
        ->and(AssetLifecycleEvent::query()->pluck('event_type')->map(fn (AssetLifecycleEventType $type): string => $type->value)->unique()->all())
        ->toEqualCanonicalizing(['acquisition', 'condition_change', 'retirement', 'disposal'])
        ->and(AssetLifecycleEvent::query()->pluck('status')->map(fn (ApprovalStatus $status): string => $status->value)->unique()->all())
        ->toEqualCanonicalizing(['pending', 'approved', 'rejected'])
        ->and(Attachment::query()->count())->toBe(4);

    Attachment::query()->get()->each(function (Attachment $attachment): void {
        Storage::disk($attachment->disk)->assertExists($attachment->path);
        expect(hash('sha256', Storage::disk($attachment->disk)->get($attachment->path)))
            ->toBe($attachment->checksum);
    });
});

test('seeded stock lots balances and movement snapshots remain internally consistent', function () {
    seed(DatabaseSeeder::class);

    Product::query()
        ->where('type', 'consumable')
        ->get()
        ->each(function (Product $product): void {
            expect((int) $product->lots()->sum('qty_remaining'))
                ->toBe((int) ($product->stock()->value('on_hand_qty') ?? 0));
        });

    StockMovement::query()
        ->whereNotNull('qty_delta')
        ->get()
        ->each(function (StockMovement $movement): void {
            expect((int) $movement->qty_after)
                ->toBe((int) $movement->qty_before + (int) $movement->qty_delta)
                ->and((int) $movement->qty_after)->toBeGreaterThanOrEqual(0);
        });

    expect(StockMovement::query()->where('movement_type', 'issue')->whereNull('stock_lot_id')->exists())->toBeFalse()
        ->and(Product::query()->where('sku', 'CON-PAPER-A4')->firstOrFail()->lots()->oldest('received_at')->firstOrFail()->qty_remaining)->toBe(0)
        ->and(Product::query()->where('sku', 'CON-HSOAP-500')->firstOrFail()->stock?->on_hand_qty)->toBe(0)
        ->and(NotificationPreference::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(DB::table('purchase_order_sequences')->where('date_prefix', '20261001')->value('last_sequence'))->toBe(5);
});

test('forecast products have three years of traceable demand history', function () {
    seed(DatabaseSeeder::class);

    $forecastSkus = [
        'CON-PAPER-A4',
        'CON-INK-BLK',
        'CON-ALCOHOL-70',
        'CON-BAT-AA',
        'CON-DISINF-01',
        'CON-GLOVES-M',
    ];

    foreach ($forecastSkus as $sku) {
        $product = Product::query()->where('sku', $sku)->firstOrFail();
        $issueDates = StockMovement::query()
            ->whereBelongsTo($product)
            ->where('movement_type', 'issue')
            ->oldest('performed_at')
            ->pluck('performed_at')
            ->map(static fn (mixed $performedAt): CarbonImmutable => CarbonImmutable::parse((string) $performedAt));
        $profile = ForecastProfile::query()->whereBelongsTo($product)->firstOrFail();
        $snapshot = ForecastSnapshot::query()->whereBelongsTo($product)->latest('forecast_date')->firstOrFail();
        $lookbackDays = (int) $profile->getAttribute('lookback_days');
        $forecastHorizonDays = (int) $profile->getAttribute('forecast_horizon_days');
        $rawData = $snapshot->getAttribute('raw_data');

        expect($issueDates->count())->toBeGreaterThanOrEqual(35)
            ->and($issueDates->first()?->diffInMonths($issueDates->last()))->toBeGreaterThanOrEqual(35.0)
            ->and($issueDates->map(static fn (CarbonImmutable $date): string => $date->format('Y-m'))->unique()->count())->toBeGreaterThanOrEqual(34)
            ->and(StockLot::query()->whereBelongsTo($product)->count())->toBeGreaterThanOrEqual(12)
            ->and($lookbackDays)->toBe(365)
            ->and($forecastHorizonDays)->toBeLessThanOrEqual(90)
            ->and(data_get($rawData, 'summary.lookback_days'))->toBe(365)
            ->and(data_get($rawData, 'summary.forecast_horizon_days'))->toBe($forecastHorizonDays)
            ->and(data_get($rawData, 'summary.has_sufficient_history'))->toBeTrue();
    }

    expect(StockMovement::query()->where('movement_type', 'issue')->whereNull('stock_lot_id')->exists())->toBeFalse();
});

test('operational records use professional names without placeholder wording', function () {
    seed(DatabaseSeeder::class);

    $visibleValues = collect()
        ->merge(Product::withTrashed()->pluck('name'))
        ->merge(Supplier::query()->pluck('name'))
        ->merge(User::query()->pluck('name'))
        ->merge(Booking::withTrashed()->pluck('purpose'))
        ->merge(Requisition::withTrashed()->pluck('notes'));

    expect($visibleValues->filter(
        static fn (?string $value): bool => $value !== null
            && preg_match('/\b(?:uat|sample|demo|example|test)\b/i', $value) === 1,
    ))->toBeEmpty()
        ->and(User::query()->where('email', 'admin@local.test')->value('name'))->toBe('Adrian M. Mercado')
        ->and(Product::query()->where('sku', 'CON-PAPER-A4')->value('name'))
        ->toBe('Multipurpose Copy Paper, A4, 80 gsm, 500 Sheets/Ream');
});

test('rerunning operational seeders on a later day does not duplicate stable business records', function () {
    seed(DatabaseSeeder::class);

    $counts = [
        'users' => User::query()->count(),
        'products' => Product::withTrashed()->count(),
        'assets' => Asset::query()->count(),
        'bookings' => Booking::withTrashed()->count(),
        'requisitions' => Requisition::withTrashed()->count(),
        'movements' => StockMovement::query()->count(),
        'locations' => Location::query()->count(),
        'counts' => InventoryCountSession::query()->count(),
        'maintenances' => AssetMaintenance::query()->count(),
        'transfers' => AssetTransfer::query()->count(),
        'lifecycle_events' => AssetLifecycleEvent::query()->count(),
        'attachments' => Attachment::query()->count(),
        'purchase_orders' => PurchaseOrder::query()->count(),
        'notifications' => DB::table('notifications')->count(),
    ];

    CarbonImmutable::setTestNow(CarbonImmutable::now()->addDay());

    try {
        seed(DatabaseSeeder::class);
    } finally {
        CarbonImmutable::setTestNow();
    }

    expect(User::query()->count())->toBe($counts['users'])
        ->and(Product::withTrashed()->count())->toBe($counts['products'])
        ->and(Asset::query()->count())->toBe($counts['assets'])
        ->and(Booking::withTrashed()->count())->toBe($counts['bookings'])
        ->and(Requisition::withTrashed()->count())->toBe($counts['requisitions'])
        ->and(StockMovement::query()->count())->toBe($counts['movements'])
        ->and(Location::query()->count())->toBe($counts['locations'])
        ->and(InventoryCountSession::query()->count())->toBe($counts['counts'])
        ->and(AssetMaintenance::query()->count())->toBe($counts['maintenances'])
        ->and(AssetTransfer::query()->count())->toBe($counts['transfers'])
        ->and(AssetLifecycleEvent::query()->count())->toBe($counts['lifecycle_events'])
        ->and(Attachment::query()->count())->toBe($counts['attachments'])
        ->and(PurchaseOrder::query()->count())->toBe($counts['purchase_orders'])
        ->and(DB::table('notifications')->count())->toBe($counts['notifications']);
});

test('database seeder can be rerun without duplicating seeded positions', function () {
    seed(DatabaseSeeder::class);

    $spmo = Department::query()->where('code', 'SPMO')->firstOrFail();
    $positionCount = Position::query()->count();
    $userCount = User::query()->count();
    $notificationCount = DB::table('notifications')->count();
    $templateCount = RequisitionTemplate::query()->count();

    seed(DatabaseSeeder::class);

    expect(Position::query()->where('department_id', $spmo->id)->where('title', 'Supply Head')->count())->toBe(1);
    expect(Position::query()->where('code', 'POS-SUP-HEAD')->count())->toBe(1);
    expect(Position::query()->count())->toBe($positionCount);
    expect(User::query()->count())->toBe($userCount);
    expect(DB::table('notifications')->count())->toBe($notificationCount);
    expect(RequisitionTemplate::query()->count())->toBe($templateCount);
});

test('production database seeding excludes demo users and direct demo seeders are blocked', function () {
    /** @var Application $application */
    $application = app();
    $originalEnvironment = $application->environment();
    $application['env'] = 'production';

    try {
        (new DatabaseSeeder)->setContainer($application)->run();

        expect(User::query()->where('email', 'admin@local.test')->exists())->toBeFalse();
        /** @var list<class-string<Seeder>> $operationalSeeders */
        foreach ([
            UatOrganizationSeeder::class,
            UatInventorySeeder::class,
            UatProcurementSeeder::class,
            UatWorkflowSeeder::class,
            UatAssetOperationsSeeder::class,
            UatMonitoringSeeder::class,
            SupplierSeeder::class,
        ] as $seederClass) {
            expect(fn () => (new $seederClass)->setContainer($application)->run())
                ->toThrow(RuntimeException::class, 'production');
        }

        expect(fn () => (new E2eSeeder)->setContainer($application)->run())
            ->toThrow(RuntimeException::class, 'production');
    } finally {
        $application['env'] = $originalEnvironment;
    }
});

test('the e2e setup command refuses to run in production', function () {
    /** @var Application $application */
    $application = app();
    $originalEnvironment = $application->environment();
    $application['env'] = 'production';

    try {
        $exitCode = Artisan::call('e2e:setup', ['--fresh' => true]);

        expect($exitCode)->not->toBe(0)
            ->and(Artisan::output())->toContain('cannot run in production');
    } finally {
        $application['env'] = $originalEnvironment;
    }
});

test('the e2e setup command refuses non dedicated databases', function () {
    $exitCode = Artisan::call('e2e:setup', ['--fresh' => true]);

    expect($exitCode)->not->toBe(0)
        ->and(Artisan::output())->toContain('requires the dedicated database/e2e.sqlite');
});

test('the e2e two factor helper command is restricted to the e2e environment', function () {
    $exitCode = Artisan::call('e2e:configure-two-factor', ['email' => 'admin@example.test']);

    expect($exitCode)->not->toBe(0)
        ->and(Artisan::output())->toContain('restricted to the E2E environment');
});

test('the e2e setup command refuses similarly named sqlite databases outside the dedicated path', function () {
    $unsafeDirectory = storage_path('framework/testing/e2e-guard/database');
    $unsafeDatabase = $unsafeDirectory.DIRECTORY_SEPARATOR.'e2e.sqlite';
    $originalDatabase = config('database.connections.sqlite.database');

    File::ensureDirectoryExists($unsafeDirectory);
    File::put($unsafeDatabase, '');
    config()->set('database.connections.sqlite.database', $unsafeDatabase);

    try {
        $exitCode = Artisan::call('e2e:setup', ['--fresh' => true]);

        expect($exitCode)->not->toBe(0)
            ->and(Artisan::output())->toContain('requires the dedicated database/e2e.sqlite');
    } finally {
        config()->set('database.connections.sqlite.database', $originalDatabase);
        File::deleteDirectory(storage_path('framework/testing/e2e-guard'));
    }
});

test('rerunning the uat organization seeder preserves existing user security settings', function () {
    seed(UatOrganizationSeeder::class);

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

    seed(UatOrganizationSeeder::class);

    $user->refresh();

    expect($user->getRawOriginal('password'))->toBe($passwordHash);
    expect(Hash::check('a-user-selected-password', (string) $user->getAttribute('password')))->toBeTrue();
    expect($user->getAttribute('email_verified_at'))->toBeNull();
    expect($user->getRawOriginal('two_factor_secret'))->toBe($twoFactorSecret);
    expect($user->getRawOriginal('two_factor_recovery_codes'))->toBe($twoFactorRecoveryCodes);
    expect($user->getRoleNames()->all())->toBe(['Supply Head']);
});
