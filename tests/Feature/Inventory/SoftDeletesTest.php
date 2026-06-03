<?php

use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Support\Facades\Route;

uses()->group('soft-deletes');

beforeEach(function () {
    $this->withoutVite();
    (new \Database\Seeders\RoleSeeder)->run();
});

describe('Product soft deletes', function () {
    it('soft deletes a product instead of hard deleting', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $product = Product::factory()->create([
            'type' => ProductType::Consumable,
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.products.destroy', $product))
            ->assertRedirect();

        $this->assertSoftDeleted($product);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
    });

    it('restores a soft deleted product', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $product = Product::factory()->create([
            'type' => ProductType::Consumable,
        ]);
        $product->delete();

        $this->actingAs($admin)
            ->put(route('inventory.products.restore', $product))
            ->assertRedirect();

        $this->assertNotSoftDeleted($product);
    });

    it('excludes soft deleted products from the index', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $visible = Product::factory()->create(['name' => 'Visible Product']);
        $deleted = Product::factory()->create(['name' => 'Deleted Product']);
        $deleted->delete();

        $response = $this->actingAs($admin)
            ->get(route('inventory.products.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('inventory/products/Index')
            ->has('products.data', 1)
            ->where('products.data.0.id', $visible->id)
        );
    });
});

describe('Booking soft deletes', function () {
    it('soft deletes a booking', function () {
        $user = User::factory()->create();
        $user->assignRole('Property Custodian');

        $asset = Asset::factory()->create([
            'product_id' => Product::factory()->create(['type' => ProductType::Asset, 'is_active' => true])->id,
            'status' => 'Available',
        ]);

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $user->id,
            'status' => BookingStatus::Requested,
        ]);

        $this->actingAs($user)
            ->delete(route('inventory.bookings.destroy', $booking))
            ->assertRedirect();

        $this->assertSoftDeleted($booking);
    });

    it('restores a soft deleted booking', function () {
        $user = User::factory()->create();
        $user->assignRole('Property Custodian');

        $asset = Asset::factory()->create([
            'product_id' => Product::factory()->create(['type' => ProductType::Asset, 'is_active' => true])->id,
            'status' => 'Available',
        ]);

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $user->id,
            'status' => BookingStatus::Requested,
        ]);
        $booking->delete();

        $this->actingAs($user)
            ->put(route('inventory.bookings.restore', $booking))
            ->assertRedirect();

        $this->assertNotSoftDeleted($booking);
    });
});

describe('Requisition soft deletes', function () {
    it('soft deletes a requisition', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $requisition = Requisition::factory()->create([
            'requester_id' => $user->id,
            'status' => RequisitionStatus::Draft,
        ]);

        $this->actingAs($user)
            ->delete(route('inventory.requisitions.destroy', $requisition))
            ->assertRedirect();

        $this->assertSoftDeleted($requisition);
    });

    it('restores a soft deleted requisition', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $requisition = Requisition::factory()->create([
            'requester_id' => $user->id,
            'status' => RequisitionStatus::Submitted,
        ]);
        $requisition->delete();

        $this->actingAs($user)
            ->put(route('inventory.requisitions.restore', $requisition))
            ->assertRedirect();

        $this->assertNotSoftDeleted($requisition);
    });
});

describe('Deleted by and reason tracking', function () {
    it('tracks who deleted a product and stores the reason', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $product = Product::factory()->create(['type' => ProductType::Consumable]);

        $this->actingAs($admin)
            ->delete(route('inventory.products.destroy', $product), [
                'deletion_reason' => 'Duplicate entry',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_by' => $admin->id,
            'deletion_reason' => 'Duplicate entry',
        ]);
    });

    it('tracks who deleted a booking and stores the reason', function () {
        $user = User::factory()->create();
        $user->assignRole('Property Custodian');

        $asset = Asset::factory()->create([
            'product_id' => Product::factory()->create(['type' => ProductType::Asset, 'is_active' => true])->id,
            'status' => 'Available',
        ]);

        $booking = Booking::factory()->create([
            'asset_id' => $asset->id,
            'requester_id' => $user->id,
            'status' => BookingStatus::Requested,
        ]);

        $this->actingAs($user)
            ->delete(route('inventory.bookings.destroy', $booking), [
                'deletion_reason' => 'Cancelled by requester',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'deleted_by' => $user->id,
            'deletion_reason' => 'Cancelled by requester',
        ]);
    });

    it('tracks who deleted a requisition and stores the reason', function () {
        $user = User::factory()->create();
        $user->assignRole('Admin');

        $requisition = Requisition::factory()->create([
            'requester_id' => $user->id,
            'status' => RequisitionStatus::Draft,
        ]);

        $this->actingAs($user)
            ->delete(route('inventory.requisitions.destroy', $requisition), [
                'deletion_reason' => 'Created by mistake',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('requisitions', [
            'id' => $requisition->id,
            'deleted_by' => $user->id,
            'deletion_reason' => 'Created by mistake',
        ]);
    });
});

describe('Unified trash viewer', function () {
    it('shows trashed items from all models to admin', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $product = Product::factory()->create(['name' => 'Trash Product']);
        $product->delete();

        $response = $this->actingAs($admin)
            ->get(route('inventory.trash'));

        $response->assertInertia(fn ($page) => $page
            ->component('inventory/Trash')
            ->has('items.data', 1)
            ->where('items.data.0.type', 'product')
            ->where('items.data.0.label', fn ($label) => str_contains($label, 'Trash Product'))
        );
    });

    it('filters trashed items by type', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $product = Product::factory()->create(['name' => 'Trash Product']);
        $product->delete();

        $requisition = Requisition::factory()->create([
            'requester_id' => $admin->id,
            'status' => RequisitionStatus::Draft,
        ]);
        $requisition->delete();

        $response = $this->actingAs($admin)
            ->get(route('inventory.trash', ['type' => 'requisition']));

        $response->assertInertia(fn ($page) => $page
            ->component('inventory/Trash')
            ->has('items.data', 1)
            ->where('items.data.0.type', 'requisition')
        );
    });

    it('searches trashed items by label', function () {
        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $productA = Product::factory()->create(['name' => 'Alpha Widget', 'sku' => 'WGT-001']);
        $productA->delete();

        $productB = Product::factory()->create(['name' => 'Beta Gadget', 'sku' => 'GGT-002']);
        $productB->delete();

        $response = $this->actingAs($admin)
            ->get(route('inventory.trash', ['search' => 'Alpha']));

        $response->assertInertia(fn ($page) => $page
            ->component('inventory/Trash')
            ->has('items.data', 1)
            ->where('items.data.0.label', fn ($label) => str_contains($label, 'Alpha Widget'))
        );
    });
});
