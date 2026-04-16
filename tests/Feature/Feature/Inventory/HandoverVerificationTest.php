<?php

use App\Models\Asset;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('property custodian can initiate handover and recipient can verify (creates transfer movement)', function () {
    Notification::fake();

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
        ->post(route('inventory.handover.store', absolute: false), [
            'asset_tag_code' => 'TAG-0001',
            'to_user_id' => $recipient->id,
        ])
        ->assertRedirect();

    $handoverLogId = null;
    $token = null;

    Notification::assertSentTo($recipient, HandoverVerificationNotification::class, function (HandoverVerificationNotification $n) use (&$handoverLogId, &$token) {
        $handoverLogId = $n->handoverLogId;
        $token = $n->token;

        return $n->handoverLogId > 0 && $n->token !== '';
    });

    $this->actingAs($recipient)
        ->post(route('inventory.handover.verify.submit', ['handoverLog' => $handoverLogId], absolute: false), [
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
