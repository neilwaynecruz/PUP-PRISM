<?php

use App\Models\Booking;
use App\Models\Department;
use App\Models\Position;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function inventoryPagesUser(string $role, ?Position $position = null): User
{
    $factory = User::factory();

    if ($position !== null) {
        $factory = $factory->assignedPosition($position);
    }

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('supply head can open the receiving index', function () {
    $user = inventoryPagesUser('Supply Head');

    $this->actingAs($user)
        ->get(route('inventory.receiving.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('inventory/receiving/Index'));
});

test('admin can open the stock movements index with paginated data', function () {
    $department = Department::factory()->create(['name' => 'Logistics']);
    $position = Position::factory()->create(['department_id' => $department->id]);

    $admin = inventoryPagesUser('Admin', $position);

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-MOVE-0001',
        'name' => 'Movement Product',
    ]);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'performed_by' => $admin->id,
        'accountable_position_id' => $position->id,
        'movement_type' => 'receive',
        'qty_delta' => 5,
    ]);

    $this->actingAs($admin)
        ->get(route('inventory.movements.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/movements/Index')
            ->has('movements.data', 1)
            ->where('movements.data.0.product.sku', 'SKU-MOVE-0001'));
});

test('supply head cannot open another users booking details directly', function () {
    $position = Position::factory()->create();

    $supplyHead = inventoryPagesUser('Supply Head', $position);

    $requester = User::factory()->assignedPosition($position)->create();

    $booking = Booking::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.bookings.show', $booking, absolute: false))
        ->assertForbidden();
});
