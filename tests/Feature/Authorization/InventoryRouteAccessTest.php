<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function inventoryUserWithRole(string $role): User
{
    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole($role);

    return $user;
}

test('handover routes stay limited to admins and property custodians', function () {
    $propertyCustodian = inventoryUserWithRole('Property Custodian');
    $supplyHead = inventoryUserWithRole('Supply Head');

    $this->actingAs($propertyCustodian)
        ->get(route('inventory.handover.index', absolute: false))
        ->assertOk();

    $this->actingAs($supplyHead)
        ->get(route('inventory.handover.index', absolute: false))
        ->assertForbidden();
});

test('receiving and label routes stay limited to admins and supply heads', function () {
    $supplyHead = inventoryUserWithRole('Supply Head');
    $propertyCustodian = inventoryUserWithRole('Property Custodian');
    $product = Product::factory()->create();

    $this->actingAs($supplyHead)
        ->get(route('inventory.receiving.index', absolute: false))
        ->assertOk();

    $this->actingAs($supplyHead)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertOk();

    $this->actingAs($propertyCustodian)
        ->get(route('inventory.receiving.index', absolute: false))
        ->assertForbidden();

    $this->actingAs($propertyCustodian)
        ->get(route('inventory.products.label', $product, absolute: false))
        ->assertForbidden();
});

test('stock movements route stays admin only', function () {
    $admin = inventoryUserWithRole('Admin');
    $supplyHead = inventoryUserWithRole('Supply Head');

    $this->actingAs($admin)
        ->get(route('inventory.movements.index', absolute: false))
        ->assertOk();

    $this->actingAs($supplyHead)
        ->get(route('inventory.movements.index', absolute: false))
        ->assertForbidden();
});
