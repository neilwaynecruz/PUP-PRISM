<?php

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\User;
use App\Services\Inventory\AssetLifecycleService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    foreach (['Admin', 'Supply Head', 'Property Custodian'] as $role) {
        Role::findOrCreate($role);
    }
});

test('retirement requires cleared accountability and separate approval', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $approver = User::factory()->create();
    $approver->assignRole('Admin');
    $asset = Asset::factory()->create();
    $service = app(AssetLifecycleService::class);

    $this->actingAs($requester);
    $event = $service->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Retirement->value,
        'reason' => 'The asset has reached the end of its useful service life.',
        'proposed_for' => now()->toDateString(),
    ]);

    $this->actingAs($approver);
    $service->approve($approver, $event);

    expect($asset->fresh()?->lifecycle_status)->toBe(AssetLifecycleStatus::Retired)
        ->and($asset->fresh()?->status)->toBe(AssetStatus::Condemned)
        ->and($event->fresh()?->status->value)->toBe('approved')
        ->and($event->fresh()?->occurred_at)->not->toBeNull();
});

test('assigned assets cannot be retired', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $approver = User::factory()->create();
    $approver->assignRole('Admin');
    $asset = Asset::factory()->assignedToPosition()->checkedOut()->create();
    $service = app(AssetLifecycleService::class);

    $this->actingAs($requester);
    $event = $service->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Retirement->value,
        'reason' => 'Retirement requested.',
    ]);

    $this->actingAs($approver);
    expect(fn () => $service->approve($approver, $event))
        ->toThrow(RuntimeException::class, 'handover or return');

    expect($asset->fresh()?->lifecycle_status)->toBe(AssetLifecycleStatus::Active)
        ->and($event->fresh()?->status->value)->toBe('pending');
});

test('condition changes do not change lifecycle state', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $approver = User::factory()->create();
    $approver->assignRole('Admin');
    $asset = Asset::factory()->create();
    $service = app(AssetLifecycleService::class);

    $this->actingAs($requester);
    $event = $service->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::ConditionChange->value,
        'condition_status' => AssetConditionStatus::Damaged->value,
        'reason' => 'The casing and display were damaged during inspection.',
    ]);

    $this->actingAs($approver);
    $service->approve($approver, $event);

    expect($asset->fresh()?->condition_status)->toBe(AssetConditionStatus::Damaged)
        ->and($asset->fresh()?->lifecycle_status)->toBe(AssetLifecycleStatus::Active)
        ->and($asset->fresh()?->status)->toBe(AssetStatus::Unserviceable);
});

test('disposal requires an already retired asset', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $this->actingAs($requester);
    $asset = Asset::factory()->create();

    expect(fn () => app(AssetLifecycleService::class)->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::Disposal->value,
        'reason' => 'Disposal proposed.',
    ]))->toThrow(RuntimeException::class, 'Retire the asset');
});

test('retired assets cannot be returned to active use through a condition change', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $asset = Asset::factory()->create([
        'lifecycle_status' => AssetLifecycleStatus::Retired,
        'condition_status' => AssetConditionStatus::Damaged,
        'status' => AssetStatus::Condemned,
    ]);

    expect(fn () => app(AssetLifecycleService::class)->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::ConditionChange->value,
        'condition_status' => AssetConditionStatus::Serviceable->value,
        'reason' => 'Attempt to return a retired asset to service.',
    ]))->toThrow(RuntimeException::class, 'active assets');

    expect($asset->fresh()?->status)->toBe(AssetStatus::Condemned)
        ->and($asset->fresh()?->lifecycle_status)->toBe(AssetLifecycleStatus::Retired);
});

test('condition changes cannot bypass an open maintenance record', function () {
    $requester = User::factory()->create();
    $requester->assignRole('Property Custodian');
    $approver = User::factory()->create();
    $approver->assignRole('Admin');
    $asset = Asset::factory()->create([
        'condition_status' => AssetConditionStatus::Damaged,
        'status' => AssetStatus::Unserviceable,
    ]);
    AssetMaintenance::factory()->create([
        'asset_id' => $asset->id,
        'status' => MaintenanceStatus::InProgress,
    ]);
    $service = app(AssetLifecycleService::class);
    $event = $service->propose($requester, [
        'asset_id' => $asset->id,
        'event_type' => AssetLifecycleEventType::ConditionChange->value,
        'condition_status' => AssetConditionStatus::Serviceable->value,
        'reason' => 'Mark the asset serviceable before maintenance is complete.',
    ]);

    expect(fn () => $service->approve($approver, $event))
        ->toThrow(RuntimeException::class, 'maintenance record');

    expect($asset->fresh()?->status)->toBe(AssetStatus::Unserviceable)
        ->and($event->fresh()?->status->value)->toBe('pending');
});
