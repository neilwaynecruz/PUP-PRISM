<?php

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
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

test('approved bookings block overlapping requests', function () {
    $position = Position::factory()->create();
    $csrfToken = 'booking-overlap-token';

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => 'Available',
    ]);

    $startAt = CarbonImmutable::now()->addDay()->startOfHour();
    $endAt = $startAt->addHours(2);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'approver_id' => $user->id,
        'approver_position_id' => $position->id,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'status' => 'Approved',
        'purpose' => null,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.bookings.store', absolute: false), [
            '_token' => $csrfToken,
            'asset_id' => $asset->id,
            'start_at' => $startAt->addMinutes(30)->toIso8601String(),
            'end_at' => $endAt->addMinutes(30)->toIso8601String(),
        ])
        ->assertSessionHasErrors(['start_at']);
});

test('booking index paginates records and bounds the asset selector payload', function () {
    $position = Position::factory()->create();
    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => 'Available',
    ]);

    Asset::factory()->count(30)->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => 'Available',
    ]);

    Booking::factory()->count(18)->create([
        'asset_id' => $asset->id,
    ]);

    $this->actingAs($user)
        ->get(route('inventory.bookings.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/bookings/Index')
            ->has('bookings.data', 15)
            ->has('assets', 25)
            ->has('calendar_events')
            ->has('approval_queue'));
});

test('property custodian can reject a pending booking request', function () {
    $approverPosition = Position::factory()->create();
    $requesterPosition = Position::factory()->create();
    $csrfToken = 'booking-reject-token';

    $approver = User::factory()->assignedPosition($approverPosition)->create();
    $approver->assignRole('Property Custodian');

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $requester->assignRole('Property Custodian');

    $booking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Requested',
        'approver_id' => null,
        'approver_position_id' => null,
        'approved_ip_address' => null,
    ]);

    $this->actingAs($approver)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.bookings.update', $booking, absolute: false), [
            '_token' => $csrfToken,
            'action' => 'reject',
        ])
        ->assertRedirect();

    $booking->refresh();

    expect($booking->status)->toBe('Rejected');
    expect($booking->approver_id)->toBe($approver->id);
    expect($booking->approver_position_id)->toBe($approverPosition->id);
    expect($booking->approved_ip_address)->not->toBeNull();
});
