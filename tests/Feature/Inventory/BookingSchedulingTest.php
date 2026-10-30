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
    Role::findOrCreate('Property Custodian');
});

function schedulingCustodian(): User
{
    $user = User::factory()->assignedPosition(Position::factory()->create())->create();
    $user->assignRole('Property Custodian');

    return $user;
}

function schedulingAsset(string $name = 'Laptop Dell'): Asset
{
    return Asset::factory()->assignedToPosition(Position::factory()->create())->create([
        'product_id' => Product::factory()->asset()->create(['name' => $name])->id,
        'status' => AssetStatus::Available,
    ]);
}

test('booking times sent with a different UTC offset are stored in the application timezone', function () {
    config(['app.timezone' => 'Asia/Manila']);
    date_default_timezone_set('Asia/Manila');

    $user = schedulingCustodian();
    $asset = schedulingAsset();
    $startUtc = CarbonImmutable::now('UTC')->addDays(2)->setTime(1, 0);

    $this->actingAs($user)
        ->post(route('inventory.bookings.store', absolute: false), [
            'asset_id' => $asset->id,
            'start_at' => $startUtc->toIso8601String(),
            'end_at' => $startUtc->addHours(2)->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->sole();

    // 01:00 UTC is 09:00 in Manila; it must not be stored as 01:00 local time.
    expect($booking->start_at->format('H:i'))->toBe('09:00')
        ->and($booking->start_at->equalTo($startUtc))->toBeTrue();
});

test('datetime-local values without an offset are read in the application timezone', function () {
    $user = schedulingCustodian();
    $asset = schedulingAsset();
    $day = CarbonImmutable::now()->addDays(3)->format('Y-m-d');

    $this->actingAs($user)
        ->post(route('inventory.bookings.store', absolute: false), [
            'asset_id' => $asset->id,
            'start_at' => "{$day}T08:30",
            'end_at' => "{$day}T10:00",
        ])
        ->assertSessionHasNoErrors();

    expect(Booking::query()->sole()->start_at->format('Y-m-d H:i'))->toBe("{$day} 08:30");
});

test('bookings cannot start in the past', function () {
    $user = schedulingCustodian();
    $asset = schedulingAsset();

    $this->actingAs($user)
        ->post(route('inventory.bookings.store', absolute: false), [
            'asset_id' => $asset->id,
            'start_at' => CarbonImmutable::now()->subDay()->toIso8601String(),
            'end_at' => CarbonImmutable::now()->addHour()->toIso8601String(),
        ])
        ->assertSessionHasErrors('start_at');

    expect(Booking::query()->count())->toBe(0);
});

test('a booking request whose schedule has ended cannot be approved', function () {
    $requester = schedulingCustodian();
    $approver = schedulingCustodian();
    $asset = schedulingAsset();

    $booking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'start_at' => CarbonImmutable::now()->subDays(2),
        'end_at' => CarbonImmutable::now()->subDay(),
        'status' => BookingStatus::Requested,
    ]);

    $this->actingAs($approver)
        ->put(route('inventory.bookings.update', $booking, absolute: false), ['action' => 'approve'])
        ->assertSessionHasErrors('start_at');

    expect($booking->fresh()->status)->toBe(BookingStatus::Requested);
});

test('bookings can be searched by asset name and the page exposes the app timezone', function () {
    $user = schedulingCustodian();
    $projector = schedulingAsset('Projector Epson');
    $laptop = schedulingAsset('Laptop Dell');

    foreach ([$projector, $laptop] as $asset) {
        Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $user->id,
            'requester_position_id' => $user->position_id,
            'start_at' => CarbonImmutable::now()->addDay(),
            'end_at' => CarbonImmutable::now()->addDay()->addHour(),
            'status' => BookingStatus::Requested,
        ]);
    }

    $this->actingAs($user)
        ->get(route('inventory.bookings.index', ['search' => 'Projector'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->has('bookings.data', 1)
            ->where('bookings.data.0.asset_id', $projector->id)
            ->where('timezone', config('app.timezone')));
});
