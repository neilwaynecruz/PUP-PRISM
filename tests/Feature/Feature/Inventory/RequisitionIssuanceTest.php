<?php

use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('requester cannot approve own requisition (separation of duty)', function () {
    $position = Position::factory()->create();

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Supply Head');

    $req = Requisition::create([
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'status' => 'Submitted',
    ]);

    $this->actingAs($user)
        ->put(route('inventory.requisitions.approve', $req, absolute: false), [])
        ->assertForbidden();
});

test('supply head can issue an approved requisition and records issue movements with ip_address', function () {
    $requesterPosition = Position::factory()->create();
    $issuerPosition = Position::factory()->create();
    $approverPosition = Position::factory()->create();

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $issuer = User::factory()->assignedPosition($issuerPosition)->create();
    $issuer->assignRole('Supply Head');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-ISSUE-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10, 'reserved_qty' => 0]);

    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $req = Requisition::create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Approved',
        'approved_at' => CarbonImmutable::now(),
        'approver_id' => User::factory()->assignedPosition($approverPosition)->create()->id,
        'approver_position_id' => $approverPosition->id,
        'requested_ip_address' => '10.10.10.10',
        'approved_ip_address' => '10.10.10.11',
    ]);

    RequisitionLine::create([
        'requisition_id' => $req->id,
        'product_id' => $product->id,
        'qty_requested' => 3,
        'qty_issued' => 0,
    ]);

    $this->actingAs($issuer)
        ->from(route('inventory.requisitions.show', $req, absolute: false))
        ->put(route('inventory.requisitions.issue', $req, absolute: false), ['notes' => 'Issued for office use'])
        ->assertRedirect();

    $stock = ProductStock::query()->where('product_id', $product->id)->firstOrFail();
    expect($stock->on_hand_qty)->toBe(7);

    $lot->refresh();
    expect($lot->qty_remaining)->toBe(7);

    $req->refresh();
    expect($req->status)->toBe('Issued');
    expect($req->issued_by)->toBe($issuer->id);
    expect($req->issued_position_id)->toBe($issuerPosition->id);
    expect($req->issued_ip_address)->not->toBeNull();

    $movement = StockMovement::query()
        ->where('requisition_id', $req->id)
        ->where('product_id', $product->id)
        ->where('movement_type', 'issue')
        ->whereNotNull('ip_address')
        ->firstOrFail();

    expect($movement->accountable_position_id)->toBe($requesterPosition->id);
});
