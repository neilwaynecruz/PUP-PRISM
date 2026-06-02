<?php

use App\Models\Asset;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('receive consumable creates stock, lot, and movement records', function () {
    $user = User::factory()->create();
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-SERVICE-0001']);
    $service = app(InventoryService::class);
    $receivedAt = CarbonImmutable::parse('2026-01-10 09:00:00');
    $expiresAt = CarbonImmutable::parse('2026-07-10');

    $service->receiveConsumable(
        user: $user,
        product: $product,
        qty: 12,
        referenceNo: 'DEL-1001',
        receivedAt: $receivedAt,
        expiresAt: $expiresAt,
        notes: 'Warehouse receipt',
        ipAddress: '10.0.0.10',
    );

    $stock = ProductStock::query()->where('product_id', $product->id)->firstOrFail();
    $lot = StockLot::query()->where('product_id', $product->id)->firstOrFail();
    $movement = StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'receive')->firstOrFail();

    expect($stock->on_hand_qty)->toBe(12);
    expect($lot->reference_no)->toBe('DEL-1001');
    expect($lot->qty_received)->toBe(12);
    expect($lot->qty_remaining)->toBe(12);
    expect($lot->received_at?->toIso8601String())->toBe($receivedAt->toIso8601String());
    expect($lot->expires_at?->toDateString())->toBe($expiresAt->toDateString());
    expect($movement->qty_delta)->toBe(12);
    expect($movement->ip_address)->toBe('10.0.0.10');
});

test('receive consumable updates an existing stock row and allows a null reference number', function () {
    $user = User::factory()->create();
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-SERVICE-0002']);
    $existingStock = ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 5,
    ]);
    $service = app(InventoryService::class);

    $service->receiveConsumable(
        user: $user,
        product: $product,
        qty: 7,
        referenceNo: null,
        receivedAt: CarbonImmutable::parse('2026-02-01 08:00:00'),
        expiresAt: null,
        notes: 'Top-up receipt',
        ipAddress: '10.0.0.11',
    );

    $existingStock->refresh();
    $lot = StockLot::query()->where('product_id', $product->id)->latest('id')->firstOrFail();

    expect(ProductStock::query()->where('product_id', $product->id)->count())->toBe(1);
    expect($existingStock->on_hand_qty)->toBe(12);
    expect($lot->reference_no)->toBeNull();
    expect($lot->expires_at)->toBeNull();
});

test('receive assets creates assets and movement rows for each tag', function () {
    $user = User::factory()->create();
    $product = Product::factory()->asset()->create();
    $service = app(InventoryService::class);

    $service->receiveAssets(
        user: $user,
        product: $product,
        tagCodes: ['AST-SVC-0001', 'AST-SVC-0002'],
        notes: 'Asset delivery',
        ipAddress: '10.0.0.20',
    );

    expect(Asset::query()->where('product_id', $product->id)->count())->toBe(2);
    expect(StockMovement::query()->where('product_id', $product->id)->where('movement_type', 'receive')->count())->toBe(2);
});

test('receive assets rolls back when a duplicate tag code is encountered', function () {
    $user = User::factory()->create();
    $product = Product::factory()->asset()->create();
    $service = app(InventoryService::class);

    Asset::factory()->create([
        'product_id' => $product->id,
        'tag_code' => 'AST-DUP-0001',
    ]);

    expect(fn () => $service->receiveAssets(
        user: $user,
        product: $product,
        tagCodes: ['AST-DUP-0001', 'AST-DUP-0002'],
        notes: 'Duplicate asset load',
        ipAddress: '10.0.0.21',
    ))->toThrow(QueryException::class);

    expect(Asset::query()->where('tag_code', 'AST-DUP-0002')->exists())->toBeFalse();
    expect(StockMovement::query()->where('notes', 'Duplicate asset load')->count())->toBe(0);
});

test('issue requisition allocates by earliest expiry and updates status', function () {
    $requesterPosition = Position::factory()->create();
    $issuerPosition = Position::factory()->create();

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $issuer = User::factory()->assignedPosition($issuerPosition)->create();

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-FIFO-0001']);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 8,
    ]);

    $laterLot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 5,
        'qty_remaining' => 5,
        'received_at' => CarbonImmutable::parse('2026-01-05 08:00:00'),
        'expires_at' => '2026-12-31',
    ]);

    $earlierExpiryLot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 3,
        'qty_remaining' => 3,
        'received_at' => CarbonImmutable::parse('2026-01-01 08:00:00'),
        'expires_at' => '2026-06-30',
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Approved',
    ]);

    $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 4,
        'qty_issued' => 0,
    ]);

    $service = app(InventoryService::class);

    $service->issueRequisition(
        user: $issuer,
        requisition: $requisition,
        notes: 'Issued by service test',
        ipAddress: '10.0.0.30',
    );

    $earlierExpiryLot->refresh();
    $laterLot->refresh();
    $requisition->refresh();

    expect($earlierExpiryLot->qty_remaining)->toBe(0);
    expect($laterLot->qty_remaining)->toBe(4);
    expect($requisition->status)->toBe('Issued');
    expect($requisition->issued_by)->toBe($issuer->id);
    expect($requisition->issued_position_id)->toBe($issuerPosition->id);

    $movements = StockMovement::query()
        ->where('requisition_id', $requisition->id)
        ->where('movement_type', 'issue')
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2);
    expect($movements->sum('qty_delta'))->toBe(-4);
});

test('issue requisition rejects non consumable lines and rolls back without movements', function () {
    $requesterPosition = Position::factory()->create();
    $issuer = User::factory()->assignedPosition(Position::factory()->create())->create();

    $product = Product::factory()->asset()->create(['sku' => 'SKU-ASSET-0001']);
    $requisition = Requisition::factory()->create([
        'requester_id' => User::factory()->assignedPosition($requesterPosition)->create()->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Approved',
    ]);

    $line = $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 1,
        'qty_issued' => 0,
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->issueRequisition(
        user: $issuer,
        requisition: $requisition,
        notes: 'Attempted to issue an asset',
        ipAddress: '10.0.0.33',
    ))->toThrow(RuntimeException::class, 'Only consumable requisition lines can be issued here.');

    $requisition->refresh();
    $line->refresh();

    expect($requisition->status)->toBe('Approved');
    expect($line->qty_issued)->toBe(0);
    expect(StockMovement::query()->where('requisition_id', $requisition->id)->count())->toBe(0);
});

test('issue requisition fails on insufficient stock and rolls back changes', function () {
    $requesterPosition = Position::factory()->create();
    $issuerPosition = Position::factory()->create();

    $requester = User::factory()->assignedPosition($requesterPosition)->create();
    $issuer = User::factory()->assignedPosition($issuerPosition)->create();

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-LOW-0001']);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 1,
    ]);

    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 1,
        'qty_remaining' => 1,
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Approved',
    ]);

    $line = $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 2,
        'qty_issued' => 0,
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->issueRequisition(
        user: $issuer,
        requisition: $requisition,
        notes: 'Too much requested',
        ipAddress: '10.0.0.31',
    ))->toThrow(RuntimeException::class, 'Insufficient stock');

    $requisition->refresh();
    $line->refresh();
    $lot->refresh();

    expect($requisition->status)->toBe('Approved');
    expect($line->qty_issued)->toBe(0);
    expect($lot->qty_remaining)->toBe(1);
    expect(StockMovement::query()->where('requisition_id', $requisition->id)->count())->toBe(0);
});

test('issue requisition rejects non approved records before allocating stock', function () {
    $requesterPosition = Position::factory()->create();
    $issuer = User::factory()->assignedPosition(Position::factory()->create())->create();

    $requisition = Requisition::factory()->create([
        'requester_id' => User::factory()->assignedPosition($requesterPosition)->create()->id,
        'requester_position_id' => $requesterPosition->id,
        'status' => 'Submitted',
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->issueRequisition(
        user: $issuer,
        requisition: $requisition,
        notes: null,
        ipAddress: '10.0.0.32',
    ))->toThrow(RuntimeException::class, 'Only approved requisitions can be issued.');
});
