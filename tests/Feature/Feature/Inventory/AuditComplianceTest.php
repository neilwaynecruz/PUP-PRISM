<?php

use App\Models\Asset;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('booking requests and approvals retain IP and position audit data', function () {
    $requesterPosition = Position::factory()->create();
    $approverPosition = Position::factory()->create();

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $approver = User::factory()->assignedPosition($approverPosition)->create();
    $requester->assignRole('Property Custodian');
    $approver->assignRole('Property Custodian');

    $asset = Asset::factory()->assignedToPosition($approverPosition)->create([
        'product_id' => Product::factory()->asset()->create()->id,
        'status' => 'Available',
    ]);

    $this->actingAs($requester)
        ->post(route('inventory.bookings.store', absolute: false), [
            'asset_id' => $asset->id,
            'start_at' => CarbonImmutable::now()->addDay()->setTime(9, 0)->toIso8601String(),
            'end_at' => CarbonImmutable::now()->addDay()->setTime(11, 0)->toIso8601String(),
            'purpose' => 'UAT booking audit proof',
        ])
        ->assertRedirect();

    $booking = Booking::query()->latest('id')->firstOrFail();
    expect($booking->requester_position_id)->toBe($requesterPosition->id);
    expect($booking->requested_ip_address)->not->toBeNull();

    $this->actingAs($approver)
        ->put(route('inventory.bookings.update', $booking, absolute: false), [
            'action' => 'approve',
        ])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->status)->toBe('Approved');
    expect($booking->approver_position_id)->toBe($approverPosition->id);
    expect($booking->approved_ip_address)->not->toBeNull();
});

test('handover receipt view states internal accountability wording', function () {
    $fromPosition = Position::factory()->create(['title' => 'Chief Property Custodian', 'code' => 'POS-TEST-FROM']);
    $toPosition = Position::factory()->create(['title' => 'Director of IT', 'code' => 'POS-TEST-TO']);

    $fromUser = User::factory()->assignedPosition($fromPosition)->create(['name' => 'Custodian Sender']);
    $toUser = User::factory()->assignedPosition($toPosition)->create(['name' => 'Recipient User']);
    $verifiedBy = User::factory()->assignedPosition($toPosition)->create(['name' => 'Verifier User']);

    $asset = Asset::factory()->assignedToPosition($toPosition)->create([
        'product_id' => Product::factory()->asset()->create(['name' => 'Accountability Laptop'])->id,
        'tag_code' => 'UAT-REC-0001',
        'status' => 'Checked_Out',
    ]);

    $handover = HandoverLog::query()->create([
        'asset_id' => $asset->id,
        'from_user_id' => $fromUser->id,
        'to_user_id' => $toUser->id,
        'from_position_id' => $fromPosition->id,
        'to_position_id' => $toPosition->id,
        'initiated_by' => $fromUser->id,
        'initiated_at' => CarbonImmutable::now()->subHour(),
        'verified_at' => CarbonImmutable::now(),
        'verified_by' => $verifiedBy->id,
        'verification_token_hash' => null,
        'ip_address' => '10.0.0.1',
        'verified_ip_address' => '10.0.0.2',
        'signature_png' => null,
        'notes' => 'Internal accountability receipt wording test',
    ]);

    $html = view('inventory.handover_receipt', [
        'handover' => $handover->load([
            'asset.product',
            'fromUser',
            'toUser',
            'fromPosition.department',
            'toPosition.department',
            'verifiedBy',
        ]),
    ])->render();

    expect($html)->toContain('For internal accountability only.');
    expect($html)->toContain('does not replace external legal contracts');
});
