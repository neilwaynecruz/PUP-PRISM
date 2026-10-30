<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    (new RoleSeeder)->run();
});

function safeguardAdmin(): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Admin');

    return $user;
}

test('admins can reactivate a deactivated account and the change is audited', function () {
    $admin = safeguardAdmin();
    $managedUser = User::factory()->create(['is_active' => false]);
    $managedUser->assignRole('Property Custodian');

    $this->actingAs($admin)
        ->patch(route('admin.users.reactivate', ['managedUser' => $managedUser], absolute: false))
        ->assertRedirect(route('admin.users.edit', ['managedUser' => $managedUser], absolute: false));

    expect($managedUser->fresh()->is_active)->toBeTrue();

    $log = AuditLog::query()->where('action', 'reactivate')->where('model_id', $managedUser->id)->sole();

    expect($log->old_values['is_active'])->toBeFalse()
        ->and($log->new_values['is_active'])->toBeTrue();
});

test('non-admins cannot reactivate accounts', function () {
    $supplyHead = User::factory()->withTwoFactor()->create();
    $supplyHead->assignRole('Supply Head');
    $managedUser = User::factory()->create(['is_active' => false]);

    $this->actingAs($supplyHead)
        ->patch(route('admin.users.reactivate', ['managedUser' => $managedUser], absolute: false))
        ->assertForbidden();

    expect($managedUser->fresh()->is_active)->toBeFalse();
});

test('admins cannot change their own role even when other admins exist', function () {
    $admin = safeguardAdmin();
    safeguardAdmin();

    $this->actingAs($admin)
        ->put(route('admin.users.update', ['managedUser' => $admin], absolute: false), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Property Custodian',
            'position_id' => null,
        ])
        ->assertRedirect();

    expect($admin->fresh()->hasRole('Admin'))->toBeTrue()
        ->and($admin->fresh()->hasRole('Property Custodian'))->toBeFalse();
});

test('new users cannot be assigned to an inactive position', function () {
    $admin = safeguardAdmin();
    $inactivePosition = Position::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->post(route('admin.users.store', absolute: false), [
            'name' => 'New Person',
            'email' => 'new.person@example.test',
            'password' => 'Str0ng!Passw0rd#2026',
            'password_confirmation' => 'Str0ng!Passw0rd#2026',
            'role' => 'Property Custodian',
            'position_id' => $inactivePosition->id,
        ])
        ->assertSessionHasErrors('position_id');
});

test('users keep their current position on update even if it was later deactivated', function () {
    $admin = safeguardAdmin();
    $position = Position::factory()->create();
    $managedUser = User::factory()->assignedPosition($position)->create();
    $managedUser->assignRole('Property Custodian');
    $position->update(['is_active' => false]);
    $otherInactive = Position::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->put(route('admin.users.update', ['managedUser' => $managedUser], absolute: false), [
            'name' => 'Renamed Person',
            'email' => $managedUser->email,
            'role' => 'Property Custodian',
            'position_id' => $position->id,
        ])
        ->assertSessionHasNoErrors();

    expect($managedUser->fresh()->name)->toBe('Renamed Person');

    $this->actingAs($admin)
        ->put(route('admin.users.update', ['managedUser' => $managedUser], absolute: false), [
            'name' => 'Renamed Person',
            'email' => $managedUser->email,
            'role' => 'Property Custodian',
            'position_id' => $otherInactive->id,
        ])
        ->assertSessionHasErrors('position_id');
});

test('the user list can be filtered by department and explains each role', function () {
    $admin = safeguardAdmin();
    $engineering = Department::factory()->create(['name' => 'Engineering']);
    $inEngineering = User::factory()->assignedPosition(Position::factory()->create(['department_id' => $engineering->id]))->create();
    $inEngineering->assignRole('Property Custodian');
    $elsewhere = User::factory()->assignedPosition(Position::factory()->create())->create();
    $elsewhere->assignRole('Property Custodian');

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['department_id' => $engineering->id], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $inEngineering->id)
            ->has('roleDescriptions.Admin.abilities')
            ->has('roleDescriptions.Supply Head')
            ->has('roleDescriptions.Property Custodian'));
});

test('the edit page hides inactive positions except the one already assigned', function () {
    $admin = safeguardAdmin();
    $current = Position::factory()->create();
    $managedUser = User::factory()->assignedPosition($current)->create();
    $managedUser->assignRole('Property Custodian');
    $current->update(['is_active' => false]);
    $unrelatedInactive = Position::factory()->create(['is_active' => false]);

    $this->actingAs($admin)
        ->get(route('admin.users.edit', ['managedUser' => $managedUser], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->where('positions', fn ($positions) => collect($positions)->pluck('id')->contains($current->id)
                && ! collect($positions)->pluck('id')->contains($unrelatedInactive->id))
            ->where('can.changeRole', true));
});
