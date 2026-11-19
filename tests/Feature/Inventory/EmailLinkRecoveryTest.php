<?php

use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\User;
use App\Notifications\BookingStatusChangedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\DailyNotificationDigest;
use App\Notifications\ExpiringStockAlertNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\ProcurementRecommendationNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

test('handover survives recipient email verification without exposing its details early', function () {
    $recipient = User::factory()->unverified()->create();
    $token = 'synthetic-email-recovery-token';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'verification_token_hash' => hash('sha256', $token),
    ]);
    $link = route('inventory.handover.verify', ['handoverLog' => $handover, 'token' => $token]);

    $this->actingAs($recipient)->get($link)
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('url.intended', $link)
        ->assertSessionMissing("handover_verify_token.{$handover->id}");

    $verification = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
        'id' => $recipient->id,
        'hash' => sha1($recipient->email),
    ]);
    $this->get($verification)->assertRedirect($link);
    $this->get($link)->assertRedirect(route('inventory.handover.verify', $handover));
    $this->get(route('inventory.handover.verify', $handover))
        ->assertOk()->assertInertia(fn (Assert $page) => $page->component('inventory/handover/Verify'));
    expect($handover->fresh()->verified_at)->toBeNull();
});

test('wrong account gets actionable handover guidance without recipient details or acceptance', function () {
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    $token = 'wrong-account-recovery-token';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'verification_token_hash' => hash('sha256', $token),
    ]);

    $this->actingAs($other)->get(route('inventory.handover.verify', ['handoverLog' => $handover, 'token' => $token]))
        ->assertForbidden()
        ->assertSee('Sign out and reopen the latest handover email')
        ->assertDontSee($recipient->email)
        ->assertDontSee($token)
        ->assertSessionMissing("handover_verify_token.{$handover->id}");
    expect($handover->fresh()->verified_at)->toBeNull();
});

test('invalid and missing handover tokens explain recovery without weakening rejection', function (bool $includeToken) {
    $recipient = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $parameters = ['handoverLog' => $handover];
    if ($includeToken) {
        $parameters['token'] = 'invalid-recovery-token';
    }

    $this->actingAs($recipient)->get(route('inventory.handover.verify', $parameters))
        ->assertForbidden()->assertSee('latest handover email')
        ->assertSessionMissing("handover_verify_token.{$handover->id}");
    expect($handover->fresh()->verified_at)->toBeNull();
})->with([false, true]);

test('emailed module actions use reachable authorized destinations and still require login', function (string $kind) {
    Role::findOrCreate('Admin');
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Admin');
    $product = Product::factory()->consumable()->create();
    $notification = match ($kind) {
        'booking submitted' => new BookingSubmittedNotification(Booking::factory()->create()),
        'booking status' => new BookingStatusChangedNotification(Booking::factory()->create(['requester_id' => $user->id]), 'approved'),
        'requisition submitted' => new RequisitionSubmittedNotification(Requisition::factory()->create()),
        'requisition status' => new RequisitionStatusChangedNotification(Requisition::factory()->create(['requester_id' => $user->id]), 'approved'),
        'low stock' => new LowStockAlertNotification($product, 0),
        'expiry' => new ExpiringStockAlertNotification(StockLot::factory()->create(['product_id' => $product->id, 'expires_at' => now()->addDays(3)]), 3),
        'forecast' => new ProcurementRecommendationNotification($product, 5, 10),
        'digest' => new DailyNotificationDigest(collect()),
    };
    $url = $notification->toMail($user)->actionUrl;
    expect($url)->toStartWith('http://localhost/');
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($user)->get($url)->assertOk();
})->with(['booking submitted', 'booking status', 'requisition submitted', 'requisition status', 'low stock', 'expiry', 'forecast', 'digest']);
