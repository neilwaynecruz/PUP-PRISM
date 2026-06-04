<?php

use App\Models\AuditLog;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
});

test('audit log index shows readable diffs and hides sensitive raw values', function () {
    $user = User::factory()->create();
    $user->assignRole('Admin');

    AuditLog::query()->create([
        'user_id' => $user->id,
        'action' => 'update',
        'model_type' => 'Product',
        'model_id' => 42,
        'description' => 'Product updated.',
        'old_values' => [
            'name' => 'Old Name',
            'status' => 'Draft',
            'is_active' => false,
            'updated_at' => now()->subMinute()->toIso8601String(),
            'password' => 'hidden-old',
        ],
        'new_values' => [
            'name' => 'New Name',
            'status' => 'Approved',
            'is_active' => true,
            'updated_at' => now()->toIso8601String(),
            'password' => 'hidden-new',
        ],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Pest',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.audit-logs.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/AuditLog')
            ->has('logs.data', 1)
            ->where('logs.data.0.model_label', 'Product')
            ->has('logs.data.0.changes', 3)
            ->where('logs.data.0.changes.0.label', 'Name')
            ->where('logs.data.0.changes.0.old_value', 'Old Name')
            ->where('logs.data.0.changes.0.new_value', 'New Name')
            ->where('logs.data.0.changes.2.label', 'Is Active')
            ->where('logs.data.0.changes.2.old_value', 'No')
            ->where('logs.data.0.changes.2.new_value', 'Yes')
            ->missing('logs.data.0.raw_old_values.password')
            ->missing('logs.data.0.raw_new_values.password'));
});
