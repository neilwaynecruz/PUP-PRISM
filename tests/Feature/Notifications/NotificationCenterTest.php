<?php

use App\Models\Product;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use Spatie\Permission\Models\Role;

uses()->group('notifications');

beforeEach(function () {
    Role::findOrCreate('Supply Head');
});

function notificationUser(): User
{
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    return $user;
}

test('users can mark a notification as read', function () {
    $user = notificationUser();
    $product = Product::factory()->create([
        'reorder_threshold' => 10,
    ]);

    $user->notify(new LowStockAlertNotification($product, 3));

    $notification = $user->notifications()->latest()->firstOrFail();

    $this->actingAs($user)
        ->put(route('notifications.read', $notification->id, absolute: false))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not()->toBeNull();
});

test('users can mark all notifications as read', function () {
    $user = notificationUser();
    $product = Product::factory()->create([
        'reorder_threshold' => 10,
    ]);

    $user->notify(new LowStockAlertNotification($product, 3));
    $user->notify(new LowStockAlertNotification($product, 2));

    $this->actingAs($user)
        ->put(route('notifications.read-all', absolute: false))
        ->assertRedirect();

    expect($user->fresh()->unreadNotifications()->count())->toBe(0);
});
