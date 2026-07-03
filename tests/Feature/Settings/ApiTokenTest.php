<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    (new RoleSeeder)->run();
    $this->withoutVite();
});

test('admin and supply head can view the api token settings page', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('tokens')
            ->has('abilityOptions', 2),
        );
})->with([
    'admin' => 'Admin',
    'supply head' => 'Supply Head',
]);

test('other authenticated users cannot view the api token settings page', function (?string $role) {
    $user = User::factory()->create();

    if ($role !== null) {
        $user->assignRole($role);
    }

    $this->actingAs($user)
        ->get(route('api-tokens.index'))
        ->assertForbidden();
})->with([
    'property custodian' => 'Property Custodian',
    'roleless user' => null,
]);

test('api token creation validates required fields', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user)
        ->from(route('api-tokens.index'))
        ->post(route('api-tokens.store'), [
            'name' => '',
            'abilities' => [],
        ])
        ->assertSessionHasErrors(['name', 'abilities'])
        ->assertRedirect(route('api-tokens.index'));
});

test('api token can be created and shown once', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user);

    $this->followingRedirects()
        ->post(route('api-tokens.store'), [
            'name' => 'Inventory CLI',
            'abilities' => ['read'],
        ])
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('newToken', fn (Assert $newToken) => $newToken
                ->where(
                    'name',
                    fn (mixed $value): bool => $value === 'Inventory CLI',
                )
                ->where(
                    'ability_labels',
                    fn (mixed $value): bool => $value === ['Read'],
                )
                ->where(
                    'plain_text_token',
                    fn (mixed $value): bool => is_string($value) && $value !== '',
                )
                ->etc(),
            ),
        );

    $token = $user->tokens()->latest('id')->first();

    expect($token)->not->toBeNull();
    expect($token?->abilities)->toBe(['read']);

    $this->get(route('api-tokens.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->where('newToken', null),
        );
});

test('api token can be revoked', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $token = $user->createToken('Inventory CLI', ['read'])->accessToken;

    $this->actingAs($user)
        ->delete(route('api-tokens.destroy', $token->id))
        ->assertRedirect(route('api-tokens.index'));

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $token->id,
    ]);
});

test('users cannot revoke another users api token', function () {
    $actingUser = User::factory()->create();
    $actingUser->assignRole('Admin');

    $otherUser = User::factory()->create();
    $otherUser->assignRole('Admin');
    $token = $otherUser->createToken('External integration', ['read'])->accessToken;

    $this->actingAs($actingUser)
        ->delete(route('api-tokens.destroy', $token->id))
        ->assertNotFound();

    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $token->id,
    ]);
});
