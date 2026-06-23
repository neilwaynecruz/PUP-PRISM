<?php

use App\Models\Supplier;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $this->withoutVite();
});

test('supply head can create a supplier', function () {
    $user = User::factory()->create();
    $user->assignRole('Supply Head');
    $csrfToken = 'supplier-store-token';

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->post(route('inventory.suppliers.store', absolute: false), [
            '_token' => $csrfToken,
            'name' => 'Northwind Procurement',
            'contact_person' => 'Maria Santos',
            'email' => 'northwind@example.test',
            'phone' => '0917-200-1000',
            'lead_time_days' => 7,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect(Supplier::query()->where('name', 'Northwind Procurement')->exists())
        ->toBeTrue();
});

test('admin can delete an unused supplier', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');
    $csrfToken = 'supplier-delete-token';

    $supplier = Supplier::factory()->create();

    $this->actingAs($user)
        ->withSession(['_token' => $csrfToken])
        ->delete(route('inventory.suppliers.destroy', $supplier, absolute: false), [
            '_token' => $csrfToken,
        ])
        ->assertRedirect(route('inventory.suppliers.index', absolute: false));

    expect(Supplier::query()->whereKey($supplier->id)->exists())->toBeFalse();
});
