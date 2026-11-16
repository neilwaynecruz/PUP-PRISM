<?php

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Property Custodian');
    Role::findOrCreate('Supply Head');
});

function workflowCustodian(): User
{
    $custodian = User::factory()->assignedPosition(Position::factory()->create())->create();
    $custodian->assignRole('Property Custodian');

    return $custodian;
}

function workflowAsset(?Position $position = null, string $tag = 'TAG-WF-1'): Asset
{
    return Asset::factory()->assignedToPosition($position ?? Position::factory()->create())->create([
        'product_id' => Product::factory()->asset()->create(['name' => 'Projector Epson'])->id,
        'status' => AssetStatus::Available,
        'tag_code' => $tag,
    ]);
}

function pendingHandoverFor(Asset $asset, User $recipient, User $initiator): HandoverLog
{
    return HandoverLog::factory()->create([
        'asset_id' => $asset->id,
        'to_user_id' => $recipient->id,
        'to_position_id' => $recipient->position_id,
        'from_user_id' => $initiator->id,
        'from_position_id' => $asset->position_id,
        'initiated_by' => $initiator->id,
        'initiated_at' => now()->subHour(),
        'last_sent_at' => now()->subHour(),
        'verified_at' => null,
        'verification_token_hash' => hash('sha256', 'original-token'),
    ]);
}

test('custodians can browse and search assets by name with availability flags', function () {
    $custodian = workflowCustodian();
    workflowAsset(tag: 'TAG-AVAIL');
    $busy = workflowAsset(tag: 'TAG-BUSY');
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    pendingHandoverFor($busy, $recipient, $custodian);

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['asset_search' => 'Projector'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/handover/Initiate')
            ->has('assetOptions', 2)
            ->where('assetOptions', fn ($options) => collect($options)->firstWhere('tag_code', 'TAG-AVAIL')['unavailable_reason'] === null
                && collect($options)->firstWhere('tag_code', 'TAG-BUSY')['unavailable_reason'] === 'Handover awaiting verification'));
});

test('reviewed serial numbers search handover candidates without bypassing eligibility', function () {
    $custodian = workflowCustodian();
    $asset = workflowAsset(tag: 'TAG-SERIAL-OK');
    $asset->update(['serial_number' => 'SN-OCR-ONLY']);
    $excluded = workflowAsset(tag: 'TAG-SERIAL-EXCLUDED');
    $excluded->update(['serial_number' => 'SN-OCR-ONLY-2', 'status' => AssetStatus::Condemned]);

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['asset_search' => 'SN-OCR-ONLY'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('assetOptions', 1)
            ->where('assetOptions.0.id', $asset->id)
            ->where('selectedAsset', null));

    $this->get(route('inventory.handover.index', ['asset_search' => 'SN-NO-MATCH'], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('assetOptions', 0));
    expect(HandoverLog::query()->count())->toBe(0);
});

test('selecting an asset shows its accountability, location, and eligibility', function () {
    $custodian = workflowCustodian();
    $department = Department::factory()->create(['name' => 'College of Engineering']);
    $position = Position::factory()->create(['department_id' => $department->id, 'title' => 'Lab Head']);
    $holder = User::factory()->assignedPosition($position)->create(['name' => 'Current Holder']);
    workflowAsset($position, 'TAG-SEL');

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['asset' => 'TAG-SEL'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedAsset.tag_code', 'TAG-SEL')
            ->where('selectedAsset.eligible', true)
            ->where('selectedAsset.position.title', 'Lab Head')
            ->where('selectedAsset.position.department', 'College of Engineering')
            ->where('selectedAsset.position_holders', [$holder->name])
            ->where('users', fn ($users) => collect($users)->firstWhere('id', $holder->id)['unavailable_reason'] === 'Already accountable for this asset'));

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['asset' => 'NOPE-404'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedAsset', null)
            ->where('selectedAssetMissing', true));
});

test('a selected asset with a pending handover lists the blocker', function () {
    $custodian = workflowCustodian();
    $asset = workflowAsset(tag: 'TAG-BLOCKED');
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create(['name' => 'Waiting Recipient']);
    pendingHandoverFor($asset, $recipient, $custodian);

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['asset' => 'TAG-BLOCKED'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedAsset.eligible', false)
            ->where('selectedAsset.blockers.0', 'This asset already has a handover pending verification.')
            ->where('selectedAsset.pending_handover.to', 'Waiting Recipient'));
});

test('handover recipients must be active personnel', function () {
    $custodian = workflowCustodian();
    workflowAsset(tag: 'TAG-INACTIVE');
    $inactive = User::factory()->assignedPosition(Position::factory()->create())->create(['is_active' => false]);

    $this->actingAs($custodian)
        ->post(route('inventory.handover.store', absolute: false), [
            'asset_tag_code' => 'TAG-INACTIVE',
            'to_user_id' => $inactive->id,
        ])
        ->assertSessionHasErrors('to_user_id');

    expect(HandoverLog::query()->count())->toBe(0);
});

test('an asset cannot be handed over to the position that already holds it', function () {
    $custodian = workflowCustodian();
    $position = Position::factory()->create();
    workflowAsset($position, 'TAG-SAME');
    $sameHolder = User::factory()->assignedPosition($position)->create();

    $this->actingAs($custodian)
        ->post(route('inventory.handover.store', absolute: false), [
            'asset_tag_code' => 'TAG-SAME',
            'to_user_id' => $sameHolder->id,
        ])
        ->assertSessionHasErrors('to_user_id');
});

test('a pending handover can be cancelled, invalidating its link and freeing the asset', function () {
    Notification::fake();
    $custodian = workflowCustodian();
    $asset = workflowAsset(tag: 'TAG-CANCEL');
    $originalPositionId = $asset->position_id;
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    $recipient->markEmailAsVerified();
    $handover = pendingHandoverFor($asset, $recipient, $custodian);

    $this->actingAs($custodian)
        ->post(route('inventory.handover.cancel', $handover, absolute: false), [
            'cancellation_reason' => 'Sent to the wrong person.',
        ])
        ->assertRedirect();

    $handover->refresh();

    expect($handover->status())->toBe(HandoverLog::STATUS_CANCELLED)
        ->and($handover->cancelled_by)->toBe($custodian->id)
        ->and($handover->verification_token_hash)->toBeNull()
        ->and(AuditLog::query()->where('action', 'handover_cancel')->where('model_id', $handover->id)->exists())->toBeTrue();

    // The previously emailed link no longer works.
    $this->actingAs($recipient)
        ->get(route('inventory.handover.verify', ['handoverLog' => $handover->id, 'token' => 'original-token'], absolute: false))
        ->assertRedirect(route('dashboard', absolute: false));

    // Accountability never moved, and the asset can be handed over again.
    expect($asset->fresh()->position_id)->toBe($originalPositionId);

    $nextRecipient = User::factory()->assignedPosition(Position::factory()->create())->create();

    $this->actingAs($custodian)
        ->post(route('inventory.handover.store', absolute: false), [
            'asset_tag_code' => 'TAG-CANCEL',
            'to_user_id' => $nextRecipient->id,
        ])
        ->assertSessionHasNoErrors();

    expect(HandoverLog::query()->pending()->where('asset_id', $asset->id)->count())->toBe(1);
});

test('cancellation requires a reason and only applies to pending handovers', function () {
    $custodian = workflowCustodian();
    $asset = workflowAsset(tag: 'TAG-CANCEL-2');
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    $handover = pendingHandoverFor($asset, $recipient, $custodian);

    $this->actingAs($custodian)
        ->post(route('inventory.handover.cancel', $handover, absolute: false), ['cancellation_reason' => ''])
        ->assertSessionHasErrors('cancellation_reason');

    $handover->forceFill(['verified_at' => now()])->save();

    $this->actingAs($custodian)
        ->post(route('inventory.handover.cancel', $handover, absolute: false), ['cancellation_reason' => 'Too late.'])
        ->assertRedirect();

    expect($handover->fresh()->cancelled_at)->toBeNull();
});

test('resending a verification link rotates the token and respects a cooldown', function () {
    Notification::fake();
    $custodian = workflowCustodian();
    $asset = workflowAsset(tag: 'TAG-RESEND');
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    $handover = pendingHandoverFor($asset, $recipient, $custodian);
    $originalHash = $handover->verification_token_hash;

    $this->actingAs($custodian)
        ->post(route('inventory.handover.resend', $handover, absolute: false))
        ->assertRedirect();

    $handover->refresh();

    expect($handover->verification_token_hash)->not->toBe($originalHash)
        ->and(AuditLog::query()->where('action', 'handover_resend')->count())->toBe(1);

    Notification::assertSentTo(
        $recipient,
        HandoverVerificationNotification::class,
        fn (HandoverVerificationNotification $notification) => hash('sha256', $notification->token) === $handover->verification_token_hash,
    );

    $rotatedHash = $handover->verification_token_hash;

    $this->actingAs($custodian)
        ->post(route('inventory.handover.resend', $handover, absolute: false))
        ->assertRedirect();

    expect($handover->fresh()->verification_token_hash)->toBe($rotatedHash);
});

test('supply heads cannot cancel or resend handovers', function () {
    $custodian = workflowCustodian();
    $supplyHead = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $supplyHead->assignRole('Supply Head');
    $asset = workflowAsset(tag: 'TAG-ROLE');
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    $handover = pendingHandoverFor($asset, $recipient, $custodian);

    $this->actingAs($supplyHead)
        ->post(route('inventory.handover.cancel', $handover, absolute: false), ['cancellation_reason' => 'Not mine.'])
        ->assertForbidden();

    $this->actingAs($supplyHead)
        ->post(route('inventory.handover.resend', $handover, absolute: false))
        ->assertForbidden();
});

test('handover history can be filtered by status', function () {
    $custodian = workflowCustodian();
    $recipient = User::factory()->assignedPosition(Position::factory()->create())->create();
    pendingHandoverFor(workflowAsset(tag: 'TAG-H1'), $recipient, $custodian);
    pendingHandoverFor(workflowAsset(tag: 'TAG-H2'), $recipient, $custodian)->forceFill(['cancelled_at' => now()])->save();

    $this->actingAs($custodian)
        ->get(route('inventory.handover.index', ['history_status' => 'cancelled'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recent.data', 1)
            ->where('recent.data.0.status', 'cancelled')
            ->where('counts.pending', 1));
});
