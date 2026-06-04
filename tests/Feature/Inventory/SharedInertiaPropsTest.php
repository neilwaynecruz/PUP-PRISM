<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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
