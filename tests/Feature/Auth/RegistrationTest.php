<?php

use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;

beforeEach(function () {
    foreach (['REGISTRATION_ENABLED', 'APP_ROUTES_CACHE'] as $key) {
        $processValue = getenv($key);
        $environmentValue = $_ENV[$key] ?? null;
        $serverValue = $_SERVER[$key] ?? null;

        $this->beforeApplicationDestroyed(function () use ($key, $processValue, $environmentValue, $serverValue): void {
            putenv($processValue === false ? $key : "{$key}={$processValue}");

            if ($environmentValue === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $environmentValue;
            }

            if ($serverValue === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $serverValue;
            }
        });
    }

    // Feature flags must rebuild routes instead of using the development route cache.
    $routesCache = storage_path('framework/testing/registration-routes-'.bin2hex(random_bytes(8)).'.php');
    putenv("APP_ROUTES_CACHE={$routesCache}");
    $_ENV['APP_ROUTES_CACHE'] = $routesCache;
    $_SERVER['APP_ROUTES_CACHE'] = $routesCache;

    putenv('REGISTRATION_ENABLED=false');
    $_ENV['REGISTRATION_ENABLED'] = 'false';
    $_SERVER['REGISTRATION_ENABLED'] = 'false';

    $this->refreshApplication();
    $this->withoutVite();
});

test('registration routes are unavailable when registration is disabled', function () {
    expect(Features::enabled(Features::registration()))->toBeFalse();

    $this->get('/register')->assertNotFound();

    $this->post('/register', [
        'name' => 'Disabled Registration User',
        'email' => 'disabled@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

test('registration screen can be rendered when registration is enabled', function () {
    putenv('REGISTRATION_ENABLED=true');
    $_ENV['REGISTRATION_ENABLED'] = 'true';
    $_SERVER['REGISTRATION_ENABLED'] = 'true';

    $this->refreshApplication();
    $this->withoutVite();

    expect(Features::enabled(Features::registration()))->toBeTrue();

    $this->get('/register')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('auth/Register'));
});
