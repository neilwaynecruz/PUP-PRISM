<?php

use App\Enums\BookingStatus;
use App\Enums\RequisitionStatus;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function bulkWorkflowUser(string $role, Position $position): User
{
    $factory = User::factory()->assignedPosition($position);

    if ($role === 'Supply Head') {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('supply head can bulk approve submitted requisitions', function () {
    $requesterPosition = Position::factory()->create();
    $reviewerPosition = Position::factory()->create();
    $csrfToken = 'requisition-bulk-approve-token';

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $requester->assignRole('Property Custodian');

    $reviewer = bulkWorkflowUser('Supply Head', $reviewerPosition);

    $firstRequisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    $secondRequisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    $this->actingAs($reviewer)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.requisitions.bulk-approve', absolute: false), [
            '_token' => $csrfToken,
            'ids' => [$firstRequisition->id, $secondRequisition->id],
        ])
        ->assertRedirect();

    $firstRequisition->refresh();
    $secondRequisition->refresh();

    expect($firstRequisition->status)->toBe(RequisitionStatus::Approved)
        ->and($secondRequisition->status)->toBe(RequisitionStatus::Approved)
        ->and($firstRequisition->approver_id)->toBe($reviewer->id)
        ->and($secondRequisition->approver_id)->toBe($reviewer->id);
});

test('supply head can bulk issue approved requisitions', function () {
    $requesterPosition = Position::factory()->create();
    $issuerPosition = Position::factory()->create();
    $approverPosition = Position::factory()->create();
    $csrfToken = 'requisition-bulk-issue-token';

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $issuer = bulkWorkflowUser('Supply Head', $issuerPosition);

    $approver = User::factory()->assignedPosition($approverPosition)->create();

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-BULK-ISSUE']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 20]);

    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 20,
        'qty_remaining' => 20,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $firstRequisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => RequisitionStatus::Approved,
        'approved_at' => CarbonImmutable::now(),
        'approver_id' => $approver->id,
        'approver_position_id' => $approverPosition->id,
    ]);

    $firstRequisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 2,
        'qty_issued' => 0,
    ]);

    $secondRequisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => RequisitionStatus::Approved,
        'approved_at' => CarbonImmutable::now(),
        'approver_id' => $approver->id,
        'approver_position_id' => $approverPosition->id,
    ]);

    $secondRequisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 3,
        'qty_issued' => 0,
    ]);

    $this->actingAs($issuer)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.requisitions.bulk-issue', absolute: false), [
            '_token' => $csrfToken,
            'ids' => [$firstRequisition->id, $secondRequisition->id],
        ])
        ->assertRedirect();

    $firstRequisition->refresh();
    $secondRequisition->refresh();

    expect($firstRequisition->status)->toBe(RequisitionStatus::Issued)
        ->and($secondRequisition->status)->toBe(RequisitionStatus::Issued);

    expect(ProductStock::query()->where('product_id', $product->id)->value('on_hand_qty'))->toBe(15);
});

test('property custodian can bulk reject pending booking requests', function () {
    $approverPosition = Position::factory()->create();
    $requesterPosition = Position::factory()->create();
    $csrfToken = 'booking-bulk-reject-workflow-token';

    $approver = User::factory()->assignedPosition($approverPosition)->create();
    $approver->assignRole('Property Custodian');

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $requester->assignRole('Property Custodian');

    $firstBooking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => BookingStatus::Requested,
    ]);

    $secondBooking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => BookingStatus::Requested,
    ]);

    $this->actingAs($approver)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.bookings.bulk-reject', absolute: false), [
            '_token' => $csrfToken,
            'ids' => [$firstBooking->id, $secondBooking->id],
        ])
        ->assertRedirect();

    expect($firstBooking->refresh()->status)->toBe(BookingStatus::Rejected);
    expect($secondBooking->refresh()->status)->toBe(BookingStatus::Rejected);
});

test('scheduled commands record heartbeat timestamps on success', function () {
    Cache::flush();

    $this->artisan('app:inventory-generate-alerts')
        ->assertExitCode(0);

    expect(Cache::get(SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_INVENTORY_GENERATE_ALERTS)))
        ->not->toBeNull();
});
