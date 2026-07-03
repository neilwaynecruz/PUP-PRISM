<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Department;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function handoverAdmin(Position $position): User
{
    $admin = User::factory()->withTwoFactor()->assignedPosition($position)->create();
    $admin->assignRole('Admin');

    return $admin;
}

test('property custodian can initiate handover and recipient can verify (creates transfer movement)', function () {
    Notification::fake();
    $storeToken = 'handover-store-token';
    $verifyToken = 'handover-verify-token';

    $custodianPosition = Position::factory()->create();
    $recipientPosition = Position::factory()->create();

    $custodian = User::factory()->assignedPosition($custodianPosition)->create();
    $custodian->assignRole('Property Custodian');

    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($custodianPosition)->create([
        'product_id' => $product->id,
        'status' => AssetStatus::Available,
        'tag_code' => 'TAG-0001',
    ]);

    $this->actingAs($custodian)
        ->withSession(['_token' => $storeToken])
        ->post(route('inventory.handover.store', absolute: false), [
            '_token' => $storeToken,
            'asset_tag_code' => 'TAG-0001',
            'to_user_id' => $recipient->id,
        ])
        ->assertRedirect();

    $capturedNotification = null;

    Notification::assertSentTo($recipient, HandoverVerificationNotification::class, function (HandoverVerificationNotification $notification) use (&$capturedNotification): bool {
        $capturedNotification = $notification;

        return $notification->handoverLogId > 0 && $notification->token !== '';
    });

    assert($capturedNotification instanceof HandoverVerificationNotification);

    $this->actingAs($recipient)
        ->withSession(['_token' => $verifyToken])
        ->post(route('inventory.handover.verify.submit', ['handoverLog' => $capturedNotification->handoverLogId], absolute: false), [
            '_token' => $verifyToken,
            'token' => $capturedNotification->token,
            'signature_png' => validHandoverSignaturePng(),
        ])
        ->assertRedirect(route('inventory.handover.index', absolute: false));

    $asset->refresh();
    expect($asset->status)->toBe(AssetStatus::CheckedOut);
    expect($asset->position_id)->toBe($recipientPosition->id);

    $handover = HandoverLog::query()->findOrFail($capturedNotification->handoverLogId);
    expect($handover->from_position_id)->toBe($custodianPosition->id);
    expect($handover->to_position_id)->toBe($recipientPosition->id);
    expect($handover->verified_by)->toBe($recipient->id);

    $movement = StockMovement::query()
        ->where('asset_id', $asset->id)
        ->where('movement_type', 'transfer')
        ->whereNotNull('ip_address')
        ->firstOrFail();

    expect($movement->accountable_position_id)->toBe($recipientPosition->id);
});

test('handover index bounds recipients and keeps recent handovers visible', function () {
    $department = Department::factory()->create(['name' => 'Information Technology']);
    $position = Position::factory()->create(['department_id' => $department->id]);

    $admin = handoverAdmin($position);

    User::factory()->count(30)->assignedPosition($position)->create();

    $this->actingAs($admin)
        ->get(route('inventory.handover.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/handover/Initiate')
            ->has('users', 25)
            ->has('recent'));
});

test('recipient can open the verification page for a pending handover', function () {
    $recipientPosition = Position::factory()->create();
    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipientPosition->id,
    ]);

    $previewToken = 'preview-token';

    $this->actingAs($recipient)
        ->get(route('inventory.handover.verify', ['handoverLog' => $handover, 'token' => $previewToken], absolute: false))
        ->assertRedirect(route('inventory.handover.verify', $handover, absolute: false));

    $this->actingAs($recipient)
        ->get(route('inventory.handover.verify', $handover, absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/handover/Verify')
            ->where('handover.id', $handover->id)
            ->where('handover.token', $previewToken)
            ->where('email_verified', true));
});

test('invalid handover signature formats are rejected', function (string $signature) {
    $recipientPosition = Position::factory()->create();
    $csrfToken = 'handover-invalid-signature-token';
    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $plainToken = 'handover-invalid-signature-plain-token';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipientPosition->id,
        'verification_token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($recipient)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.handover.verify.submit', $handover, absolute: false), [
            '_token' => $csrfToken,
            'token' => $plainToken,
            'signature_png' => $signature,
        ])
        ->assertSessionHasErrors(['signature_png']);

    $handover->refresh();

    expect($handover->verified_at)->toBeNull();
    expect($handover->signature_png)->toBeNull();
})->with([
    'non data uri' => 'not-a-signature',
    'jpeg data uri' => 'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQAAAQABAAD',
    'invalid png magic bytes' => 'data:image/png;base64,AAAA',
]);

test('valid png data uri signatures are accepted', function () {
    $recipientPosition = Position::factory()->create();
    $csrfToken = 'handover-valid-signature-token';
    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $plainToken = 'handover-valid-signature-plain-token';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipientPosition->id,
        'verification_token_hash' => hash('sha256', $plainToken),
    ]);

    $this->actingAs($recipient)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.handover.verify.submit', $handover, absolute: false), [
            '_token' => $csrfToken,
            'token' => $plainToken,
            'signature_png' => validHandoverSignaturePng(),
        ])
        ->assertRedirect(route('inventory.handover.index', absolute: false));

    $handover->refresh();

    expect($handover->verified_at)->not->toBeNull();
    expect($handover->signature_png)->toBe(validHandoverSignaturePng());
});

test('oversized decoded handover signatures are rejected before verification completes', function () {
    $recipientPosition = Position::factory()->create();
    $csrfToken = 'handover-oversize-token';
    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $plainToken = 'handover-signature-token';
    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipientPosition->id,
        'verification_token_hash' => hash('sha256', $plainToken),
    ]);

    $oversizedDecoded = "\x89PNG".str_repeat("\x00", 512001 - 4);
    $oversizedSignature = 'data:image/png;base64,'.base64_encode($oversizedDecoded);

    $this->actingAs($recipient)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.handover.verify.submit', $handover, absolute: false), [
            '_token' => $csrfToken,
            'token' => $plainToken,
            'signature_png' => $oversizedSignature,
        ])
        ->assertSessionHasErrors(['signature_png']);

    $handover->refresh();

    expect($handover->verified_at)->toBeNull();
    expect($handover->signature_png)->toBeNull();
});

test('verified recipients can download the handover receipt pdf', function () {
    $recipientPosition = Position::factory()->create();
    $recipient = User::factory()->assignedPosition($recipientPosition)->create();
    $recipient->markEmailAsVerified();

    $handover = HandoverLog::factory()->create([
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipientPosition->id,
        'verified_at' => now(),
        'verified_by' => $recipient->id,
        'verification_token_hash' => null,
    ]);

    $response = $this->actingAs($recipient)
        ->get(route('inventory.handover.receipt', $handover, absolute: false));

    $response->assertOk();

    expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
    expect((string) $response->headers->get('content-disposition'))->toContain('.pdf');
});

test('web responses include security headers', function () {
    $position = Position::factory()->create();
    $user = handoverAdmin($position);

    $response = $this->actingAs($user)
        ->get(route('inventory.handover.index', absolute: false));

    $response->assertOk();
    expect($response->headers->get('X-Frame-Options'))->toBe('DENY');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});
