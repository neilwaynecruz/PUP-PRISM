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

test('supply head cannot access restricted inventory modules', function (
    string $routeName,
) {
    $position = Position::factory()->create();
    $user = User::factory()->create(['position_id' => $position->id]);
    $user->assignRole('Supply Head');

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
    'stock movements' => 'inventory.movements.index',
    'audit logs' => 'inventory.audit-logs.index',
]);
