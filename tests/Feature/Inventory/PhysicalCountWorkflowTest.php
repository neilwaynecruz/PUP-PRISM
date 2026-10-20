<?php

use App\Enums\InventoryCountReviewStatus;
use App\Models\Asset;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\PhysicalCountService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function phaseFourCounter(string $role = 'Supply Head'): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole($role);

    return $user;
}

function phaseFourCountSession(User $creator, Product $product): InventoryCountSession
{
    return app(PhysicalCountService::class)->createSession($creator, [
        'counting_date' => now()->toDateString(),
        'scope' => 'Main supply room',
        'assigned_to' => $creator->id,
        'product_ids' => [$product->id],
        'asset_ids' => [],
        'remarks' => 'Quarterly physical count.',
    ]);
}

test('a count observation accounts for intervening movements without changing official stock', function () {
    $counter = phaseFourCounter();
    $this->actingAs($counter);
    $product = Product::factory()->consumable()->create();
    $stock = ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);
    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
    ]);

    $session = phaseFourCountSession($counter, $product);

    $lot->update(['qty_remaining' => 8]);
    $stock->update(['on_hand_qty' => 8]);
    StockMovement::factory()->create([
        'movement_type' => 'issue',
        'product_id' => $product->id,
        'stock_lot_id' => $lot->id,
        'qty_delta' => -2,
        'qty_before' => 10,
        'qty_after' => 8,
        'performed_by' => $counter->id,
        'performed_at' => now(),
    ]);

    $line = $session->lines()->firstOrFail();
    app(PhysicalCountService::class)->recordLine($counter, $session, $line, 8, 'Eight units found.');

    expect($line->fresh())
        ->expected_qty_snapshot->toBe(10)
        ->expected_qty_at_count->toBe(8)
        ->counted_qty->toBe(8)
        ->variance_qty->toBe(0)
        ->and($stock->fresh()?->on_hand_qty)->toBe(8)
        ->and($lot->fresh()?->qty_remaining)->toBe(8);
});

test('approved discrepancies post once against the counted lot', function () {
    $counter = phaseFourCounter();
    $reviewer = phaseFourCounter('Admin');
    $product = Product::factory()->consumable()->create(['sku' => 'COUNT-POST-01']);
    $stock = ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);
    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-COUNT-01',
        'qty_received' => 10,
        'qty_remaining' => 10,
    ]);

    $this->actingAs($counter);
    $session = phaseFourCountSession($counter, $product);
    $line = $session->lines()->firstOrFail();
    $service = app(PhysicalCountService::class);
    $service->recordLine($counter, $session, $line, 7, 'Three units could not be located.');
    $service->submit($counter, $session);

    $this->actingAs($reviewer);
    $service->reviewLine(
        $reviewer,
        $session,
        $line,
        InventoryCountReviewStatus::Approved,
        'Shelf and issue records checked.',
    );
    $service->approve($reviewer, $session, '127.0.0.1');

    $movementId = $line->fresh()?->adjustment_movement_id;

    expect($stock->fresh()?->on_hand_qty)->toBe(7)
        ->and($lot->fresh()?->qty_remaining)->toBe(7)
        ->and($movementId)->not->toBeNull()
        ->and(StockMovement::query()->findOrFail($movementId)->qty_delta)->toBe(-3);

    expect(fn () => $service->approve($reviewer, $session->fresh(), '127.0.0.1'))
        ->toThrow(RuntimeException::class, 'Only submitted count sessions can be approved.');
});

test('the person who counted a discrepancy cannot review it', function () {
    $adminCounter = phaseFourCounter('Admin');
    $product = Product::factory()->consumable()->create();
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 4]);
    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 4,
        'qty_remaining' => 4,
    ]);

    $this->actingAs($adminCounter);
    $session = phaseFourCountSession($adminCounter, $product);
    $line = $session->lines()->firstOrFail();
    $service = app(PhysicalCountService::class);
    $service->recordLine($adminCounter, $session, $line, 3, null);
    $service->submit($adminCounter, $session);

    expect(fn () => $service->reviewLine(
        $adminCounter,
        $session,
        $line,
        InventoryCountReviewStatus::Approved,
        'Self review attempt.',
    ))->toThrow(RuntimeException::class, 'reviewed by someone other');
});

test('a failed multi-line approval rolls back every count adjustment', function () {
    $counter = phaseFourCounter();
    $reviewer = phaseFourCounter('Admin');
    $product = Product::factory()->consumable()->create();
    $stock = ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);
    $firstLot = StockLot::factory()->create(['product_id' => $product->id, 'qty_received' => 5, 'qty_remaining' => 5]);
    $secondLot = StockLot::factory()->create(['product_id' => $product->id, 'qty_received' => 5, 'qty_remaining' => 5]);

    $this->actingAs($counter);
    $session = phaseFourCountSession($counter, $product);
    $service = app(PhysicalCountService::class);
    foreach ($session->lines as $line) {
        $service->recordLine($counter, $session, $line, 4, null);
    }
    $service->submit($counter, $session);

    $this->actingAs($reviewer);
    foreach ($session->lines as $line) {
        $service->reviewLine($reviewer, $session, $line, InventoryCountReviewStatus::Approved, 'Reviewed.');
    }

    $secondLot->update(['qty_remaining' => 0]);
    $stock->update(['on_hand_qty' => 5]);
    StockMovement::factory()->create([
        'movement_type' => 'issue',
        'product_id' => $product->id,
        'stock_lot_id' => $secondLot->id,
        'qty_delta' => -5,
        'qty_before' => 10,
        'qty_after' => 5,
        'performed_by' => $reviewer->id,
        'performed_at' => now(),
    ]);

    expect(fn () => $service->approve($reviewer, $session, '127.0.0.1'))
        ->toThrow(RuntimeException::class, 'no longer has enough stock');

    expect($firstLot->fresh()?->qty_remaining)->toBe(5)
        ->and($stock->fresh()?->on_hand_qty)->toBe(5)
        ->and($session->fresh()?->status->value)->toBe('submitted')
        ->and($session->lines()->whereNotNull('adjustment_movement_id')->count())->toBe(0);
});

test('only the assigned counter can change saved observations', function () {
    $counter = phaseFourCounter();
    $admin = phaseFourCounter('Admin');
    $product = Product::factory()->consumable()->create();
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 4]);
    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 4,
        'qty_remaining' => 4,
    ]);
    $session = phaseFourCountSession($counter, $product);
    $line = $session->lines()->firstOrFail();

    $this->actingAs($admin)
        ->put(route('inventory.counts.lines.update', [$session, $line], absolute: false), [
            'counted_qty' => 3,
            'count_notes' => 'Administrative overwrite attempt.',
        ])
        ->assertForbidden();

    expect($line->fresh()?->counted_qty)->toBeNull();
});

test('count sessions reject inactive or unauthorized assignees', function () {
    $creator = phaseFourCounter();
    $inactiveAssignee = phaseFourCounter();
    $inactiveAssignee->update(['is_active' => false]);
    $ordinaryUser = User::factory()->create();
    $product = Product::factory()->consumable()->create();
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 2]);
    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 2,
        'qty_remaining' => 2,
    ]);

    foreach ([$inactiveAssignee, $ordinaryUser] as $assignee) {
        expect(fn () => app(PhysicalCountService::class)->createSession($creator, [
            'counting_date' => now()->toDateString(),
            'scope' => 'Restricted assignment test',
            'assigned_to' => $assignee->id,
            'product_ids' => [$product->id],
            'asset_ids' => [],
        ]))->toThrow(RuntimeException::class, 'active inventory staff member');
    }

    expect(InventoryCountSession::query()->count())->toBe(0);
});

test('a location-specific asset count rejects assets without that recorded location', function () {
    $counter = phaseFourCounter('Property Custodian');
    $location = Location::factory()->create();
    $asset = Asset::factory()->create(['current_location_id' => null]);

    expect(fn () => app(PhysicalCountService::class)->createSession($counter, [
        'counting_date' => now()->toDateString(),
        'scope' => 'Room inventory',
        'location_id' => $location->id,
        'assigned_to' => $counter->id,
        'product_ids' => [],
        'asset_ids' => [$asset->id],
    ]))->toThrow(RuntimeException::class, 'different or unrecorded location');
});
