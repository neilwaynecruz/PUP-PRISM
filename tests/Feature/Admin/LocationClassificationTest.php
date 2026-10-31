<?php

use App\Models\Department;
use App\Models\Location;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new RoleSeeder)->run();
});

test('admin can create a classified bookable facility with controlled values', function () {
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');
    $department = Department::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.locations.store', absolute: false), [
            'department_id' => $department->id,
            'code' => 'EVENT-AVR',
            'name' => 'Audio Visual Room',
            'facility_group' => 'events',
            'location_type' => 'audio_visual_room',
            'building' => 'Academic Building',
            'room' => 'AVR',
            'is_bookable' => true,
            'capacity' => 80,
            'is_active' => true,
        ])
        ->assertRedirect();

    $location = Location::query()->where('code', 'EVENT-AVR')->firstOrFail();

    expect($location->facility_group->value)->toBe('events')
        ->and($location->location_type->value)->toBe('audio_visual_room')
        ->and($location->is_bookable)->toBeTrue()
        ->and($location->capacity)->toBe(80);
});

test('location classification rejects invalid values and self parenting', function () {
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');
    $location = Location::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.locations.update', $location, absolute: false), [
            'parent_location_id' => $location->id,
            'code' => $location->code,
            'name' => $location->name,
            'facility_group' => 'unsupported',
            'location_type' => 'unsupported',
            'is_bookable' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['parent_location_id', 'facility_group', 'location_type']);
});

test('location hierarchy rejects descendant cycles', function () {
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');
    $parent = Location::factory()->create(['code' => 'BUILDING-A']);
    $child = Location::factory()->create([
        'code' => 'BUILDING-A-ROOM-1',
        'parent_location_id' => $parent->id,
    ]);

    $this->actingAs($admin)
        ->put(route('admin.locations.update', $parent, absolute: false), [
            'parent_location_id' => $child->id,
            'code' => $parent->code,
            'name' => $parent->name,
            'facility_group' => $parent->facility_group->value,
            'location_type' => $parent->location_type->value,
            'is_bookable' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors('parent_location_id');

    expect($parent->fresh()->parent_location_id)->toBeNull();
});

test('location classification rejects mismatched facility group and location type', function () {
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');
    $location = Location::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.locations.update', $location, absolute: false), [
            'code' => $location->code,
            'name' => $location->name,
            'facility_group' => 'sports',
            'location_type' => 'classroom',
            'is_bookable' => false,
            'is_active' => true,
        ])
        ->assertSessionHasErrors(['location_type']);
});
