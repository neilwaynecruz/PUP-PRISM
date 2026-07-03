<?php

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('receiving stock writes an audit log entry', function () {
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    $product = Product::factory()->consumable()->create([
        'sku' => 'AUDIT-RECEIVE-001',
        'reorder_threshold' => 5,
    ]);

    $this->actingAs($admin)
        ->post(route('inventory.receiving.store', absolute: false), [
            'sku' => $product->sku,
            'qty' => 10,
            'reference_no' => 'GRN-100',
        ])
        ->assertRedirect();

    expect(
        AuditLog::query()
            ->where('action', 'receive')
            ->where('model_type', 'Product')
            ->where('model_id', $product->id)
            ->exists()
    )->toBeTrue();
});

test('handover initiation writes an audit log entry', function () {
    $position = Position::factory()->create();
    $recipientPosition = Position::factory()->create();

    $custodian = User::factory()->assignedPosition($position)->create(['email_verified_at' => now()]);
    $custodian->assignRole('Property Custodian');

    $recipient = User::factory()->assignedPosition($recipientPosition)->create(['email_verified_at' => now()]);

    $product = Product::factory()->asset()->create();
    $asset = Asset::factory()->assignedToPosition($position)->create([
        'product_id' => $product->id,
        'tag_code' => 'TAG-AUDIT-001',
    ]);

    $this->actingAs($custodian)
        ->post(route('inventory.handover.store', absolute: false), [
            'asset_tag_code' => $asset->tag_code,
            'to_user_id' => $recipient->id,
            'notes' => 'Audit coverage test',
        ])
        ->assertRedirect();

    expect(
        AuditLog::query()
            ->where('action', 'handover_initiate')
            ->where('model_type', 'HandoverLog')
            ->exists()
    )->toBeTrue();
});

test('password updates write security audit log entries', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => 'password',
    ]);

    $this->actingAs($user)
        ->put(route('user-password.update', absolute: false), [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertRedirect();

    expect(
        AuditLog::query()
            ->where('action', 'password_change')
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();
});

test('audit log service records security-sensitive custom actions', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    AuditLogService::logCustom('api_token_create', 'Token created for integrations.', $user);

    expect(
        AuditLog::query()
            ->where('action', 'api_token_create')
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();
});
