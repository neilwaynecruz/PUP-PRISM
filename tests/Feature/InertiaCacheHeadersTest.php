<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('products index uses short lived private cache headers', function () {
    Role::findOrCreate('Admin');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Admin');

    $response = $this->actingAs($user)->get(route('inventory.products.index', absolute: false));

    $response->assertOk();

    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)
        ->toContain('private')
        ->toContain('max-age=30');
});

test('settings profile page is excluded from short lived inertia cache headers', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))->not->toContain('max-age=30');
});
