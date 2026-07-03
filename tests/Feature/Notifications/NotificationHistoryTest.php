<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Supply Head');
});

test('users can browse paginated notification history with filters', function () {
    $user = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $user->assignRole('Supply Head');

    $product = Product::factory()->create(['reorder_threshold' => 10]);
    $user->notify(new LowStockAlertNotification($product, 3));
    $user->notify(new LowStockAlertNotification($product, 2));

    $latest = $user->notifications()->latest()->firstOrFail();
    $latest->markAsRead();

    $this->actingAs($user)
        ->get(route('notifications.index', ['read' => 'unread'], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('notifications/Index')
            ->has('notifications.data', 1)
            ->where('unreadCount', 1));

    $this->actingAs($user)
        ->get(route('notifications.index', ['read' => 'read'], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
});

test('users only see their own notification history', function () {
    $owner = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $owner->assignRole('Supply Head');

    $other = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $other->assignRole('Supply Head');
    $product = Product::factory()->create(['reorder_threshold' => 10]);

    $owner->notify(new LowStockAlertNotification($product, 1));
    $other->notify(new LowStockAlertNotification($product, 1));

    $this->actingAs($owner)
        ->get(route('notifications.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
});
