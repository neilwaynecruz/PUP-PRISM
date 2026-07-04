<?php

use App\Models\User;

test('session status endpoint returns unauthorized for guests', function () {
    $response = $this->get(route('session.status'));

    $response->assertUnauthorized();
    expectResponsePreventsClientCaching($response);
});

test('session status endpoint returns no content for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('session.status'));

    $response->assertNoContent();
    expectResponsePreventsClientCaching($response);
});

test('authenticated pages include no-store cache headers', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    expectResponsePreventsClientCaching($response);
    $response->assertHeader('Pragma', 'no-cache');
});

test('login page includes no-store cache headers', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
    expectResponsePreventsClientCaching($response);
});

test('guests requesting protected pages are redirected to login', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});
