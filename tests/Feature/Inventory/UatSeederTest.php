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
use App\Models\Category;
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
use App\Models\UnitOfMeasurement;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
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
    expect(Supplier::query()->count())->toBeGreaterThanOrEqual(15);
    expect(PurchaseOrder::query()->count())->toBeGreaterThanOrEqual(21);
    expect(ForecastSnapshot::query()->count())->toBeGreaterThanOrEqual(1);
    expect(InventoryAlert::query()->count())->toBeGreaterThanOrEqual(3);
    expect(AuditLog::query()->count())->toBeGreaterThanOrEqual(5);
    expect(DB::table('notifications')->count())->toBeGreaterThanOrEqual(5);
    expect(StockMovement::query()->distinct()->pluck('movement_type')->sort()->values()->all())
        ->toEqualCanonicalizing(['adjustment', 'condemn', 'cycle_count', 'issue', 'receive', 'return', 'transfer']);
});

test('database seeder provides the approved institutional catalog and facility classifications', function () {
    seed(DatabaseSeeder::class);

    expect(Category::query()->whereIn('name', [
        'Office Supplies',
        'Equipment',
        'Furniture and Fixtures',
        'Facilities Equipment',
    ])->count())->toBe(4)
        ->and(UnitOfMeasurement::query()->whereIn('code', ['piece', 'ream', 'box'])->count())->toBe(3)
        ->and(Product::withTrashed()->count())->toBeBetween(80, 150)
        ->and(Asset::query()->count())->toBeBetween(150, 300)
        ->and(Supplier::query()->count())->toBeBetween(15, 30)
        ->and(Location::query()->count())->toBeBetween(25, 60)
        ->and(Location::query()->whereIn('facility_group', ['academic', 'administrative', 'sports', 'events'])->distinct()->count('facility_group'))->toBe(4)
        ->and(Location::query()->where('location_type', 'gymnasium')->where('is_bookable', true)->exists())->toBeTrue()
        ->and(Product::query()->where('sku', 'OFF-PAPER-A4-80')->whereNotNull('unit_of_measurement_id')->exists())->toBeTrue()
        ->and(Product::query()->where('sku', 'AST-AC-SPLIT')->where('is_bookable', false)->exists())->toBeTrue()
        ->and(Asset::query()->whereNotNull('serial_number')->whereNotNull('acquisition_date')->count())->toBeGreaterThanOrEqual(150);
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
        expect(Storage::disk($attachment->disk)->exists($attachment->path))->toBeTrue();
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

    /** @var Collection<int, StockMovement> $movements */
    $movements = StockMovement::query()
        ->whereNotNull('qty_delta')
        ->get();

    $movements
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

test('forecast products have four years of traceable demand history', function () {
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

        expect($issueDates->count())->toBeGreaterThanOrEqual(48)
            ->and($issueDates->first()?->diffInMonths($issueDates->last()))->toBeGreaterThanOrEqual(47.0)
            ->and($issueDates->map(static fn (CarbonImmutable $date): string => $date->format('Y-m'))->unique()->count())->toBeGreaterThanOrEqual(48)
            ->and(StockLot::query()->whereBelongsTo($product)->count())->toBeGreaterThanOrEqual(16)
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
    $initialDate = CarbonImmutable::parse('2026-10-15 09:00:00', (string) config('app.timezone'));
    CarbonImmutable::setTestNow($initialDate);

    try {
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

        CarbonImmutable::setTestNow($initialDate->addDay());
        seed(DatabaseSeeder::class);

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
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('month-boundary reruns roll historical demand without unintended movement duplicates', function () {
    $initialDate = CarbonImmutable::parse('2026-10-31 09:00:00', (string) config('app.timezone'));
    $forecastSkus = [
        'CON-PAPER-A4',
        'CON-INK-BLK',
        'CON-ALCOHOL-70',
        'CON-BAT-AA',
        'CON-DISINF-01',
        'CON-GLOVES-M',
    ];
    $expectedNewQuarterReferences = [
        'DR-202607-A4P',
        'DR-202607-ALC',
        'DR-202607-BAT',
        'DR-202607-DSF',
        'DR-202607-GLV',
        'DR-202607-INK',
    ];
    $movementIdentity = static fn (StockMovement $movement): string => sprintf('%s|%s|%s|%s',
        $movement->movement_type,
        $movement->product_id,
        $movement->stock_lot_id,
        $movement->notes,
    );

    CarbonImmutable::setTestNow($initialDate);

    try {
        seed(DatabaseSeeder::class);

        $forecastProductIds = Product::query()
            ->whereIn('sku', $forecastSkus)
            ->pluck('id');
        // Office supply (OFF-*) history rolls a month forward on its own; it is covered by UatOperationalHistoryTest.
        $curatedMovements = static fn (): int => StockMovement::query()
            ->whereDoesntHave('product', static fn (Builder $query): Builder => $query->withTrashed()->whereLike('sku', 'OFF-%'))
            ->count();
        $movementsBefore = $curatedMovements();
        /** @var Collection<int, StockMovement> $historicalMovementsBefore */
        $historicalMovementsBefore = StockMovement::query()
            ->whereIn('product_id', $forecastProductIds)
            ->where('movement_type', 'issue')
            ->where('notes', 'like', 'Monthly % allocation - %')
            ->get();

        /** @var Collection<string, int> $historicalMovementIdsBefore */
        $historicalMovementIdsBefore = $historicalMovementsBefore
            ->mapWithKeys(static fn (StockMovement $movement): array => [
                $movementIdentity($movement) => $movement->id,
            ]);

        CarbonImmutable::setTestNow($initialDate->addDay());
        seed(DatabaseSeeder::class);

        /** @var Collection<int, StockMovement> $historicalMovementsAfter */
        $historicalMovementsAfter = StockMovement::query()
            ->whereIn('product_id', $forecastProductIds)
            ->where('movement_type', 'issue')
            ->where('notes', 'like', 'Monthly % allocation - %')
            ->get();
        /** @var Collection<string, int> $historicalMovementIdsAfter */
        $historicalMovementIdsAfter = $historicalMovementsAfter
            ->mapWithKeys(static fn (StockMovement $movement): array => [
                $movementIdentity($movement) => $movement->id,
            ]);
        $stableHistoricalIdentityKeys = $historicalMovementIdsBefore
            ->keys()
            ->intersect($historicalMovementIdsAfter->keys());
        $duplicateMovementIdentities = StockMovement::query()
            ->whereIn('product_id', $forecastProductIds)
            ->get()
            ->groupBy($movementIdentity)
            ->filter(static fn (Collection $movements): bool => $movements->count() > 1);

        expect($curatedMovements())->toBe($movementsBefore + 7)
            ->and($historicalMovementIdsBefore)->toHaveCount(288)
            ->and($historicalMovementIdsAfter)->toHaveCount(288)
            ->and($stableHistoricalIdentityKeys)->toHaveCount(282)
            ->and($stableHistoricalIdentityKeys->every(
                fn (string $key): bool => $historicalMovementIdsAfter->get($key) === $historicalMovementIdsBefore->get($key),
            ))->toBeTrue()
            ->and($historicalMovementsAfter->filter(
                fn (StockMovement $movement): bool => str_ends_with((string) $movement->notes, 'July 2022'),
            ))->toBeEmpty()
            ->and($historicalMovementsAfter->filter(
                fn (StockMovement $movement): bool => str_ends_with((string) $movement->notes, 'July 2026'),
            ))->toHaveCount(6)
            ->and(StockLot::query()
                ->whereIn('product_id', $forecastProductIds)
                ->whereIn('reference_no', $expectedNewQuarterReferences)
                ->pluck('reference_no')
                ->sort()
                ->values()
                ->all())->toBe($expectedNewQuarterReferences)
            ->and(StockMovement::query()
                ->where('movement_type', 'receive')
                ->whereHas('stockLot', static fn (Builder $query): Builder => $query->whereIn('reference_no', $expectedNewQuarterReferences))
                ->count())->toBe(6)
            ->and($duplicateMovementIdentities)->toBeEmpty()
            ->and(StockMovement::query()
                ->whereIn('product_id', $forecastProductIds)
                ->where('movement_type', 'issue')
                ->whereNull('stock_lot_id')
                ->exists())->toBeFalse();

        Product::query()
            ->whereIn('id', $forecastProductIds)
            ->get()
            ->each(function (Product $product): void {
                expect((int) $product->lots()->sum('qty_remaining'))
                    ->toBe((int) ($product->stock()->value('on_hand_qty') ?? 0));
            });

        StockMovement::query()
            ->whereIn('product_id', $forecastProductIds)
            ->whereNotNull('qty_delta')
            ->get()
            ->each(function (StockMovement $movement): void {
                expect((int) $movement->qty_after)
                    ->toBe((int) $movement->qty_before + (int) $movement->qty_delta)
                    ->and((int) $movement->qty_after)->toBeGreaterThanOrEqual(0);
            });
    } finally {
        CarbonImmutable::setTestNow();
    }
});

test('year-boundary reruns preserve master identities and valid four-year ledgers', function () {
    $initialDate = CarbonImmutable::parse('2026-12-31 09:00:00', (string) config('app.timezone'));
    CarbonImmutable::setTestNow($initialDate);

    try {
        seed(DatabaseSeeder::class);

        $productIds = Product::withTrashed()->pluck('id', 'sku');
        $assetIds = Asset::query()->pluck('id', 'tag_code');
        $locationIds = Location::query()->pluck('id', 'code');
        $unitIds = UnitOfMeasurement::query()->pluck('id', 'code');

        CarbonImmutable::setTestNow($initialDate->addDay());
        seed(DatabaseSeeder::class);

        expect(Product::withTrashed()->pluck('id', 'sku')->all())->toBe($productIds->all())
            ->and(Asset::query()->pluck('id', 'tag_code')->all())->toBe($assetIds->all())
            ->and(Location::query()->pluck('id', 'code')->all())->toBe($locationIds->all())
            ->and(UnitOfMeasurement::query()->pluck('id', 'code')->all())->toBe($unitIds->all())
            ->and(Product::withTrashed()->select('sku')->groupBy('sku')->havingRaw('COUNT(*) > 1')->exists())->toBeFalse()
            ->and(Asset::query()->whereNotNull('serial_number')->select('serial_number')->groupBy('serial_number')->havingRaw('COUNT(*) > 1')->exists())->toBeFalse()
            ->and(Location::query()->select('code')->groupBy('code')->havingRaw('COUNT(*) > 1')->exists())->toBeFalse()
            ->and(PurchaseOrder::query()->select('po_number')->groupBy('po_number')->havingRaw('COUNT(*) > 1')->exists())->toBeFalse()
            ->and(PurchaseOrder::query()->whereLike('po_number', 'PO-HIST-%')->whereNotLike('po_number', 'PO-HIST-%-%')->count())->toBe(16);

        StockMovement::query()
            ->whereNotNull('qty_delta')
            ->get()
            ->each(function (StockMovement $movement): void {
                expect((int) $movement->qty_after)
                    ->toBe((int) $movement->qty_before + (int) $movement->qty_delta)
                    ->and((int) $movement->qty_after)->toBeGreaterThanOrEqual(0);
            });
    } finally {
        CarbonImmutable::setTestNow();
    }
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
