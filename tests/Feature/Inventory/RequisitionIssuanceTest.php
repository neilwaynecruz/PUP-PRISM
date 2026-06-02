<?php

use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('requester can submit a requisition and view it in the index and show pages', function () {
    $position = Position::factory()->create();
    $csrfToken = 'requisition-store-token';

    $requester = User::factory()->assignedPosition($position)->create();
    $requester->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-REQ-0001',
        'name' => 'Printer Paper',
    ]);

    $this->actingAs($requester)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.requisitions.store', absolute: false), [
            '_token' => $csrfToken,
            'notes' => 'Quarterly supply request',
            'lines' => [
                ['sku' => $product->sku, 'qty_requested' => 4],
            ],
        ])
        ->assertRedirect();

    $requisition = Requisition::query()->latest('id')->firstOrFail();

    expect($requisition->status)->toBe('Submitted');
    expect($requisition->lines)->toHaveCount(1);

    $this->actingAs($requester)
        ->get(route('inventory.requisitions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/requisitions/Index')
            ->has('requisitions.data', 1)
            ->where('requisitions.data.0.id', $requisition->id));

    $this->actingAs($requester)
        ->get(route('inventory.requisitions.show', $requisition, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/requisitions/Show')
            ->where('requisition.id', $requisition->id)
            ->where('requisition.lines.0.sku', 'SKU-REQ-0001'));
});

test('requester cannot approve own requisition (separation of duty)', function () {
    $position = Position::factory()->create();
    $csrfToken = 'requisition-approve-token';

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Supply Head');

    $requisition = Requisition::factory()->create([
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => 'Submitted',
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.requisitions.approve', $requisition, absolute: false), ['_token' => $csrfToken])
        ->assertForbidden();
});

test('supply head can reject a submitted requisition with a reason', function () {
    $requesterPosition = Position::factory()->create();
    $reviewerPosition = Position::factory()->create();
    $csrfToken = 'requisition-reject-token';

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $requester->assignRole('Property Custodian');

    $reviewer = User::factory()->assignedPosition($reviewerPosition)->create();
    $reviewer->assignRole('Supply Head');

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Submitted',
        'notes' => 'Original request note',
    ]);

    $this->actingAs($reviewer)
        ->get(route('inventory.requisitions.show', $requisition, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.reject', true));

    $this->actingAs($reviewer)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.requisitions.reject', $requisition, absolute: false), [
            '_token' => $csrfToken,
            'notes' => 'Budget is not available this month.',
        ])
        ->assertRedirect();

    $requisition->refresh();

    expect($requisition->status)->toBe('Rejected');
    expect($requisition->notes)->toContain('Original request note');
    expect($requisition->notes)->toContain('Rejection reason: Budget is not available this month.');
});

test('supply head can issue an approved requisition and records issue movements with ip address', function () {
    $requesterPosition = Position::factory()->create();
    $issuerPosition = Position::factory()->create();
    $approverPosition = Position::factory()->create();
    $csrfToken = 'requisition-issue-token';

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $issuer = User::factory()->assignedPosition($issuerPosition)->create();
    $issuer->assignRole('Supply Head');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-ISSUE-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);

    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Approved',
        'approved_at' => CarbonImmutable::now(),
        'approver_id' => User::factory()->assignedPosition($approverPosition)->create()->id,
        'approver_position_id' => $approverPosition->id,
        'requested_ip_address' => '10.10.10.10',
        'approved_ip_address' => '10.10.10.11',
    ]);

    $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 3,
        'qty_issued' => 0,
    ]);

    $this->actingAs($issuer)
        ->from(route('inventory.requisitions.show', $requisition, absolute: false))
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.requisitions.issue', $requisition, absolute: false), [
            '_token' => $csrfToken,
            'notes' => 'Issued for office use',
        ])
        ->assertRedirect();

    $stock = ProductStock::query()->where('product_id', $product->id)->firstOrFail();
    expect($stock->on_hand_qty)->toBe(7);

    $lot->refresh();
    expect($lot->qty_remaining)->toBe(7);

    $requisition->refresh();
    expect($requisition->status)->toBe('Issued');
    expect($requisition->issued_by)->toBe($issuer->id);
    expect($requisition->issued_position_id)->toBe($issuerPosition->id);
    expect($requisition->issued_ip_address)->not->toBeNull();

    $movement = StockMovement::query()
        ->where('requisition_id', $requisition->id)
        ->where('product_id', $product->id)
        ->where('movement_type', 'issue')
        ->whereNotNull('ip_address')
        ->firstOrFail();

    expect($movement->accountable_position_id)->toBe($requesterPosition->id);
});
