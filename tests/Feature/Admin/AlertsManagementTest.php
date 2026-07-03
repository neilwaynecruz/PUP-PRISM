<?php

use App\Models\User;
use Database\Factories\InventoryAlertFactory;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
});

function alertsAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    return $admin;
}

function alertsSupplyHead(): User
{
    $user = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $user->assignRole('Supply Head');

    return $user;
}

test('admin and supply head can view alerts inbox', function () {
    InventoryAlertFactory::new()->create();

    $this->actingAs(alertsSupplyHead())
        ->get(route('admin.alerts.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/alerts/Index')
            ->has('alerts.data', 1)
            ->has('summary'));
});

test('custodian cannot access alerts inbox', function () {
    Role::findOrCreate('Property Custodian');

    $custodian = User::factory()->create(['email_verified_at' => now()]);
    $custodian->assignRole('Property Custodian');

    $this->actingAs($custodian)
        ->get(route('admin.alerts.index', absolute: false))
        ->assertForbidden();
});

test('operator can acknowledge assign and resolve an active alert', function () {
    $admin = alertsAdmin();
    $assignee = alertsSupplyHead();
    $alert = InventoryAlertFactory::new()->create([
        'message' => 'Low stock on test SKU.',
    ]);

    $this->actingAs($admin)
        ->patch(route('admin.alerts.acknowledge', $alert, absolute: false))
        ->assertRedirect();

    $alert->refresh();
    expect($alert->acknowledged_at)->not->toBeNull();
    expect($alert->acknowledged_by)->toBe($admin->id);
    expect($alert->assigned_to)->toBe($admin->id);

    $this->actingAs($admin)
        ->patch(route('admin.alerts.assign', $alert, absolute: false), [
            'assigned_to' => $assignee->id,
        ])
        ->assertRedirect();

    $alert->refresh();
    expect($alert->assigned_to)->toBe($assignee->id);

    $this->actingAs($admin)
        ->patch(route('admin.alerts.resolve', $alert, absolute: false), [
            'resolution_notes' => 'Replenishment PO raised.',
        ])
        ->assertRedirect();

    $alert->refresh();
    expect($alert->resolved_at)->not->toBeNull();
    expect($alert->resolved_by)->toBe($admin->id);
    expect($alert->resolution_notes)->toBe('Replenishment PO raised.');
});

test('resolved alerts cannot be acknowledged again', function () {
    $admin = alertsAdmin();
    $alert = InventoryAlertFactory::new()->resolved()->create();

    $this->actingAs($admin)
        ->patch(route('admin.alerts.acknowledge', $alert, absolute: false))
        ->assertForbidden();
});
