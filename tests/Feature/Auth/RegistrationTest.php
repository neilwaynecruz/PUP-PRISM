<?php

beforeEach(function () {
    putenv('REGISTRATION_ENABLED=false');
    $_ENV['REGISTRATION_ENABLED'] = 'false';
    $_SERVER['REGISTRATION_ENABLED'] = 'false';

    $this->refreshApplication();
    $this->withoutVite();
});

test('registration routes are unavailable when registration is disabled', function () {
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

    $this->get('/register')->assertOk();
});
