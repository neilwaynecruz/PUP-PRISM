<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new RoleSeeder)->run();
});

function masterDataAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    return $admin;
}

test('non-admin users cannot access master data admin routes', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('Property Custodian');

    $this->actingAs($user)
        ->get(route('admin.departments.index', absolute: false))
        ->assertForbidden();
});

test('admin can create update and deactivate departments', function () {
    $admin = masterDataAdmin();

    $this->actingAs($admin)
        ->post(route('admin.departments.store', absolute: false), [
            'name' => 'College of Engineering',
            'code' => 'COE',
            'is_active' => true,
        ])
        ->assertRedirect();

    $department = Department::query()->where('code', 'COE')->firstOrFail();

    $this->actingAs($admin)
        ->put(route('admin.departments.update', $department, absolute: false), [
            'name' => 'College of Engineering Updated',
            'code' => 'COE',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($department->fresh()->name)->toBe('College of Engineering Updated');

    $this->actingAs($admin)
        ->patch(route('admin.departments.deactivate', $department, absolute: false))
        ->assertRedirect();

    expect($department->fresh()->is_active)->toBeFalse();
});

test('admin cannot delete department with positions', function () {
    $admin = masterDataAdmin();
    $department = Department::factory()->create();
    Position::factory()->create(['department_id' => $department->id]);

    $this->actingAs($admin)
        ->delete(route('admin.departments.destroy', $department, absolute: false))
        ->assertRedirect();

    expect(Department::query()->whereKey($department->id)->exists())->toBeTrue();
});

test('admin can manage positions with department validation', function () {
    $admin = masterDataAdmin();
    $department = Department::factory()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.positions.store', absolute: false), [
            'department_id' => $department->id,
            'title' => 'Property Custodian',
            'code' => 'PC-001',
            'is_active' => true,
        ])
        ->assertRedirect();

    $position = Position::query()->where('code', 'PC-001')->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.positions.edit', $position, absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/positions/Edit')
            ->has('impactWarnings')
            ->has('usage'));
});

test('admin cannot delete position assigned to users', function () {
    $admin = masterDataAdmin();
    $position = Position::factory()->create();
    User::factory()->assignedPosition($position)->create();

    $this->actingAs($admin)
        ->delete(route('admin.positions.destroy', $position, absolute: false))
        ->assertRedirect();

    expect(Position::query()->whereKey($position->id)->exists())->toBeTrue();
});

test('admin cannot deactivate position with active users', function () {
    $admin = masterDataAdmin();
    $position = Position::factory()->create(['is_active' => true]);
    User::factory()->assignedPosition($position)->create(['is_active' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.positions.deactivate', $position, absolute: false))
        ->assertRedirect();

    expect($position->fresh()->is_active)->toBeTrue();
});

test('admin cannot delete category or origin used by products', function () {
    $admin = masterDataAdmin();
    $category = Category::factory()->create();
    $origin = Origin::factory()->create();

    Product::factory()->create([
        'category_id' => $category->id,
        'origin_id' => $origin->id,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.categories.destroy', $category, absolute: false))
        ->assertRedirect();

    $this->actingAs($admin)
        ->delete(route('admin.origins.destroy', $origin, absolute: false))
        ->assertRedirect();

    expect(Category::query()->whereKey($category->id)->exists())->toBeTrue();
    expect(Origin::query()->whereKey($origin->id)->exists())->toBeTrue();
});

test('admin can deactivate category used by products', function () {
    $admin = masterDataAdmin();
    $category = Category::factory()->create(['is_active' => true]);
    Product::factory()->create(['category_id' => $category->id]);

    $this->actingAs($admin)
        ->patch(route('admin.categories.deactivate', $category, absolute: false))
        ->assertRedirect();

    expect($category->fresh()->is_active)->toBeFalse();
});

test('product category options exclude inactive categories except current selection', function () {
    $admin = masterDataAdmin();
    $active = Category::factory()->create(['name' => 'Active Category', 'is_active' => true]);
    $inactive = Category::factory()->create(['name' => 'Legacy Category', 'is_active' => false]);
    $product = Product::factory()->create(['category_id' => $inactive->id]);

    $this->actingAs($admin)
        ->get(route('inventory.products.edit', $product, absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('categories', fn ($categories) => collect($categories)->contains('id', $active->id)
                && collect($categories)->contains('id', $inactive->id)));
});
