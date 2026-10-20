<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\Booking;
use App\Models\User;
use App\Services\Inventory\AssetIntegrityService;
use App\Services\Inventory\AssetMaintenanceService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['Admin', 'Supply Head', 'Property Custodian'] as $role) {
        Role::findOrCreate($role);
    }
});

test('maintenance follows guarded transitions and restores asset availability on completion', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $this->actingAs($custodian);
    $asset = Asset::factory()->create();
    $service = app(AssetMaintenanceService::class);

    $maintenance = $service->create($custodian, [
        'asset_id' => $asset->id,
        'maintenance_type' => MaintenanceType::Repair->value,
        'reported_issue' => 'The power switch is not working.',
        'reported_date' => now()->toDateString(),
        'scheduled_for' => now()->addDay()->toDateString(),
    ]);

    $service->transition($maintenance, MaintenanceStatus::Scheduled, []);
    $service->transition($maintenance, MaintenanceStatus::InProgress, []);

    expect($asset->fresh()?->status)->toBe(AssetStatus::Unserviceable);

    $service->transition($maintenance, MaintenanceStatus::Completed, [
        'findings' => 'The switch was replaced and tested.',
        'repair_cost' => 450,
    ]);

    expect($maintenance->fresh()?->status)->toBe(MaintenanceStatus::Completed)
        ->and($maintenance->fresh()?->completed_at)->not->toBeNull()
        ->and($asset->fresh()?->status)->toBe(AssetStatus::Available)
        ->and($asset->fresh()?->condition_status->value)->toBe('serviceable');
});

test('approved bookings are not silently cancelled when maintenance is started', function () {
    $custodian = User::factory()->create();
    $custodian->assignRole('Property Custodian');
    $this->actingAs($custodian);
    $asset = Asset::factory()->create();
    $booking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'status' => BookingStatus::Approved,
        'start_at' => now()->addDay(),
        'end_at' => now()->addDays(2),
    ]);
    $service = app(AssetMaintenanceService::class);
    $maintenance = $service->create($custodian, [
        'asset_id' => $asset->id,
        'maintenance_type' => MaintenanceType::Corrective->value,
        'reported_issue' => 'Intermittent display failure.',
        'reported_date' => now()->toDateString(),
    ]);

    expect(fn () => $service->transition($maintenance, MaintenanceStatus::InProgress, []))
        ->toThrow(RuntimeException::class, 'approved booking');

    expect($asset->fresh()?->status)->toBe(AssetStatus::Available)
        ->and($booking->fresh()?->status)->toBe(BookingStatus::Approved)
        ->and($maintenance->fresh()?->status)->toBe(MaintenanceStatus::Reported);
});

test('supply personnel cannot change maintenance status', function () {
    $supply = User::factory()->withTwoFactor()->create();
    $supply->assignRole('Supply Head');
    $maintenance = AssetMaintenance::factory()->create();

    $this->actingAs($supply)
        ->put(route('inventory.maintenance.transition', $maintenance, absolute: false), ['status' => 'cancelled'])
        ->assertForbidden();
});

test('an open maintenance report blocks incompatible new bookings', function () {
    $asset = Asset::factory()->create();
    AssetMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::Reported,
    ]);

    expect(fn () => app(AssetIntegrityService::class)->ensureBookable(
        $asset,
        now()->addDay(),
        now()->addDays(2),
    ))->toThrow(ValidationException::class, 'unavailable while it has open maintenance');
});
