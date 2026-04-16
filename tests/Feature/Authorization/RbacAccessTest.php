<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

test('unverified users are redirected to verification notice', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertRedirect(route('verification.notice', absolute: false));
});

test('verified users without role cannot access role-protected route', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.health', absolute: false))
        ->assertForbidden();
});

test('verified users with Admin role can access role-protected route', function () {
    Role::findOrCreate('Admin');
    $user = User::factory()->create();
    $user->assignRole('Admin');

    $this->actingAs($user)
        ->get(route('admin.health', absolute: false))
        ->assertNoContent();
});
