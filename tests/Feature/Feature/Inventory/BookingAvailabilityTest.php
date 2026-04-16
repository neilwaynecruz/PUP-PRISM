<?php

use App\Models\Asset;
use App\Models\Booking;
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

test('approved bookings block overlapping requests', function () {
    $position = Position::factory()->create();

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'status' => 'Available',
    ]);

    $startAt = CarbonImmutable::now()->addDay()->startOfHour();
    $endAt = $startAt->addHours(2);

    Booking::create([
        'asset_id' => $asset->id,
        'requester_id' => $user->id,
        'requester_position_id' => $position->id,
        'approver_id' => $user->id,
        'approver_position_id' => $position->id,
        'requested_ip_address' => '127.0.0.1',
        'approved_ip_address' => '127.0.0.1',
        'start_at' => $startAt,
        'end_at' => $endAt,
        'status' => 'Approved',
        'purpose' => null,
    ]);

    $this->actingAs($user)
        ->post(route('inventory.bookings.store', absolute: false), [
            'asset_id' => $asset->id,
            'start_at' => $startAt->addMinutes(30)->toIso8601String(),
            'end_at' => $endAt->addMinutes(30)->toIso8601String(),
        ])
        ->assertSessionHasErrors(['start_at']);
});
