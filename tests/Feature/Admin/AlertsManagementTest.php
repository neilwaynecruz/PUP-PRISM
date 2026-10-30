<?php

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductStock;
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

test('alert actions are recorded in the audit log with before and after values', function () {
    $admin = alertsAdmin();
    $assignee = alertsSupplyHead();
    $alert = InventoryAlertFactory::new()->create();

    $this->actingAs($admin)->patch(route('admin.alerts.acknowledge', $alert, absolute: false));
    $this->actingAs($admin)->patch(route('admin.alerts.assign', $alert, absolute: false), ['assigned_to' => $assignee->id]);
    $this->actingAs($admin)->patch(route('admin.alerts.resolve', $alert, absolute: false), ['resolution_notes' => 'Restocked.']);

    $logs = AuditLog::query()
        ->where('model_type', 'InventoryAlert')
        ->where('model_id', $alert->id)
        ->orderBy('id')
        ->get();

    expect($logs->pluck('action')->all())->toBe(['alert_acknowledge', 'alert_assign', 'alert_resolve'])
        ->and($logs[1]->old_values['assigned_to'])->toBe($admin->name)
        ->and($logs[1]->new_values['assigned_to'])->toBe($assignee->name)
        ->and($logs[2]->new_values['resolution_notes'])->toBe('Restocked.');
});

test('an alert must be acknowledged before it can be resolved', function () {
    $admin = alertsAdmin();
    $alert = InventoryAlertFactory::new()->create();

    $this->actingAs($admin)
        ->patch(route('admin.alerts.resolve', $alert, absolute: false), ['resolution_notes' => 'Done.'])
        ->assertSessionHasErrors('resolution_notes');

    expect($alert->fresh()->resolved_at)->toBeNull();
});

test('alerts cannot be assigned to users outside the alert operator roles', function () {
    Role::findOrCreate('Property Custodian');
    $admin = alertsAdmin();
    $custodian = User::factory()->create(['email_verified_at' => now()]);
    $custodian->assignRole('Property Custodian');
    $alert = InventoryAlertFactory::new()->create();

    $this->actingAs($admin)
        ->patch(route('admin.alerts.assign', $alert, absolute: false), ['assigned_to' => $custodian->id])
        ->assertSessionHasErrors('assigned_to');

    expect($alert->fresh()->assigned_to)->toBeNull();
});

test('resolved alerts cannot be resolved or reassigned again', function () {
    $admin = alertsAdmin();
    $alert = InventoryAlertFactory::new()->resolved()->create(['acknowledged_at' => now()]);

    $this->actingAs($admin)
        ->patch(route('admin.alerts.resolve', $alert, absolute: false))
        ->assertForbidden();

    $this->actingAs($admin)
        ->patch(route('admin.alerts.assign', $alert, absolute: false), ['assigned_to' => $admin->id])
        ->assertForbidden();
});

test('the alerts inbox shows open alerts by default with derived severity', function () {
    $product = Product::factory()->consumable()->create();
    ProductStock::query()->updateOrCreate(['product_id' => $product->id], ['on_hand_qty' => 0]);

    InventoryAlertFactory::new()->create(['product_id' => $product->id]);
    InventoryAlertFactory::new()->resolved()->create();

    $this->actingAs(alertsAdmin())
        ->get(route('admin.alerts.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
            ->has('alerts.data', 1)
            ->where('alerts.data.0.severity', 'critical')
            ->where('alerts.data.0.product.on_hand_qty', 0)
            ->where('summary.unacknowledged', 1));

    $this->actingAs(alertsAdmin())
        ->get(route('admin.alerts.index', ['status' => 'all'], absolute: false))
        ->assertInertia(fn ($page) => $page->has('alerts.data', 2));
});
