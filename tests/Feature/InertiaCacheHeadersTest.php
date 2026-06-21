<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('products index disables client caching for authenticated users', function () {
    Role::findOrCreate('Admin');

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Admin');

    $response = $this->actingAs($user)->get(route('inventory.products.index', absolute: false));

    $response->assertOk();

    $cacheControl = $response->headers->get('Cache-Control');

    expect($cacheControl)
        ->toContain('private')
        ->toContain('no-store');
});

test('settings profile page also disables client caching for authenticated users', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($user)->get(route('profile.edit'));

    $response->assertOk();
    expect($response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');
});
