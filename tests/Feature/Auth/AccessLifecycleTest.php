<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    (new RoleSeeder)->run();
});

function createRoleUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->assignRole($role);

    return $user;
}

test('inactive authenticated users are logged out on their next web request', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk();

    $user->forceFill(['is_active' => false])->save();

    $this->get(route('dashboard', absolute: false))
        ->assertRedirect(route('login', absolute: false));

    $this->assertGuest();
});

test('inactive api token users are rejected and their current token is revoked', function () {
    $user = createRoleUser('Property Custodian');
    $token = $user->createToken('Mobile app', ['read']);

    $user->forceFill(['is_active' => false])->save();

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson(route('api.products.index', absolute: false))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Your account has been deactivated. Please contact an administrator.',
        ]);

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->accessToken->id,
    ]);
});

test('admin users without confirmed two factor are redirected to security settings', function () {
    $admin = createRoleUser('Admin');

    $this->actingAs($admin)
        ->get(route('dashboard', absolute: false))
        ->assertRedirect(route('security.edit', absolute: false));
});

test('supply head api requests require confirmed two factor authentication', function () {
    $user = createRoleUser('Supply Head');
    $token = $user->createToken('ERP sync', ['read']);

    $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
        ->getJson(route('api.products.index', absolute: false))
        ->assertForbidden()
        ->assertJson([
            'message' => 'Two-factor authentication is required for your role before accessing this resource.',
        ]);
});

test('property custodians are not forced into two factor authentication', function () {
    $user = createRoleUser('Property Custodian');

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk();
});

test('privileged users with confirmed two factor can access protected areas', function () {
    $admin = createRoleUser('Admin');
    $admin->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code-1'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $this->actingAs($admin)
        ->get(route('dashboard', absolute: false))
        ->assertOk();
});
