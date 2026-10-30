<?php

use App\Models\Category;
use App\Models\Department;
use App\Models\Origin;
use App\Models\Position;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->withoutVite();
    (new RoleSeeder)->run();
});

function duplicateCheckAdmin(): User
{
    $admin = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $admin->assignRole('Admin');

    return $admin;
}

test('master data names are unique regardless of letter case', function (string $route, array $existing, string $model, array $payload, string $field) {
    $model::query()->create($existing);

    $this->actingAs(duplicateCheckAdmin())
        ->post(route($route, absolute: false), $payload)
        ->assertSessionHasErrors($field);

    expect($model::query()->count())->toBe(1);
})->with([
    'category' => ['admin.categories.store', ['name' => 'Office Supplies', 'is_active' => true], Category::class, ['name' => 'office supplies', 'is_active' => true], 'name'],
    'origin' => ['admin.origins.store', ['name' => 'Donation', 'is_active' => true], Origin::class, ['name' => 'DONATION', 'is_active' => true], 'name'],
    'department' => ['admin.departments.store', ['name' => 'College of Engineering', 'code' => 'COE', 'is_active' => true], Department::class, ['name' => 'college of engineering', 'is_active' => true], 'name'],
]);

test('position titles are unique within a department regardless of case but may repeat across departments', function () {
    $admin = duplicateCheckAdmin();
    $engineering = Department::factory()->create();
    $science = Department::factory()->create();
    Position::factory()->create(['department_id' => $engineering->id, 'title' => 'Lab Head', 'code' => 'ENG-LH']);

    $this->actingAs($admin)
        ->post(route('admin.positions.store', absolute: false), [
            'department_id' => $engineering->id,
            'title' => 'LAB HEAD',
            'code' => 'ENG-LH2',
            'is_active' => true,
        ])
        ->assertSessionHasErrors('title');

    $this->actingAs($admin)
        ->post(route('admin.positions.store', absolute: false), [
            'department_id' => $science->id,
            'title' => 'Lab Head',
            'code' => 'SCI-LH',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();
});

test('renaming a record to its own name with different casing is allowed', function () {
    $admin = duplicateCheckAdmin();
    $category = Category::query()->create(['name' => 'office supplies', 'is_active' => true]);

    $this->actingAs($admin)
        ->put(route('admin.categories.update', $category, absolute: false), [
            'name' => 'Office Supplies',
            'is_active' => true,
        ])
        ->assertSessionHasNoErrors();

    expect($category->fresh()->name)->toBe('Office Supplies');
});
