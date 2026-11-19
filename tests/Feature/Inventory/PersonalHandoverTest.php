<?php

use App\Models\HandoverLog;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
});

test('personal handovers are recipient scoped before pagination and do not expose tokens', function () {
    $recipient = User::factory()->create();
    $own = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    HandoverLog::factory()->create();
    $this->actingAs($recipient)->get(route('inventory.handover.mine'))
        ->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('inventory/handover/Mine')->has('handovers.data', 1)
        ->where('handovers.data.0.id', $own->id)
        ->missing('handovers.data.0.verification_token_hash'));
});

test('recipient can start in app review without email and acceptance still requires a signature', function () {
    $recipient = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $this->actingAs($recipient)->post(route('inventory.handover.review', $handover))
        ->assertRedirect(route('inventory.handover.verify', $handover))
        ->assertSessionMissing("handover_verify_token.{$handover->id}");
    $this->get(route('inventory.handover.verify', $handover))->assertOk();
    $this->post(route('inventory.handover.verify.submit', $handover))->assertSessionHasErrors('signature_png');
    expect($handover->fresh()->verified_at)->toBeNull();
    $this->post(route('inventory.handover.verify.submit', $handover), ['signature_png' => validHandoverSignaturePng()])->assertRedirect();
    expect($handover->fresh()->verified_by)->toBe($recipient->id)
        ->and(StockMovement::query()->where('asset_id', $handover->asset_id)->where('movement_type', 'transfer')->count())->toBe(1);
    $this->post(route('inventory.handover.verify.submit', $handover), ['signature_png' => validHandoverSignaturePng()])->assertRedirect();
    expect(StockMovement::query()->where('asset_id', $handover->asset_id)->count())->toBe(1);
});

test('in app authorization rejects expiry rotation cancellation and wrong accounts', function (string $change) {
    $recipient = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $this->actingAs($recipient)->post(route('inventory.handover.review', $handover))->assertRedirect();
    match ($change) {
        'expired' => $this->travel(16)->minutes(),
        'rotated' => $handover->forceFill(['verification_token_hash' => hash('sha256', 'replacement')])->save(),
        'cancelled' => $handover->forceFill(['cancelled_at' => now()])->save(),
        'wrong account' => $this->actingAs(User::factory()->create()),
    };
    $response = $this->post(route('inventory.handover.verify.submit', $handover), ['signature_png' => validHandoverSignaturePng()]);
    if ($change === 'cancelled') {
        $response->assertRedirect();
    } else {
        $response->assertForbidden();
    }
    expect($handover->fresh()->verified_at)->toBeNull()
        ->and(StockMovement::query()->where('asset_id', $handover->asset_id)->count())->toBe(0);
})->with(['expired', 'rotated', 'cancelled', 'wrong account']);

test('review session cannot authorize another handover', function () {
    $recipient = User::factory()->create();
    $first = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $second = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $this->actingAs($recipient)->post(route('inventory.handover.review', $first))->assertRedirect();
    $this->post(route('inventory.handover.verify.submit', $second), ['signature_png' => validHandoverSignaturePng()])->assertForbidden();
});

test('starting an in app review replaces earlier email authorization so its deadline is enforced', function () {
    $recipient = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id, 'verification_token_hash' => hash('sha256', 'earlier-email-token')]);
    $this->actingAs($recipient)->withSession(["handover_verify_token.{$handover->id}" => 'earlier-email-token'])
        ->post(route('inventory.handover.review', $handover))->assertRedirect()
        ->assertSessionMissing("handover_verify_token.{$handover->id}");
    $this->travel(16)->minutes();
    $this->post(route('inventory.handover.verify.submit', $handover), ['signature_png' => validHandoverSignaturePng()])->assertForbidden();
    expect($handover->fresh()->verified_at)->toBeNull();
});

test('wrong account gets a private recovery page and switching resumes original email after login', function () {
    $recipient = User::factory()->create();
    $other = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id, 'verification_token_hash' => hash('sha256', 'email-token')]);
    $url = route('inventory.handover.verify', ['handoverLog' => $handover, 'token' => 'email-token']);
    $this->actingAs($other)->get($url)->assertForbidden()->assertInertia(fn (Assert $page) => $page
        ->component('inventory/handover/Recovery')->where('can_switch', true)->missing('handover'));
    $this->withSession(['private_previous_data' => 'must disappear'])
        ->post(route('account.switch'), ['resume_handover' => true, 'return_url' => 'https://attacker.example'])
        ->assertRedirect(route('login'))->assertSessionHas('url.intended', $url)->assertSessionMissing('private_previous_data');
    $this->assertGuest();
    $this->post(route('login'), ['email' => $recipient->email, 'password' => 'password'])->assertRedirect($url);
    $this->get($url)->assertRedirect(route('inventory.handover.verify', $handover));
    $this->get(route('inventory.handover.verify', $handover))->assertOk();
});

test('generic account switch ignores arbitrary destinations and supports unverified accounts', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->withSession(['url.intended' => 'https://attacker.example', 'handover_review' => ['private']])
        ->post(route('account.switch'), ['return_url' => 'https://attacker.example'])
        ->assertRedirect(route('login'))->assertSessionHas('url.intended', route('inventory.handover.mine'))
        ->assertSessionMissing('handover_review');
    $this->assertGuest();
    config(['app.debug' => false]);
    $this->get(route('account.switch'))->assertMethodNotAllowed();
});

test('guest unverified and other recipients cannot start review', function () {
    $handover = HandoverLog::factory()->create();
    $this->post(route('inventory.handover.review', $handover))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->post(route('inventory.handover.review', $handover))->assertRedirect(route('verification.notice'));
    $this->actingAs(User::factory()->create())->post(route('inventory.handover.review', $handover))->assertForbidden();
});

test('account switching forces a fresh Inertia login document and never accepts on GET', function () {
    $recipient = User::factory()->create();
    $handover = HandoverLog::factory()->create(['to_user_id' => $recipient->id]);
    $this->actingAs($recipient)->get(route('inventory.handover.verify', $handover))
        ->assertForbidden()->assertSessionMissing("handover_review.{$handover->id}");
    $this->withHeader('X-Inertia', 'true')->post(route('account.switch'))
        ->assertStatus(409)->assertHeader('X-Inertia-Location', route('login'))
        ->assertHeader('Cache-Control', 'max-age=0, must-revalidate, no-cache, no-store, private');
    $this->assertGuest();
    expect($handover->fresh()->verified_at)->toBeNull();
});

test('accepted and cancelled records appear in personal history not pending', function () {
    $recipient = User::factory()->create();
    HandoverLog::factory()->create(['to_user_id' => $recipient->id, 'verified_at' => now(), 'verified_by' => $recipient->id]);
    HandoverLog::factory()->create(['to_user_id' => $recipient->id, 'cancelled_at' => now()]);
    $this->actingAs($recipient)->get(route('inventory.handover.mine'))
        ->assertInertia(fn (Assert $page) => $page->has('handovers.data', 0));
    $this->get(route('inventory.handover.mine', ['view' => 'all']))
        ->assertInertia(fn (Assert $page) => $page->has('handovers.data', 2));
});
