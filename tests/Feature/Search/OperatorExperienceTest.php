<?php

use App\Enums\BookingStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('global search returns matching products and requisitions for authorized users', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-GLOBAL-001',
        'name' => 'Global Search Paper',
    ]);

    $requester = User::factory()->create(['name' => 'Global Requester']);
    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    $this->actingAs($admin)
        ->getJson(route('search', ['q' => 'Global'], absolute: false))
        ->assertOk()
        ->assertJsonPath('data.0.type', 'product')
        ->assertJsonFragment(['title' => 'Global Search Paper'])
        ->assertJsonFragment(['title' => 'Requisition #'.$requisition->id]);
});

test('global search requires at least two characters', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->getJson(route('search', ['q' => 'a'], absolute: false))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['q']);
});

test('requisition index supports search and status filters', function () {
    $position = Position::factory()->create();
    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $matchingRequester = User::factory()->create(['name' => 'Filter Requester Alpha']);
    $otherRequester = User::factory()->create(['name' => 'Other Requester']);

    Requisition::factory()->create([
        'requester_id' => $matchingRequester->id,
        'requester_position_id' => $position->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    Requisition::factory()->create([
        'requester_id' => $otherRequester->id,
        'requester_position_id' => $position->id,
        'status' => RequisitionStatus::Issued,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', [
            'search' => 'Filter Requester',
            'status' => RequisitionStatus::Submitted->value,
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/requisitions/Index')
            ->where('filters.search', 'Filter Requester')
            ->where('filters.status', RequisitionStatus::Submitted->value)
            ->has('requisitions.data', 1));
});

test('booking index supports list search and status filters', function () {
    $position = Position::factory()->create();
    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'tag_code' => 'TAG-FILTER-001',
    ]);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Requested,
    ]);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Approved,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.bookings.index', [
            'search' => 'TAG-FILTER-001',
            'status' => BookingStatus::Requested->value,
        ], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/bookings/Index')
            ->where('filters.search', 'TAG-FILTER-001')
            ->where('filters.status', BookingStatus::Requested->value)
            ->has('bookings.data', 1));
});

test('property custodian dashboard receives role-specific summary data', function () {
    $position = Position::factory()->create();
    $custodian = User::factory()->assignedPosition($position)->create();
    $custodian->assignRole('Property Custodian');

    Requisition::factory()->create([
        'requester_id' => $custodian->id,
        'requester_position_id' => $position->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    $this->actingAs($custodian)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboardVariant', 'custodian')
            ->where('custodianSummary.my_open_requisitions', 1));
});

test('admin dashboard exposes real kpi summary values', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('dashboardVariant', 'admin')
            ->has('kpiSummary.issued_today_count')
            ->has('kpiSummary.near_expiry_batch_count'));
});

test('supply head can export procurement and slow-moving reports', function () {
    $supplyHead = User::factory()->withTwoFactor()->create();
    $supplyHead->assignRole('Supply Head');

    $supplier = Supplier::factory()->create(['name' => 'Report Supplier']);
    PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.reports.procurement', ['format' => 'csv'], absolute: false))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');

    $this->actingAs($supplyHead)
        ->get(route('inventory.reports.slow-moving', ['format' => 'csv'], absolute: false))
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});
