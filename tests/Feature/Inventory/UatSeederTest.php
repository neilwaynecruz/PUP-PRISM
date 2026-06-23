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
use Illuminate\Support\Facades\DB;

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
