<?php

use App\Models\AuditLog;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();

    (new RoleSeeder)->run();
});

function createAdminUser(): User
{
    $user = User::factory()->withTwoFactor()->create(['name' => 'Admin User']);
    $user->assignRole('Admin');

    return $user;
}

test('non-admin users cannot access user management', function () {
    $user = User::factory()->create();
    $user->assignRole('Property Custodian');

    $this->actingAs($user)
        ->get(route('admin.users.index', absolute: false))
        ->assertForbidden();
});

test('admin can open the user management index', function () {
    $admin = createAdminUser();
    $managedUser = User::factory()->assignedPosition(Position::factory()->create())->create([
        'name' => 'Zed User',
        'invited_at' => now(),
    ]);
    $managedUser->assignRole('Property Custodian');

    $this->actingAs($admin)
        ->get(route('admin.users.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/Index')
            ->has('users.data', 2));
});

test('admin can create a user with a role', function () {
    $admin = createAdminUser();
    $position = Position::factory()->create();

    $response = $this->actingAs($admin)->post(route('admin.users.store', absolute: false), [
        'name' => 'Provisioned User',
        'email' => 'provisioned@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'Property Custodian',
        'position_id' => $position->id,
    ]);

    $createdUser = User::query()->where('email', 'provisioned@example.test')->firstOrFail();

    $response->assertRedirect(route('admin.users.edit', ['managedUser' => $createdUser], absolute: false));

    expect($createdUser->hasRole('Property Custodian'))->toBeTrue();
    expect($createdUser->position_id)->toBe($position->id);
    expect($createdUser->is_active)->toBeTrue();
    expect($createdUser->invited_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'create',
        'model_type' => 'User',
        'model_id' => $createdUser->id,
    ]);
});

test('admin can update a user and sync the assigned role', function () {
    $admin = createAdminUser();
    $managedUser = User::factory()->assignedPosition(Position::factory()->create())->create([
        'invited_at' => now(),
    ]);
    $managedUser->assignRole('Property Custodian');

    $newPosition = Position::factory()->create();

    $response = $this->actingAs($admin)->put(route('admin.users.update', ['managedUser' => $managedUser], absolute: false), [
        'name' => 'Updated User',
        'email' => 'updated@example.test',
        'role' => 'Supply Head',
        'position_id' => $newPosition->id,
    ]);

    $managedUser->refresh();

    $response->assertRedirect(route('admin.users.edit', ['managedUser' => $managedUser], absolute: false));

    expect($managedUser->name)->toBe('Updated User');
    expect($managedUser->email)->toBe('updated@example.test');
    expect($managedUser->position_id)->toBe($newPosition->id);
    expect($managedUser->hasRole('Supply Head'))->toBeTrue();
    expect($managedUser->hasRole('Property Custodian'))->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'update',
        'model_type' => 'User',
        'model_id' => $managedUser->id,
    ]);
});

test('admin can deactivate another user', function () {
    $admin = createAdminUser();
    $managedUser = User::factory()->create([
        'invited_at' => now(),
    ]);
    $managedUser->assignRole('Property Custodian');

    $response = $this->actingAs($admin)->patch(route('admin.users.deactivate', ['managedUser' => $managedUser], absolute: false));

    $response->assertRedirect(route('admin.users.index', absolute: false));

    expect($managedUser->fresh()->is_active)->toBeFalse();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'deactivate',
        'model_type' => 'User',
        'model_id' => $managedUser->id,
    ]);
});

test('deactivating a user revokes their tokens and active sessions', function () {
    $admin = createAdminUser();
    $managedUser = User::factory()->create([
        'invited_at' => now(),
    ]);
    $managedUser->assignRole('Supply Head');

    $token = $managedUser->createToken('Inventory sync', ['read'])->accessToken;

    DB::table((string) config('session.table', 'sessions'))->insert([
        [
            'id' => 'managed-user-session-1',
            'user_id' => $managedUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Pest',
            'payload' => 'session-one',
            'last_activity' => now()->timestamp,
        ],
        [
            'id' => 'managed-user-session-2',
            'user_id' => $managedUser->id,
            'ip_address' => '127.0.0.2',
            'user_agent' => 'Pest',
            'payload' => 'session-two',
            'last_activity' => now()->timestamp,
        ],
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', ['managedUser' => $managedUser], absolute: false))
        ->assertRedirect(route('admin.users.index', absolute: false));

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->id,
    ]);

    expect(
        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $managedUser->id)
            ->count()
    )->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'revoke_tokens',
        'model_type' => 'User',
        'model_id' => $managedUser->id,
    ]);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'revoke_sessions',
        'model_type' => 'User',
        'model_id' => $managedUser->id,
    ]);
});

test('admin cannot deactivate their own account', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->patch(route('admin.users.deactivate', ['managedUser' => $admin], absolute: false))
        ->assertRedirect();

    expect($admin->fresh()->is_active)->toBeTrue();

    expect(
        AuditLog::query()
            ->where('action', 'deactivate')
            ->where('model_id', $admin->id)
            ->exists()
    )->toBeFalse();
});

test('last active admin cannot remove their admin role', function () {
    $admin = createAdminUser();

    $this->actingAs($admin)
        ->put(route('admin.users.update', ['managedUser' => $admin], absolute: false), [
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => 'Supply Head',
            'position_id' => null,
        ])
        ->assertRedirect();

    expect($admin->fresh()->hasRole('Admin'))->toBeTrue();
});
