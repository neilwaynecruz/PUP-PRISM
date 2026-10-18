<?php

use App\Models\Department;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
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

    $factory = User::factory()->assignedPosition($position);

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
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

test('static inventory trash routes are not shadowed by detail routes', function (string $uri, string $expectedRoute): void {
    $route = app('router')->getRoutes()->match(Request::create($uri, 'GET'));

    expect($route->getName())->toBe($expectedRoute);
})->with([
    'bookings' => ['/inventory/bookings/trash', 'inventory.bookings.trash'],
    'requisitions' => ['/inventory/requisitions/trash', 'inventory.requisitions.trash'],
    'products' => ['/inventory/products/trash', 'inventory.products.trash'],
]);

test('numeric inventory detail routes still resolve', function (string $uri, string $expectedRoute): void {
    $route = app('router')->getRoutes()->match(Request::create($uri, 'GET'));

    expect($route->getName())->toBe($expectedRoute);
})->with([
    'bookings' => ['/inventory/bookings/123', 'inventory.bookings.show'],
    'requisitions' => ['/inventory/requisitions/123', 'inventory.requisitions.show'],
    'products' => ['/inventory/products/123', 'inventory.products.show'],
]);

test('inventory detail routes reject non numeric identifiers', function (string $uri): void {
    $this->actingAs(inventoryUserWithRole('Admin'))
        ->get($uri)
        ->assertNotFound();
})->with([
    'bookings' => ['/inventory/bookings/not-a-number'],
    'requisitions' => ['/inventory/requisitions/not-a-number'],
    'products' => ['/inventory/products/not-a-number'],
]);
