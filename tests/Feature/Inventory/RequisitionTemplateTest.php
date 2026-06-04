<?php

use App\Models\Position;
use App\Models\Product;
use App\Models\RequisitionTemplate;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

test('requester can save a requisition template and see it on the requisitions page', function () {
    $position = Position::factory()->create();
    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-TEMPLATE-001',
        'name' => 'Copy Paper',
    ]);

    $this->actingAs($user)
        ->post(route('inventory.requisition-templates.store', absolute: false), [
            'name' => 'Monthly office supplies',
            'notes' => 'Standard weekly replenishment',
            'lines' => [
                ['sku' => $product->sku, 'qty_requested' => 5],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('requisition_templates', [
        'user_id' => $user->id,
        'name' => 'Monthly office supplies',
    ]);

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/requisitions/Index')
            ->where('can.manageTemplates', true)
            ->has('templates', 1)
            ->where('templates.0.name', 'Monthly office supplies')
            ->where('templates.0.lines.0.sku', 'SKU-TEMPLATE-001')
            ->where('templates.0.lines.0.availability.available', true));
});

test('template lines are flagged when the referenced product is inactive', function () {
    $position = Position::factory()->create();
    $user = User::factory()->assignedPosition($position)->create();
    $user->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-TEMPLATE-STALE',
        'name' => 'Printer Ink',
        'is_active' => true,
    ]);

    RequisitionTemplate::factory()->create([
        'user_id' => $user->id,
        'name' => 'Printer refill',
        'lines' => [
            [
                'sku' => $product->sku,
                'name' => $product->name,
                'qty_requested' => 2,
            ],
        ],
    ]);

    $product->update(['is_active' => false]);

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory/requisitions/Index')
            ->where('templates.0.lines.0.availability.available', false)
            ->where('templates.0.lines.0.availability.message', 'This product is currently inactive.'));
});

test('owners can update duplicate and delete their own requisition templates', function () {
    $ownerPosition = Position::factory()->create();
    $otherPosition = Position::factory()->create();

    $owner = User::factory()->assignedPosition($ownerPosition)->create();
    $owner->assignRole('Property Custodian');

    $otherUser = User::factory()->assignedPosition($otherPosition)->create();
    $otherUser->assignRole('Property Custodian');

    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-TEMPLATE-OWNER',
        'name' => 'Whiteboard Marker',
    ]);

    $template = RequisitionTemplate::factory()->create([
        'user_id' => $owner->id,
        'name' => 'Teaching kit',
        'notes' => 'Original notes',
        'lines' => [
            [
                'sku' => $product->sku,
                'name' => $product->name,
                'qty_requested' => 3,
            ],
        ],
    ]);

    $this->actingAs($otherUser)
        ->put(route('inventory.requisition-templates.update', $template, absolute: false), [
            'name' => 'Blocked update',
            'notes' => 'Should fail',
            'lines' => [
                ['sku' => $product->sku, 'qty_requested' => 1],
            ],
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->put(route('inventory.requisition-templates.update', $template, absolute: false), [
            'name' => 'Teaching kit v2',
            'notes' => 'Updated notes',
            'lines' => [
                ['sku' => $product->sku, 'qty_requested' => 4],
            ],
        ])
        ->assertRedirect();

    $template->refresh();

    expect($template->name)->toBe('Teaching kit v2');
    expect($template->notes)->toBe('Updated notes');
    expect($template->lines[0]['qty_requested'])->toBe(4);

    $this->actingAs($owner)
        ->post(route('inventory.requisition-templates.duplicate', $template, absolute: false))
        ->assertRedirect();

    expect(
        RequisitionTemplate::query()
            ->where('user_id', $owner->id)
            ->where('name', 'like', 'Copy of %')
            ->count()
    )->toBe(1);

    $this->actingAs($owner)
        ->delete(route('inventory.requisition-templates.destroy', $template, absolute: false))
        ->assertRedirect();

    $this->assertDatabaseMissing('requisition_templates', [
        'id' => $template->id,
    ]);
});
