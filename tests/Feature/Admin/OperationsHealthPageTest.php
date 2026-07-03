<?php

use App\Models\User;
use App\Support\SchedulerHeartbeat;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
});

test('admin can view operations health page', function () {
    Cache::put(
        SchedulerHeartbeat::cacheKey(SchedulerHeartbeat::COMMAND_TRASH_CLEANUP),
        now()->toIso8601String(),
    );

    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('admin.operations.health', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/operations/Health')
            ->has('health.queue_operations')
            ->has('tracked_commands'));
});

test('non-admin users cannot view operations health page', function () {
    Role::findOrCreate('Supply Head');

    $user = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $user->assignRole('Supply Head');

    $this->actingAs($user)
        ->get(route('admin.operations.health', absolute: false))
        ->assertForbidden();
});
