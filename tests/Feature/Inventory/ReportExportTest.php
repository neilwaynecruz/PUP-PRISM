<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockMovement;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

test('property custodian can export filtered product inventory as csv', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    Product::factory()->consumable()->create([
        'sku' => 'CSV-CONS-001',
        'name' => 'Consumable Export Match',
        'is_active' => true,
    ]);

    Product::factory()->asset()->create([
        'sku' => 'CSV-ASSET-001',
        'name' => 'Asset Export Miss',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('inventory.reports.products', [
            'format' => 'csv',
            'type' => 'consumable',
            'search' => 'Match',
            'active' => '1',
        ], absolute: false));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('text/csv');

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Product Inventory Listing')
        ->toContain('Consumable Export Match')
        ->toContain('CSV-CONS-001')
        ->not->toContain('CSV-ASSET-001');
});

test('admin can export filtered stock movement audit log as csv', function () {
    $admin = User::factory()->create(['name' => 'Audit Admin']);
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create([
        'sku' => 'AUDIT-SKU-001',
        'name' => 'Audit Product',
    ]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'movement_type' => 'receive',
        'qty_delta' => 12,
        'performed_by' => $admin->id,
        'notes' => 'Included movement',
    ]);

    StockMovement::factory()->create([
        'movement_type' => 'issue',
        'performed_by' => $admin->id,
        'notes' => 'Excluded movement',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('inventory.reports.movements', [
            'format' => 'csv',
            'type' => 'receive',
            'search' => 'AUDIT-SKU-001',
        ], absolute: false));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Stock Movement Audit Log')
        ->toContain('Included movement')
        ->toContain('AUDIT-SKU-001')
        ->not->toContain('Excluded movement');
});

test('admin can export asset condition reports as csv', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $position = Position::factory()->create();

    Asset::factory()->assignedToPosition($position)->create([
        'tag_code' => 'COND-0001',
        'status' => AssetStatus::Condemned,
    ]);

    Asset::factory()->assignedToPosition($position)->create([
        'tag_code' => 'UNS-0001',
        'status' => AssetStatus::Unserviceable,
    ]);

    Asset::factory()->assignedToPosition($position)->create([
        'tag_code' => 'AVL-0001',
        'status' => AssetStatus::Available,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('inventory.reports.asset-conditions', ['format' => 'csv'], absolute: false));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Unserviceable and Condemned Asset Report')
        ->toContain('COND-0001')
        ->toContain('UNS-0001')
        ->not->toContain('AVL-0001');
});

test('property custodian can export booking schedule reports as csv', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $position = Position::factory()->create();
    $product = Product::factory()->asset()->create(['name' => 'Booking Export Asset']);
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'tag_code' => 'BOOK-0001',
    ]);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Approved,
        'start_at' => now()->addDays(2),
        'end_at' => now()->addDays(2)->addHours(2),
        'purpose' => 'Included booking',
    ]);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Rejected,
        'start_at' => now()->subMonths(8),
        'end_at' => now()->subMonths(8)->addHours(2),
        'purpose' => 'Excluded booking',
    ]);

    $response = $this->actingAs($user)
        ->get(route('inventory.reports.bookings', ['format' => 'csv'], absolute: false));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Booking Schedule Report')
        ->toContain('Booking Export Asset')
        ->toContain('Included booking')
        ->not->toContain('Excluded booking');
});

test('property custodian can export requisition history reports as csv', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'sku' => 'REQ-CSV-001',
        'name' => 'Requisition Export Product',
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $user->id,
        'status' => RequisitionStatus::Submitted,
        'notes' => 'Requisition export notes',
    ]);

    RequisitionLine::factory()->create([
        'requisition_id' => $requisition->id,
        'product_id' => $product->id,
        'qty_requested' => 5,
        'qty_issued' => 0,
    ]);

    $response = $this->actingAs($user)
        ->get(route('inventory.reports.requisitions', ['format' => 'csv'], absolute: false));

    $response->assertOk();

    $content = $response->streamedContent();

    expect($content)
        ->toContain('Requisition History Report')
        ->toContain((string) $requisition->id)
        ->toContain('REQ-CSV-001')
        ->toContain('Requisition export notes');
});

dataset('pdf report routes', [
    'products' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Property Custodian')),
        'route_name' => 'inventory.reports.products',
        'route_parameters' => ['format' => 'pdf'],
    ],
    'movements' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin')),
        'route_name' => 'inventory.reports.movements',
        'route_parameters' => ['format' => 'pdf'],
    ],
    'asset conditions' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Admin')),
        'route_name' => 'inventory.reports.asset-conditions',
        'route_parameters' => ['format' => 'pdf'],
    ],
    'bookings' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Property Custodian')),
        'route_name' => 'inventory.reports.bookings',
        'route_parameters' => ['format' => 'pdf'],
    ],
    'requisitions' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Property Custodian')),
        'route_name' => 'inventory.reports.requisitions',
        'route_parameters' => ['format' => 'pdf'],
    ],
]);

test('pdf report routes download successfully', function (array $payload) {
    $response = $this->actingAs($payload['user'])->get(route(
        $payload['route_name'],
        $payload['route_parameters'],
        absolute: false,
    ));

    $response->assertOk();
    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
    expect((string) $response->headers->get('content-disposition'))->toContain('.pdf');
})->with('pdf report routes');

dataset('forbidden report routes', [
    'products export for plain user' => fn () => [
        'user' => User::factory()->create(),
        'route_name' => 'inventory.reports.products',
        'route_parameters' => ['format' => 'csv'],
    ],
    'movements export for supply head' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Supply Head')),
        'route_name' => 'inventory.reports.movements',
        'route_parameters' => ['format' => 'csv'],
    ],
    'asset condition export for supply head' => fn () => [
        'user' => tap(User::factory()->create(), fn (User $user) => $user->assignRole('Supply Head')),
        'route_name' => 'inventory.reports.asset-conditions',
        'route_parameters' => ['format' => 'csv'],
    ],
]);

test('restricted report routes remain forbidden', function (array $payload) {
    $this->actingAs($payload['user'])
        ->get(route(
            $payload['route_name'],
            $payload['route_parameters'],
            absolute: false,
        ))
        ->assertForbidden();
})->with('forbidden report routes');
