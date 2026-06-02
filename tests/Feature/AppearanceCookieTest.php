<?php

beforeEach(function () {
    $this->withoutVite();
});

test('valid appearance cookie is rendered safely and applied', function () {
    $this->withUnencryptedCookie('appearance', 'dark')
        ->get('/')
        ->assertOk()
        ->assertSee("const appearance = 'dark';", false)
        ->assertSee('class="dark"', false);
});

test('malicious appearance cookie falls back to system and is not reflected', function () {
    $payload = "';alert(1);//";

    $response = $this->withUnencryptedCookie('appearance', $payload)
        ->get('/')
        ->assertOk();

    $response->assertSee("const appearance = 'system';", false);
    $response->assertDontSee('alert(1)', false);
});

test('unknown appearance cookie value falls back to system', function () {
    $this->withUnencryptedCookie('appearance', 'neon')
        ->get('/')
        ->assertOk()
        ->assertSee("const appearance = 'system';", false);
});
