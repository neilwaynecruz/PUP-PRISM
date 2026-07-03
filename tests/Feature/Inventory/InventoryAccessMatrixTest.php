<?php

use App\Models\Position;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function inventoryAccessUser(string $role, Position $position): User
{
    $factory = User::factory()->create(['position_id' => $position->id]);

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    $factory->assignRole($role);

    return $factory;
}

test('supply head cannot access restricted inventory modules', function (
    string $routeName,
) {
    $position = Position::factory()->create();
    $user = inventoryAccessUser('Supply Head', $position);

    $this->actingAs($user)
        ->get(route($routeName, absolute: false))
        ->assertForbidden();
})->with([
    'handover' => 'inventory.handover.index',
    'stock movements' => 'inventory.movements.index',
    'audit logs' => 'inventory.audit-logs.index',
]);

test('property custodian cannot access restricted inventory modules', function (
    string $routeName,
) {
    $position = Position::factory()->create();
    $user = User::factory()->create(['position_id' => $position->id]);
    $user->assignRole('Property Custodian');

    $this->actingAs($user)
        ->get(route($routeName, absolute: false))
        ->assertForbidden();
})->with([
    'receiving' => 'inventory.receiving.index',
    'suppliers' => 'inventory.suppliers.index',
    'purchase orders' => 'inventory.purchase-orders.index',
    'stock movements' => 'inventory.movements.index',
    'audit logs' => 'inventory.audit-logs.index',
]);

test('plain authenticated users cannot access inventory modules', function (
    string $routeName,
) {
    $position = Position::factory()->create();
    $user = User::factory()->create(['position_id' => $position->id]);

    $this->actingAs($user)
        ->get(route($routeName, absolute: false))
        ->assertForbidden();
})->with([
    'products' => 'inventory.products.index',
    'handover' => 'inventory.handover.index',
    'bookings' => 'inventory.bookings.index',
    'requisitions' => 'inventory.requisitions.index',
    'receiving' => 'inventory.receiving.index',
    'suppliers' => 'inventory.suppliers.index',
    'purchase orders' => 'inventory.purchase-orders.index',
    'stock movements' => 'inventory.movements.index',
    'audit logs' => 'inventory.audit-logs.index',
]);
