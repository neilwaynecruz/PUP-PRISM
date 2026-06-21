<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
});

test('authenticated inertia pages share session timeout metadata', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('session.lifetimeMinutes', (int) config('session.lifetime'))
            ->where('session.warningMinutes', max(1, min(5, ((int) config('session.lifetime')) - 1)))
            ->where('session.keepAliveUrl', route('session.keep-alive', absolute: false))
            ->where('session.loginUrl', route('login', absolute: false)));
});

test('authenticated inertia pages share role-aware inventory permissions', function () {
    Role::findOrCreate('Supply Head');

    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.permissions.viewProducts', true)
            ->where('auth.permissions.createProducts', true)
            ->where('auth.permissions.viewHandover', false)
            ->where('auth.permissions.viewBookings', true)
            ->where('auth.permissions.viewRequisitions', true)
            ->where('auth.permissions.viewReceiving', true)
            ->where('auth.permissions.viewMovements', false)
            ->where('auth.permissions.viewAuditLogs', false));
});
