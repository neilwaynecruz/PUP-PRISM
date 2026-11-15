<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function () {
    withoutVite();

    Role::findOrCreate('Admin');
});

test('audit log index shows readable diffs without exposing raw value payloads', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Admin');

    AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => 'update',
        'model_type' => 'Product',
        'model_id' => 42,
        'description' => 'Product updated.',
        'old_values' => [
            'name' => 'Old Name',
            'status' => 'Draft',
            'is_active' => false,
            'updated_at' => now()->subMinute()->toIso8601String(),
            'password' => 'hidden-old',
        ],
        'new_values' => [
            'name' => 'New Name',
            'status' => 'Approved',
            'is_active' => true,
            'updated_at' => now()->toIso8601String(),
            'password' => 'hidden-new',
        ],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    actingAs($user)
        ->get(route('inventory.audit-logs.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/AuditLog')
            ->has('logs.data', 1)
            ->where('logs.data.0.model_label', 'Product')
            ->has('logs.data.0.changes', 3)
            ->where('logs.data.0.changes.0.label', 'Name')
            ->where('logs.data.0.changes.0.old_value', 'Old Name')
            ->where('logs.data.0.changes.0.new_value', 'New Name')
            ->where('logs.data.0.changes.2.label', 'Is Active')
            ->where('logs.data.0.changes.2.old_value', 'No')
            ->where('logs.data.0.changes.2.new_value', 'Yes')
            ->missing('logs.data.0.raw_old_values')
            ->missing('logs.data.0.raw_new_values'));
});

test('custom audit entries keep their before and after values', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Admin');
    actingAs($user);

    AuditLogService::logCustom(
        'profile_email_change',
        'Profile email changed.',
        $user,
        ['email' => 'old@example.test'],
        ['email' => 'new@example.test'],
    );

    $log = AuditLog::query()->where('action', 'profile_email_change')->sole();

    expect($log->old_values)->toBe(['email' => 'old@example.test'])
        ->and($log->new_values)->toBe(['email' => 'new@example.test']);
});

test('audit log entries use readable action labels and can be filtered by actor', function () {
    $admin = User::factory()->withTwoFactor()->create(['name' => 'Audit Admin']);
    $admin->assignRole('Admin');
    $other = User::factory()->create(['name' => 'Other Person']);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'handover_initiate',
        'model_type' => 'HandoverLog',
        'model_id' => 7,
        'description' => 'Handover initiated.',
    ]);
    AuditLog::query()->create([
        'user_id' => $other->id,
        'action' => 'update',
        'model_type' => 'Product',
        'model_id' => 9,
        'description' => 'Product updated.',
    ]);

    actingAs($admin)
        ->get(route('inventory.audit-logs.index', ['user_id' => $admin->id], absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action_label', 'Handover initiated')
            ->where('logs.data.0.model_label', 'Handover')
            ->where('filterOptions.actions', fn ($actions) => collect($actions)->contains(fn ($option) => $option['value'] === 'handover_initiate' && $option['label'] === 'Handover initiated'))
            ->has('filterOptions.users', 2));
});

test('audit log rejects malformed date filters', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    actingAs($admin)
        ->get(route('inventory.audit-logs.index', ['date_from' => "2026-01-01' OR 1=1"], absolute: false))
        ->assertSessionHasErrors('date_from');
});

test('audit log export neutralises spreadsheet formulas and records the export', function () {
    $admin = User::factory()->withTwoFactor()->create();
    $admin->assignRole('Admin');

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'update',
        'model_type' => 'Product',
        'model_id' => 1,
        'description' => '=HYPERLINK("http://evil.test","click")',
    ]);

    $response = actingAs($admin)
        ->get(route('inventory.audit-logs.export', absolute: false))
        ->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain("'=HYPERLINK")
        ->and(AuditLog::query()->where('action', 'audit_export')->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('non-admins cannot export audit logs', function () {
    Role::findOrCreate('Supply Head');
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Supply Head');

    actingAs($user)
        ->get(route('inventory.audit-logs.export', absolute: false))
        ->assertForbidden();
});

test('audit log search matches description, action, ip address, and actor case-insensitively', function () {
    $admin = User::factory()->withTwoFactor()->create([
        'name' => 'Elena Marquez',
        'email' => 'elena.marquez@example.test',
    ]);
    $admin->assignRole('Admin');

    $other = User::factory()->create([
        'name' => 'Miguel Santos',
        'email' => 'miguel.santos@example.test',
    ]);

    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'PurchaseOrderApproved',
        'model_type' => 'PurchaseOrder',
        'model_id' => 15,
        'description' => 'Approved LaserJet Toner Batch',
        'ip_address' => '10.24.8.5',
    ]);
    AuditLog::query()->create([
        'user_id' => $other->id,
        'action' => 'update',
        'model_type' => 'Product',
        'model_id' => 16,
        'description' => 'Updated Chalk Inventory',
        'ip_address' => '192.168.1.9',
    ]);

    foreach (['laserjet', 'purchaseorderapproved', 'elena.marquez', '10.24.8.5'] as $term) {
        actingAs($admin)
            ->get(route('inventory.audit-logs.index', ['search' => $term], absolute: false))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data', fn ($rows) => collect($rows)->contains(
                    fn ($row): bool => $row['description'] === 'Approved LaserJet Toner Batch',
                ))
                ->where('logs.data', fn ($rows) => ! collect($rows)->contains(
                    fn ($row): bool => $row['description'] === 'Updated Chalk Inventory',
                )));
    }
});
