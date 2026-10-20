<?php

use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Location;
use App\Models\User;
use App\Services\Inventory\AssetTransferService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['Admin', 'Supply Head', 'Property Custodian'] as $role) {
        Role::findOrCreate($role);
    }
});

test('an approved location transfer updates the asset and preserves transfer history', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $from = Location::factory()->create();
    $to = Location::factory()->create();
    $asset = Asset::factory()->create(['current_location_id' => $from->id]);
    $service = app(AssetTransferService::class);

    $this->actingAs($custodian);
    $transfer = $service->create($custodian, [
        'asset_id' => $asset->id,
        'to_location_id' => $to->id,
        'reason' => 'Move the projector to the new lecture room.',
        'transfer_date' => now()->toDateString(),
    ]);

    expect($asset->fresh()?->current_location_id)->toBe($from->id);

    $this->actingAs($admin);
    $service->approve($admin, $transfer);

    expect($asset->fresh()?->current_location_id)->toBe($to->id)
        ->and($transfer->fresh()?->status->value)->toBe('approved')
        ->and($transfer->fresh()?->from_location_id)->toBe($from->id)
        ->and(AssetTransfer::query()->count())->toBe(1);
});

test('a second pending transfer for the same asset is rejected', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $this->actingAs($custodian);
    $asset = Asset::factory()->create(['current_location_id' => Location::factory()]);
    $firstDestination = Location::factory()->create();
    $secondDestination = Location::factory()->create();
    $service = app(AssetTransferService::class);

    $payload = [
        'asset_id' => $asset->id,
        'to_location_id' => $firstDestination->id,
        'reason' => 'First requested move.',
        'transfer_date' => now()->toDateString(),
    ];
    $service->create($custodian, $payload);

    expect(fn () => $service->create($custodian, [...$payload, 'to_location_id' => $secondDestination->id]))
        ->toThrow(RuntimeException::class, 'pending location transfer');
});

test('the transfer requester cannot approve their own request', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');
    $asset = Asset::factory()->create(['current_location_id' => Location::factory()]);
    $transfer = AssetTransfer::factory()->create([
        'asset_id' => $asset->id,
        'from_location_id' => $asset->current_location_id,
        'to_location_id' => Location::factory(),
        'initiated_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->post(route('inventory.asset-transfers.approve', $transfer, absolute: false))
        ->assertForbidden();
});

test('an asset with open maintenance cannot be transferred', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $asset = Asset::factory()->create(['current_location_id' => Location::factory()]);
    AssetMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Scheduled,
        'scheduled_for' => now()->addDay()->toDateString(),
    ]);

    expect(fn () => app(AssetTransferService::class)->create($custodian, [
        'asset_id' => $asset->id,
        'to_location_id' => Location::factory()->create()->id,
        'reason' => 'Move during scheduled maintenance.',
        'transfer_date' => now()->toDateString(),
    ]))->toThrow(RuntimeException::class, 'open maintenance record');
});

test('the requester can cancel a pending transfer without changing the asset location', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $from = Location::factory()->create();
    $asset = Asset::factory()->create(['current_location_id' => $from->id]);
    $service = app(AssetTransferService::class);
    $transfer = $service->create($custodian, [
        'asset_id' => $asset->id,
        'to_location_id' => Location::factory()->create()->id,
        'reason' => 'Move no longer needed.',
        'transfer_date' => now()->toDateString(),
    ]);

    $service->cancel($custodian, $transfer, 'The receiving room is no longer available.');

    expect($transfer->fresh()?->status->value)->toBe('cancelled')
        ->and($asset->fresh()?->current_location_id)->toBe($from->id);
});
