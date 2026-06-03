<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\RequisitionStatus;
use App\Http\Resources\BookingResource;
use App\Http\Resources\HandoverLogResource;
use App\Http\Resources\ProductCollection;
use App\Http\Resources\RequisitionResource;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Http\Request;

test('product collection preserves paginator keys and row shape for inertia pages', function () {
    $product = Product::factory()->consumable()->create([
        'sku' => 'SKU-RSRC-0001',
        'name' => 'Resource Product',
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 9,
    ]);

    $paginator = Product::query()
        ->with(['category:id,name', 'origin:id,name', 'stock:id,product_id,on_hand_qty'])
        ->withCount('assets')
        ->orderBy('id')
        ->paginate(1);

    $payload = (new ProductCollection($paginator))->toArray(Request::create('/inventory/products'));

    expect($payload)->toHaveKeys(['data', 'current_page', 'last_page', 'links', 'per_page', 'total']);
    expect($payload['data'])->toHaveCount(1);
    expect($payload['data'][0])->toMatchArray([
        'sku' => 'SKU-RSRC-0001',
        'name' => 'Resource Product',
        'type' => 'consumable',
        'on_hand_qty' => 9,
        'assets_count' => 0,
    ]);
});

test('booking resource preserves approval queue fields and enum-backed labels', function () {
    $requesterPosition = Position::factory()->create();
    $approverPosition = Position::factory()->create();
    $requester = User::factory()->assignedPosition($requesterPosition)->create([
        'name' => 'Requester Name',
        'email' => 'requester@example.test',
    ]);
    $approver = User::factory()->assignedPosition($approverPosition)->create([
        'name' => 'Approver Name',
        'email' => 'approver@example.test',
    ]);
    $product = Product::factory()->asset()->create(['name' => 'Conference Laptop']);
    $asset = Asset::factory()->assignedToPosition($requesterPosition)->create([
        'product_id' => $product->id,
        'tag_code' => 'AST-RSRC-0001',
        'status' => AssetStatus::Available,
    ]);

    $booking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'approver_id' => $approver->id,
        'approver_position_id' => $approverPosition->id,
        'status' => BookingStatus::Requested,
    ])->load([
        'asset.product',
        'requester',
        'requesterPosition.department',
        'approver',
    ]);

    $payload = (new BookingResource($booking))->resolve(Request::create('/inventory/bookings'));

    expect($payload)->toMatchArray([
        'asset_id' => $asset->id,
        'asset_label' => 'AST-RSRC-0001',
        'title' => 'Conference Laptop - Requested',
        'status' => 'Requested',
        'requester_id' => $requester->id,
        'requested_ip_address' => $booking->requested_ip_address,
    ]);
    expect($payload['requester'])->toMatchArray([
        'id' => $requester->id,
        'name' => 'Requester Name',
        'email' => 'requester@example.test',
    ]);
});

test('requisition and handover resources preserve nested relationships and nullable summaries', function () {
    $fromPosition = Position::factory()->create();
    $toPosition = Position::factory()->create();
    $fromUser = User::factory()->assignedPosition($fromPosition)->create(['name' => 'From User']);
    $toUser = User::factory()->assignedPosition($toPosition)->create(['name' => 'To User']);

    $product = Product::factory()->asset()->create(['name' => 'Resource Asset']);
    $asset = Asset::factory()->assignedToPosition($fromPosition)->create([
        'product_id' => $product->id,
        'tag_code' => 'AST-RSRC-0002',
        'status' => AssetStatus::CheckedOut,
    ]);

    $handover = HandoverLog::factory()->create([
        'asset_id' => $asset->id,
        'from_user_id' => $fromUser->id,
        'to_user_id' => $toUser->id,
        'from_position_id' => $fromPosition->id,
        'to_position_id' => $toPosition->id,
    ])->load([
        'asset.product',
        'fromUser',
        'toUser',
        'fromPosition.department',
        'toPosition.department',
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $fromUser->id,
        'requester_position_id' => $fromPosition->id,
        'approver_id' => $toUser->id,
        'approver_position_id' => $toPosition->id,
        'status' => RequisitionStatus::Approved,
    ]);

    $requisition->lines()->create([
        'product_id' => Product::factory()->consumable()->create(['sku' => 'SKU-RSRC-LINE'])->id,
        'qty_requested' => 3,
        'qty_issued' => 1,
    ]);

    $requisition->load([
        'requester',
        'requesterPosition.department',
        'approver',
        'approverPosition.department',
        'issuer',
        'issuedPosition.department',
        'lines.product',
    ]);

    $handoverPayload = (new HandoverLogResource($handover))->resolve(Request::create('/inventory/handover'));
    $requisitionPayload = (new RequisitionResource($requisition))->resolve(Request::create('/inventory/requisitions/1'));

    expect($handoverPayload)->toMatchArray([
        'tag_code' => 'AST-RSRC-0002',
        'asset_name' => 'Resource Asset',
    ]);
    expect($handoverPayload['to'])->toMatchArray([
        'id' => $toUser->id,
        'name' => 'To User',
    ]);

    expect($requisitionPayload)->toMatchArray([
        'status' => 'Approved',
    ]);
    expect($requisitionPayload['requester'])->toMatchArray([
        'id' => $fromUser->id,
        'name' => 'From User',
    ]);
    expect($requisitionPayload['lines'][0])->toMatchArray([
        'sku' => 'SKU-RSRC-LINE',
        'qty_requested' => 3,
        'qty_issued' => 1,
    ]);
});
