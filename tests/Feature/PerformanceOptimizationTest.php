<?php

use App\Enums\NotificationEventType;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\PdfRowLimitExceededException;
use App\Jobs\GeneratePdfReportJob;
use App\Models\ForecastSnapshot;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\DailyNotificationDigest;
use App\Notifications\ReportReadyNotification;
use App\Services\DashboardStatsService;
use App\Services\Forecasting\DemandForecaster;
use App\Services\Optimization\InventoryOptimizationService;
use App\Services\Reports\ForecastSummaryReportService;
use App\Services\Reports\InventoryOptimizationReportService;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Database\Factories\InventoryAlertFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function perfAdminUser(): User
{
    $user = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $user->assignRole('Admin');

    return $user;
}

test('inventory optimization caches abc analysis and product snapshots and invalidates on model updates', function () {
    config()->set('optimization.cache.enabled', true);

    $admin = perfAdminUser();
    $product = Product::factory()->consumable()->create(['unit_price' => 120.00]);
    ProductStock::query()->updateOrCreate(['product_id' => $product->id], ['on_hand_qty' => 25]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'issue',
        'qty_delta' => -10,
        'performed_by' => $admin->id,
        'performed_at' => now()->subDays(3),
    ]);

    /** @var InventoryOptimizationService $optimization */
    $optimization = app(InventoryOptimizationService::class);

    $firstAbc = $optimization->getAbcAnalysis();

    DB::flushQueryLog();
    DB::enableQueryLog();
    $cachedAbc = $optimization->getAbcAnalysis();
    $abcQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($abcQueries)->toBe(0)
        ->and($cachedAbc)->toBe($firstAbc);

    $firstProductOpt = $optimization->getProductOptimization($product);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $cachedProductOpt = $optimization->getProductOptimization($product);
    $optQueries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($optQueries)->toBe(0)
        ->and($cachedProductOpt['eoq'])->toBe($firstProductOpt['eoq']);

    $product->update(['unit_price' => 480.00]);

    $refreshed = $optimization->getProductOptimization($product->fresh());
    expect($refreshed['eoq']['eoq'])->toBeLessThan($firstProductOpt['eoq']['eoq']);
});

test('inventory optimization report reuses single abc pass with exact batch parity', function () {
    $admin = perfAdminUser();
    $products = Product::factory()->count(5)->consumable()->create(['unit_price' => 50]);

    foreach ($products as $index => $product) {
        ProductStock::query()->updateOrCreate(['product_id' => $product->id], ['on_hand_qty' => 20 + $index]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -($index + 1) * 5,
            'performed_by' => $admin->id,
            'performed_at' => now()->subDays(5),
        ]);
    }

    /** @var InventoryOptimizationService $optimization */
    $optimization = app(InventoryOptimizationService::class);
    /** @var InventoryOptimizationReportService $reportService */
    $reportService = app(InventoryOptimizationReportService::class);

    $request = Request::create('/inventory/reports/optimization/csv', 'GET');
    $request->setUserResolver(fn () => $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $report = $reportService->build($request);
    $reportRows = iterator_to_array($report->rows, false);
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($reportRows)->toHaveCount(5)
        ->and($queryCount)->toBeLessThanOrEqual(15);

    foreach ($products as $product) {
        $single = $optimization->getProductOptimization($product->fresh());
        $matched = collect($reportRows)->firstWhere('sku', $product->sku);

        expect($matched)->not->toBeNull()
            ->and($matched['abc_class'])->toBe($single['abc_classification'])
            ->and($matched['safety_stock'])->toBe($single['safety_stock']['safety_stock'])
            ->and($matched['reorder_point'])->toBe($single['safety_stock']['reorder_point'])
            ->and($matched['eoq'])->toBe($single['eoq']['eoq']);
    }
});

test('demand forecaster batches queries without n+1 scaling', function () {
    $admin = perfAdminUser();
    $products = Product::factory()->count(10)->consumable()->create();

    foreach ($products as $index => $product) {
        ProductStock::query()->updateOrCreate(['product_id' => $product->id], ['on_hand_qty' => 15 + $index]);
        StockMovement::factory()->create([
            'product_id' => $product->id,
            'movement_type' => 'issue',
            'qty_delta' => -($index + 2),
            'performed_by' => $admin->id,
            'performed_at' => now()->subDays(4),
        ]);
    }

    /** @var DemandForecaster $forecaster */
    $forecaster = app(DemandForecaster::class);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $allResults = $forecaster->forecastAll();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($allResults)->toHaveCount(10)
        ->and($queryCount)->toBeLessThanOrEqual(10);

    $sample = $forecaster->forecast($products->first()->fresh());
    $fromBatch = collect($allResults)->firstWhere('productId', $products->first()->id);

    expect($fromBatch)->not->toBeNull()
        ->and($fromBatch->predictedDailyConsumption)->toBe($sample->predictedDailyConsumption)
        ->and($fromBatch->recommendedReorderQty)->toBe($sample->recommendedReorderQty);
});

test('send notification digests skips inactive users and filters matching event types in sql', function () {
    Notification::fake();

    $activeUser = User::factory()->create(['is_active' => true]);
    $inactiveUser = User::factory()->create(['is_active' => false]);

    NotificationPreference::query()->create([
        'user_id' => $activeUser->id,
        'event_type' => NotificationEventType::LowStock->value,
        'mail_enabled' => true,
        'database_enabled' => true,
        'broadcast_enabled' => true,
        'digest_frequency' => 'daily',
    ]);

    NotificationPreference::query()->create([
        'user_id' => $activeUser->id,
        'event_type' => NotificationEventType::ExpiringStock->value,
        'mail_enabled' => true,
        'database_enabled' => true,
        'broadcast_enabled' => true,
        'digest_frequency' => 'instant',
    ]);

    NotificationPreference::query()->create([
        'user_id' => $inactiveUser->id,
        'event_type' => NotificationEventType::LowStock->value,
        'mail_enabled' => true,
        'database_enabled' => true,
        'broadcast_enabled' => true,
        'digest_frequency' => 'daily',
    ]);

    foreach ([$activeUser, $inactiveUser] as $targetUser) {
        $targetUser->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => 'low_stock',
            'data' => [
                'event_type' => NotificationEventType::LowStock->value,
                'title' => 'Low Stock',
                'message' => 'SKU low stock',
            ],
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $targetUser->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => 'expiring_stock',
            'data' => [
                'event_type' => NotificationEventType::ExpiringStock->value,
                'title' => 'Expiring Stock',
                'message' => 'Instant only',
            ],
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);
    }

    $this->artisan('app:send-notification-digests')->assertExitCode(0);

    Notification::assertSentTo($activeUser, DailyNotificationDigest::class, function (DailyNotificationDigest $mail): bool {
        return $mail->entries->count() === 1
            && ($mail->entries->first()->data['event_type'] ?? null) === NotificationEventType::LowStock->value;
    });
    Notification::assertNotSentTo($inactiveUser, DailyNotificationDigest::class);

    $activeDigestedCount = $activeUser->notifications()->whereNotNull('digested_at')->count();
    $inactiveDigestedCount = $inactiveUser->notifications()->whereNotNull('digested_at')->count();

    expect($activeDigestedCount)->toBe(1)
        ->and($inactiveDigestedCount)->toBe(0);
});

test('forecast summary report selects only latest snapshot per product with nulls ordered last', function () {
    $admin = perfAdminUser();
    $productA = Product::factory()->consumable()->create(['sku' => 'SUM-A', 'name' => 'Alpha Item']);
    $productB = Product::factory()->consumable()->create(['sku' => 'SUM-B', 'name' => 'Beta Item']);

    ForecastSnapshot::factory()->create([
        'product_id' => $productA->id,
        'forecast_date' => '2026-11-01',
        'predicted_days_until_stockout' => 9,
        'generated_at' => '2026-11-01 01:00:00',
    ]);
    ForecastSnapshot::factory()->create([
        'product_id' => $productA->id,
        'forecast_date' => '2026-11-05',
        'predicted_days_until_stockout' => 2,
        'generated_at' => '2026-11-05 01:00:00',
    ]);
    ForecastSnapshot::factory()->create([
        'product_id' => $productB->id,
        'forecast_date' => '2026-11-04',
        'predicted_days_until_stockout' => null,
        'generated_at' => '2026-11-04 01:00:00',
    ]);

    $request = Request::create('/inventory/reports/forecasting/csv', 'GET');
    $request->setUserResolver(fn () => $admin);

    /** @var ForecastSummaryReportService $service */
    $service = app(ForecastSummaryReportService::class);
    $report = $service->build($request);
    $rows = iterator_to_array($report->rows, false);

    expect($rows)->toHaveCount(2)
        ->and($rows[0]['sku'])->toBe('SUM-A')
        ->and($rows[0]['forecast_date'])->toBe('2026-11-05')
        ->and($rows[0]['predicted_days_until_stockout'])->toBe(2)
        ->and($rows[1]['sku'])->toBe('SUM-B')
        ->and($rows[1]['predicted_days_until_stockout'])->toBe('');
});

test('table report enforces bounded pdf row limit without materializing full generator', function () {
    $yielded = 0;

    $generator = (function () use (&$yielded) {
        for ($i = 1; $i <= 10000; $i++) {
            $yielded++;
            yield ['col' => $i];
        }
    })();

    $report = new TableReport(
        title: 'Large Streamed Report',
        filenameBase: 'large-report',
        filters: [],
        columns: ['col' => 'Column'],
        rows: $generator,
        generatedBy: 'Admin',
        generatedAt: CarbonImmutable::now(),
    );

    expect(fn () => $report->rowsForPdf(maxRows: 5))
        ->toThrow(PdfRowLimitExceededException::class);

    expect($yielded)->toBe(6);
});

test('oversized pdf export queues background job and serves signed generated download', function () {
    Storage::fake('local');
    Bus::fake([GeneratePdfReportJob::class]);

    config()->set('reports.max_pdf_rows', 2);
    config()->set('reports.queued_pdf_disk', 'local');

    $admin = perfAdminUser();
    Product::factory()->count(3)->consumable()->create();

    $this->actingAs($admin)
        ->getJson(route('inventory.reports.products', ['format' => 'pdf'], absolute: false))
        ->assertStatus(202)
        ->assertJsonPath('queued', true)
        ->assertJsonPath('max_pdf_rows', 2);

    Bus::assertDispatched(GeneratePdfReportJob::class, function (GeneratePdfReportJob $job) use ($admin): bool {
        return $job->reportKey === 'products' && $job->userId === $admin->id;
    });

    $this->actingAs($admin)
        ->from(route('inventory.products.index', absolute: false))
        ->get(route('inventory.reports.products', ['format' => 'pdf'], absolute: false))
        ->assertRedirect(route('inventory.products.index', absolute: false));

    Notification::fake();

    $job = new GeneratePdfReportJob('products', [], $admin->id);
    app()->call([$job, 'handle']);

    $downloadUrl = null;

    Notification::assertSentTo($admin, ReportReadyNotification::class, function (ReportReadyNotification $notification) use (&$downloadUrl): bool {
        $downloadUrl = $notification->downloadUrl;

        return $notification->reportTitle === 'Product Inventory Listing';
    });

    expect($downloadUrl)->not->toBeNull();

    $otherUser = perfAdminUser();

    $this->actingAs($otherUser)
        ->get($downloadUrl)
        ->assertForbidden();

    $downloadResponse = $this->actingAs($admin)->get($downloadUrl);
    $downloadResponse->assertOk();
    expect($downloadResponse->headers->get('content-type'))->toContain('application/pdf');
});

test('notification controller extracts distinct sorted categories via sql', function () {
    $user = perfAdminUser();

    foreach (['requisitions', 'inventory', 'requisitions', 'bookings'] as $category) {
        $user->notifications()->create([
            'id' => (string) str()->uuid(),
            'type' => 'test_notification',
            'data' => [
                'category' => $category,
                'title' => 'Notice',
                'message' => 'Test message',
            ],
        ]);
    }

    $this->actingAs($user)
        ->get(route('notifications.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('categories', ['bookings', 'inventory', 'requisitions']));
});

test('dashboard supplier performance aggregates in sql and validates max date range', function () {
    $admin = perfAdminUser();
    $supplier = Supplier::factory()->create(['name' => 'Alpha Supply']);

    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Received,
        'sent_at' => '2026-11-01 00:00:00',
        'received_at' => '2026-11-05 12:00:00',
    ]);
    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
        'sent_at' => '2026-11-02 00:00:00',
        'received_at' => null,
    ]);
    $deleted = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Draft,
    ]);
    $deleted->delete();

    /** @var DashboardStatsService $stats */
    $stats = app(DashboardStatsService::class);
    $performance = $stats->supplierPerformance();

    expect($performance)->toHaveCount(1)
        ->and($performance[0]['total_pos'])->toBe(2)
        ->and($performance[0]['open_pos'])->toBe(1)
        ->and($performance[0]['avg_lead_time_days'])->toBe(4.5);

    $this->actingAs($admin)
        ->get(route('dashboard', ['from' => '2020-01-01', 'to' => '2026-11-01'], absolute: false))
        ->assertSessionHasErrors('to');
});

test('alerts inbox summary aggregates counts accurately in a single grouped query', function () {
    $admin = perfAdminUser();

    InventoryAlertFactory::new()->create([
        'type' => 'low_stock',
        'acknowledged_at' => null,
        'assigned_to' => null,
    ]);
    InventoryAlertFactory::new()->create([
        'type' => 'low_stock',
        'acknowledged_at' => now(),
        'assigned_to' => $admin->id,
    ]);
    InventoryAlertFactory::new()->create([
        'type' => 'expiring_stock',
        'acknowledged_at' => now(),
        'assigned_to' => null,
    ]);
    InventoryAlertFactory::new()->resolved()->create([
        'type' => 'resolved_only',
        'resolved_at' => now()->subDay(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.alerts.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.active', 3)
            ->where('summary.unacknowledged', 1)
            ->where('summary.acknowledged', 2)
            ->where('summary.assigned_to_me', 1)
            ->where('summary.resolved_this_week', 1)
            ->where('summary.active_by_type', [
                'expiring_stock' => 1,
                'low_stock' => 2,
            ]));
});
