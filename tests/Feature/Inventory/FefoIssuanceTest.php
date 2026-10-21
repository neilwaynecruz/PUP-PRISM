<?php

use App\Enums\RequisitionStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Carbon\CarbonImmutable;
use Spatie\Permission\Models\Role;

/**
 * @return array{
 *     position: Position,
 *     supplyHead: User,
 *     requester: User,
 *     inventoryService: InventoryService
 * }
 */
$createIssuanceContext = static function (): array {
    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');

    $department = Department::factory()->create();
    $position = Position::factory()->create(['department_id' => $department->id]);

    $supplyHead = User::factory()->assignedPosition($position)->create();
    $supplyHead->assignRole('Supply Head');

    return [
        'position' => $position,
        'supplyHead' => $supplyHead,
        'requester' => User::factory()->assignedPosition($position)->create(),
        'inventoryService' => app(InventoryService::class),
    ];
};

test('requisition issuance strictly consumes lots in First Expired First Out (FEFO) order', function () use ($createIssuanceContext) {
    $context = $createIssuanceContext();
    $position = $context['position'];
    $supplyHead = $context['supplyHead'];
    $requester = $context['requester'];
    $inventoryService = $context['inventoryService'];

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-FEFO-001']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 30]);

    $today = CarbonImmutable::now();

    // Create 3 lots with different expiration dates:
    // Lot A expires in 10 days (should be issued FIRST)
    $lotA = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-EXP-10',
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => $today->subDays(5),
        'expires_at' => $today->addDays(10)->toDateString(),
    ]);

    // Lot B expires in 30 days (should be issued SECOND)
    $lotB = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-EXP-30',
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => $today->subDays(10), // Received earlier, but expires later!
        'expires_at' => $today->addDays(30)->toDateString(),
    ]);

    // Lot C has NO expiration date (should be issued LAST)
    $lotC = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-NO-EXP',
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => $today->subDays(20), // Received even earlier
        'expires_at' => null,
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $position->id,
        'status' => RequisitionStatus::Approved,
        'approved_at' => $today,
        'approver_id' => $supplyHead->id,
        'approver_position_id' => $position->id,
    ]);

    // Requisition asks for 15 units
    $line = $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 15,
        'qty_issued' => 0,
    ]);

    $inventoryService->issueRequisition(
        user: $supplyHead,
        requisition: $requisition,
        linePayloads: [['id' => $line->id, 'qty_to_issue' => 15]],
    );

    // Lot A (earliest expiry) should be completely exhausted (10 consumed, 0 remaining)
    $lotA->refresh();
    expect($lotA->qty_remaining)->toBe(0);

    // Lot B (second expiry) should have 5 consumed (5 remaining)
    $lotB->refresh();
    expect($lotB->qty_remaining)->toBe(5);

    // Lot C (no expiry) should be untouched (10 remaining)
    $lotC->refresh();
    expect($lotC->qty_remaining)->toBe(10);

    // Check movements recorded in exact order
    $movements = StockMovement::query()
        ->where('product_id', $product->id)
        ->where('movement_type', 'issue')
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(2);
    expect($movements[0]->stock_lot_id)->toBe($lotA->id);
    expect($movements[0]->qty_delta)->toBe(-10);
    expect($movements[1]->stock_lot_id)->toBe($lotB->id);
    expect($movements[1]->qty_delta)->toBe(-5);
});

test('requisition issuance prevents issuing expired lots to university personnel', function () use ($createIssuanceContext) {
    $context = $createIssuanceContext();
    $position = $context['position'];
    $supplyHead = $context['supplyHead'];
    $requester = $context['requester'];
    $inventoryService = $context['inventoryService'];

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-FEFO-EXPIRED']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 15]);

    $today = CarbonImmutable::now();

    // Lot Expired: 10 units expired 3 days ago
    $expiredLot = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-ALREADY-EXPIRED',
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => $today->subDays(100),
        'expires_at' => $today->subDays(3)->toDateString(),
    ]);

    // Lot Valid: only 5 units unexpired
    $validLot = StockLot::factory()->create([
        'product_id' => $product->id,
        'reference_no' => 'LOT-VALID',
        'qty_received' => 5,
        'qty_remaining' => 5,
        'received_at' => $today->subDays(10),
        'expires_at' => $today->addDays(60)->toDateString(),
    ]);

    $requisition = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $position->id,
        'status' => RequisitionStatus::Approved,
        'approved_at' => $today,
        'approver_id' => $supplyHead->id,
        'approver_position_id' => $position->id,
    ]);

    // Requesting 8 units: while total on_hand is 15, unexpired is only 5!
    $line = $requisition->lines()->create([
        'product_id' => $product->id,
        'qty_requested' => 8,
        'qty_issued' => 0,
    ]);

    expect(function () use ($inventoryService, $supplyHead, $requisition, $line) {
        $inventoryService->issueRequisition(
            user: $supplyHead,
            requisition: $requisition,
            linePayloads: [['id' => $line->id, 'qty_to_issue' => 8]],
        );
    })->toThrow(RuntimeException::class, 'Expired lots cannot be issued');

    // Both lots remain untouched
    $expiredLot->refresh();
    $validLot->refresh();

    expect($expiredLot->qty_remaining)->toBe(10);
    expect($validLot->qty_remaining)->toBe(5);
});
