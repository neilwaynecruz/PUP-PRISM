<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
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

function bookingUserWithRole(string $role, Position $position): User
{
    $factory = User::factory()->assignedPosition($position);

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('approved bookings block overlapping requests', function () {
    $position = Position::factory()->create();
    $csrfToken = 'booking-overlap-token';

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
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
        'status' => BookingStatus::Approved,
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
        'status' => AssetStatus::Available,
    ]);

    Asset::factory()->count(30)->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
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
        'status' => BookingStatus::Requested,
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

    expect($booking->status)->toBe(BookingStatus::Rejected);
    expect($booking->approver_id)->toBe($approver->id);
    expect($booking->approver_position_id)->toBe($approverPosition->id);
    expect($booking->approved_ip_address)->not->toBeNull();
});

test('booking show page exposes reject for authorized approver', function (string $role) {
    $approverPosition = Position::factory()->create();
    $requesterPosition = Position::factory()->create();

    $approver = bookingUserWithRole($role, $approverPosition);

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $requester->assignRole('Property Custodian');

    $booking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => BookingStatus::Requested,
    ]);

    $this->actingAs($approver)
        ->get(route('inventory.bookings.show', $booking, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/bookings/Show')
            ->where('can.reject', true)
            ->where('can.approve', true));
})->with([
    'admin' => 'Admin',
    'property custodian' => 'Property Custodian',
]);

test('booking show page hides reject for unauthorized viewer', function () {
    $requesterPosition = Position::factory()->create();

    $requester = bookingUserWithRole('Supply Head', $requesterPosition);

    $booking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => BookingStatus::Requested,
    ]);

    $this->actingAs($requester)
        ->get(route('inventory.bookings.show', $booking, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/bookings/Show')
            ->where('can.reject', false)
            ->where('can.approve', false));
});

test('property custodian can bulk reject pending booking requests', function () {
    $approverPosition = Position::factory()->create();
    $requesterPosition = Position::factory()->create();
    $csrfToken = 'booking-bulk-reject-token';

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

    $firstBooking->refresh();
    $secondBooking->refresh();

    expect($firstBooking->status)->toBe(BookingStatus::Rejected);
    expect($secondBooking->status)->toBe(BookingStatus::Rejected);
    expect($firstBooking->approver_id)->toBe($approver->id);
    expect($secondBooking->approver_id)->toBe($approver->id);
});

test('back-to-back bookings do not conflict when one starts exactly as another ends', function () {
    $position = Position::factory()->create();
    $csrfToken = 'booking-adjacent-token';

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
    ]);

    $startAt = CarbonImmutable::now()->addDays(2)->startOfHour();
    $endAt = $startAt->addHours(2);

    Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'approver_id' => $user->id,
        'approver_position_id' => $position->id,
        'start_at' => $startAt,
        'end_at' => $endAt,
        'status' => BookingStatus::Approved,
    ]);

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.bookings.store', absolute: false), [
            '_token' => $csrfToken,
            'asset_id' => $asset->id,
            'start_at' => $endAt->toIso8601String(),
            'end_at' => $endAt->addHour()->toIso8601String(),
        ])
        ->assertSessionDoesntHaveErrors();

    expect(Booking::query()->where('asset_id', $asset->id)->count())->toBe(2);
});

test('booking approval rechecks overlaps before approving a pending request', function () {
    $position = Position::factory()->create();
    $csrfToken = 'booking-approve-conflict-token';

    $approver = User::factory()->assignedPosition($position)->create();
    $approver->assignRole('Property Custodian');

    $requester = User::factory()->assignedPosition($position)->create();
    $requester->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
    ]);

    $startAt = CarbonImmutable::now()->addDays(3)->startOfHour();
    $endAt = $startAt->addHours(2);

    $firstBooking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $requester->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Requested,
        'start_at' => $startAt,
        'end_at' => $endAt,
    ]);

    $secondBooking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $requester->id,
        'requester_position_id' => $position->id,
        'status' => BookingStatus::Requested,
        'start_at' => $startAt->addMinutes(15),
        'end_at' => $endAt->addMinutes(15),
    ]);

    $this->actingAs($approver)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.bookings.update', $firstBooking, absolute: false), [
            '_token' => $csrfToken,
            'action' => 'approve',
        ])
        ->assertRedirect();

    $this->actingAs($approver)
        ->withSession(['_token' => $csrfToken])
        ->put(route('inventory.bookings.update', $secondBooking, absolute: false), [
            '_token' => $csrfToken,
            'action' => 'approve',
        ])
        ->assertSessionHasErrors(['start_at']);

    expect($firstBooking->refresh()->status)->toBe(BookingStatus::Approved);
    expect($secondBooking->refresh()->status)->toBe(BookingStatus::Requested);
});
