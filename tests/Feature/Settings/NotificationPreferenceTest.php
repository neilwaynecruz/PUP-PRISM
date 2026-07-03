<?php

use App\Enums\NotificationEventType;
use App\Models\NotificationPreference;
use App\Models\Product;
use App\Models\User;
use App\Notifications\DailyNotificationDigest;
use App\Notifications\LowStockAlertNotification;
use App\Services\NotificationPreferenceSeeder;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Supply Head');
});

test('disabled mail channel prevents mail notification delivery', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    NotificationPreference::query()->updateOrCreate(
        [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::LowStock->value,
        ],
        [
            'mail_enabled' => false,
            'database_enabled' => true,
            'broadcast_enabled' => true,
            'digest_frequency' => 'instant',
        ],
    );

    $product = Product::factory()->consumable()->create();
    $notification = new LowStockAlertNotification($product, 4);

    $channels = $notification->via($user);

    expect($channels)->toContain('database')
        ->and($channels)->toContain('broadcast')
        ->and($channels)->not->toContain('mail');
});

test('user can view and update notification preferences', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'notification-preferences-token';

    $this->actingAs($user)
        ->get(route('notification-preferences.edit', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/NotificationPreferences')
            ->has('preferences')
            ->has('eventTypes'));

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->put(route('notification-preferences.update', absolute: false), [
            '_token' => $csrfToken,
            'preferences' => [
                [
                    'event_type' => NotificationEventType::LowStock->value,
                    'mail_enabled' => false,
                    'database_enabled' => true,
                    'broadcast_enabled' => false,
                    'digest_frequency' => 'daily',
                ],
                [
                    'event_type' => NotificationEventType::RequisitionSubmitted->value,
                    'mail_enabled' => true,
                    'database_enabled' => true,
                    'broadcast_enabled' => true,
                    'digest_frequency' => 'instant',
                ],
                [
                    'event_type' => NotificationEventType::RequisitionStatusChanged->value,
                    'mail_enabled' => true,
                    'database_enabled' => true,
                    'broadcast_enabled' => true,
                    'digest_frequency' => 'instant',
                ],
                [
                    'event_type' => NotificationEventType::ProcurementRecommendation->value,
                    'mail_enabled' => true,
                    'database_enabled' => true,
                    'broadcast_enabled' => true,
                    'digest_frequency' => 'instant',
                ],
                [
                    'event_type' => NotificationEventType::HandoverVerification->value,
                    'mail_enabled' => true,
                    'database_enabled' => true,
                    'broadcast_enabled' => true,
                    'digest_frequency' => 'instant',
                ],
            ],
        ])
        ->assertRedirect(route('notification-preferences.edit', absolute: false));

    $preference = NotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('event_type', NotificationEventType::LowStock->value)
        ->first();

    expect($preference)->not->toBeNull()
        ->and($preference->mail_enabled)->toBeFalse()
        ->and($preference->digest_frequency)->toBe('daily');
});

test('digest command sends aggregated email for daily digest users', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    NotificationPreference::query()->updateOrCreate(
        [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::LowStock->value,
        ],
        [
            'mail_enabled' => true,
            'database_enabled' => true,
            'broadcast_enabled' => false,
            'digest_frequency' => 'daily',
        ],
    );

    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => LowStockAlertNotification::class,
        'data' => [
            'event_type' => NotificationEventType::LowStock->value,
            'title' => 'Low stock alert',
            'message' => 'SKU-001 is down to 4 on hand.',
        ],
        'read_at' => null,
        'digested_at' => null,
        'created_at' => now()->subHours(3),
        'updated_at' => now()->subHours(3),
    ]);

    $this->artisan('app:send-notification-digests')
        ->assertExitCode(0);

    Notification::assertSentTo($user, DailyNotificationDigest::class);

    expect($user->fresh()->notifications()->first()?->digested_at)->not->toBeNull();
});

test('default preferences are seeded when a user is created with a role', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    app(NotificationPreferenceSeeder::class)->seedForUser($user);

    expect(
        NotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('event_type', NotificationEventType::LowStock->value)
            ->exists()
    )->toBeTrue();
});

test('notification service skips users with all delivery channels disabled', function () {
    Notification::fake();

    $user = User::factory()->create();
    $user->assignRole('Supply Head');

    NotificationPreference::query()->updateOrCreate(
        [
            'user_id' => $user->id,
            'event_type' => NotificationEventType::LowStock->value,
        ],
        [
            'mail_enabled' => false,
            'database_enabled' => false,
            'broadcast_enabled' => false,
            'digest_frequency' => 'instant',
        ],
    );

    $product = Product::factory()->consumable()->create();

    app(NotificationService::class)->lowStockAlert($product, 3);

    Notification::assertNothingSentTo($user);
});
