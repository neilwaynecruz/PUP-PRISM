<?php

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
        'status' => 'Available',
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

    $handoverLogId = null;
    $token = null;

    Notification::assertSentTo($recipient, HandoverVerificationNotification::class, function (HandoverVerificationNotification $notification) use (&$handoverLogId, &$token) {
        $handoverLogId = $notification->handoverLogId;
        $token = $notification->token;

        return $notification->handoverLogId > 0 && $notification->token !== '';
    });

    $this->actingAs($recipient)
        ->withSession(['_token' => $verifyToken])
        ->post(route('inventory.handover.verify.submit', ['handoverLog' => $handoverLogId], absolute: false), [
            '_token' => $verifyToken,
            'token' => $token,
            'signature_png' => 'data:image/png;base64,AAAA',
        ])
        ->assertRedirect(route('inventory.handover.index', absolute: false));

    $asset->refresh();
    expect($asset->status)->toBe('Checked_Out');
    expect($asset->position_id)->toBe($recipientPosition->id);

    $handover = HandoverLog::query()->findOrFail($handoverLogId);
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

    $admin = User::factory()->assignedPosition($position)->create();
    $admin->assignRole('Admin');

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

    $this->actingAs($recipient)
        ->get(route('inventory.handover.verify', ['handoverLog' => $handover, 'token' => 'preview-token'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/handover/Verify')
            ->where('handover.id', $handover->id)
            ->where('handover.token', 'preview-token')
            ->where('email_verified', true));
});

test('oversized handover signatures are rejected before verification completes', function () {
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

    $oversizedSignature = 'data:image/png;base64,'.str_repeat('A', 300001);

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
