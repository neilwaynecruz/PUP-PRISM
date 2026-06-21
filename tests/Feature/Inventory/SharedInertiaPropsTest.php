<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
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

test('authenticated inertia pages share recent notifications and unread counts', function () {
    $user = User::factory()->create();
    $product = Product::factory()->create([
        'reorder_threshold' => 10,
    ]);

    $user->notify(new LowStockAlertNotification($product, 4));

    $this->actingAs($user)
        ->get(route('dashboard', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.unreadCount', 1)
            ->has('notifications.items', 1)
            ->where('notifications.items.0.category', 'inventory')
            ->where('notifications.items.0.severity', 'warning')
            ->where('notifications.items.0.title', 'Low stock alert'));
});
