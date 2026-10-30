<?php

use App\Enums\RequisitionStatus;
use App\Models\Position;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Property Custodian');
});

function requisitionListUser(): User
{
    $user = User::factory()->assignedPosition(Position::factory()->create())->create();
    $user->assignRole('Property Custodian');

    return $user;
}

function requisitionWithLine(User $requester, Product $product, int $requested, int $issued, RequisitionStatus $status): Requisition
{
    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => $status,
    ]);

    RequisitionLine::factory()->create([
        'requisition_id' => $requisition->id,
        'product_id' => $product->id,
        'qty_requested' => $requested,
        'qty_issued' => $issued,
    ]);

    return $requisition;
}

test('the requisition list shows item summaries, issue totals, and status counts', function () {
    $user = requisitionListUser();
    $paper = Product::factory()->consumable()->create(['name' => 'Bond Paper A4', 'sku' => 'SKU-PAPER']);

    requisitionWithLine($user, $paper, 10, 4, RequisitionStatus::PartiallyIssued);
    requisitionWithLine($user, $paper, 5, 0, RequisitionStatus::Submitted);

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', absolute: false))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisitions.data', 2)
            ->has('requisitions.links')
            ->where('requisitions.total', 2)
            ->where('requisitions.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => $row['totals'] === ['line_count' => 1, 'qty_requested' => 10, 'qty_issued' => 4]
                    && $row['lines'][0]['name'] === 'Bond Paper A4',
            ))
            ->where('statusCounts.PartiallyIssued', 1)
            ->where('statusCounts.Submitted', 1));
});

test('requisitions can be found by the products they contain', function () {
    $user = requisitionListUser();
    $paper = Product::factory()->consumable()->create(['name' => 'Bond Paper A4', 'sku' => 'SKU-PAPER']);
    $ink = Product::factory()->consumable()->create(['name' => 'Printer Ink Black', 'sku' => 'SKU-INK']);

    $paperRequest = requisitionWithLine($user, $paper, 3, 0, RequisitionStatus::Submitted);
    requisitionWithLine($user, $ink, 2, 0, RequisitionStatus::Submitted);

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', ['search' => 'SKU-PAPER'], absolute: false))
        ->assertInertia(fn (Assert $page) => $page
            ->has('requisitions.data', 1)
            ->where('requisitions.data.0.id', $paperRequest->id));

    $this->actingAs($user)
        ->get(route('inventory.requisitions.index', ['search' => "#{$paperRequest->id}"], absolute: false))
        ->assertInertia(fn (Assert $page) => $page->where('requisitions.data.0.id', $paperRequest->id));
});
