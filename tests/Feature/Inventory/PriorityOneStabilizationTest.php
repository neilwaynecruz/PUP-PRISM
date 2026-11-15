<?php

use App\Enums\AssetConditionStatus;
use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\InventoryCountReviewStatus;
use App\Enums\InventoryCountStatus;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Booking;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PhysicalCountService;
use App\Services\Procurement\PurchaseOrderNumberGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
    Role::findOrCreate('User');
});

function createPriorityOneUser(string $role = 'Property Custodian'): User
{
    $position = Position::factory()->create();
    $factory = User::factory()->assignedPosition($position);

    if (in_array($role, ['Admin', 'Supply Head', 'Property Custodian'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('postgresql purchase order numbering does not abort transaction when sequence row already exists', function () {
    try {
        $pdo = DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $exception) {
        Assert::markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    $originalConnection = DB::getDefaultConnection();
    DB::setDefaultConnection('pgsql_testing');

    $targetDate = CarbonImmutable::create(2099, 12, 31, 10, 0, 0);
    $datePrefix = $targetDate->format('Ymd');
    $poNumberPrefix = "PO-{$datePrefix}-";

    DB::connection('pgsql_testing')->table('purchase_order_sequences')
        ->where('date_prefix', $datePrefix)
        ->delete();

    try {
        $generator = app(PurchaseOrderNumberGenerator::class);

        $first = DB::connection('pgsql_testing')->transaction(function () use ($generator, $targetDate, $datePrefix): string {
            DB::connection('pgsql_testing')->table('purchase_order_sequences')->insert([
                'date_prefix' => $datePrefix,
                'last_sequence' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $generator->nextNumber($targetDate);
        });

        $second = $generator->nextNumber($targetDate);

        expect($first)->toBe("{$poNumberPrefix}0001")
            ->and($second)->toBe("{$poNumberPrefix}0002");
    } finally {
        DB::connection('pgsql_testing')->table('purchase_order_sequences')
            ->where('date_prefix', $datePrefix)
            ->delete();
        DB::setDefaultConnection($originalConnection);
    }
});

test('batch receiving rejects duplicate asset tag codes across lines with validation error', function () {
    $supplyHead = createPriorityOneUser('Supply Head');
    $firstProduct = Product::factory()->asset()->create(['sku' => 'AST-DUP-1']);
    $secondProduct = Product::factory()->asset()->create(['sku' => 'AST-DUP-2']);

    $csrf = 'batch-dup-tag-token';

    $response = $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.receiving.batch', absolute: false), [
            '_token' => $csrf,
            'lines' => [
                [
                    'sku' => $firstProduct->sku,
                    'tag_codes' => ['DUP-TAG-001'],
                    'serial_numbers' => ['SN-DUP-1'],
                ],
                [
                    'sku' => $secondProduct->sku,
                    'tag_codes' => ['DUP-TAG-001'],
                    'serial_numbers' => ['SN-DUP-2'],
                ],
            ],
        ]);

    $response->assertSessionHasErrors('lines.1.tag_codes.0');
    expect(Asset::query()->where('tag_code', 'DUP-TAG-001')->exists())->toBeFalse();
});

test('direct asset receiving rejects existing tag codes cleanly via validation', function () {
    $supplyHead = createPriorityOneUser('Supply Head');
    $product = Product::factory()->asset()->create(['sku' => 'AST-DIRECT-DUP']);
    Asset::factory()->create(['product_id' => $product->id, 'tag_code' => 'EXISTING-TAG-100']);

    $csrf = 'direct-dup-tag-token';

    $response = $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.receiving.store', absolute: false), [
            '_token' => $csrf,
            'sku' => $product->sku,
            'tag_codes' => ['EXISTING-TAG-100'],
        ]);

    $response->assertSessionHasErrors('tag_codes.0');
});

test('identical repeated partial po receipts without an explicit idempotency key both record stock', function () {
    Mail::fake();

    $supplyHead = createPriorityOneUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create(['sku' => 'PO-REPEAT-01']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 0]);

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Sent,
        'requested_by' => $supplyHead->id,
    ]);

    $line = PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 0,
        'unit_price' => 50,
        'subtotal' => 500,
    ]);

    $csrf = 'po-repeat-receive-token';
    $payload = [
        '_token' => $csrf,
        'lines' => [
            [
                'purchase_order_line_id' => $line->id,
                'qty_received' => 3,
            ],
        ],
    ];

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.purchase-orders.receive', $purchaseOrder, absolute: false), $payload)
        ->assertRedirect();

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.purchase-orders.receive', $purchaseOrder, absolute: false), $payload)
        ->assertRedirect();

    $line->refresh();
    $purchaseOrder->refresh();
    $stock = ProductStock::query()->where('product_id', $product->id)->firstOrFail();

    expect($line->qty_received)->toBe(6)
        ->and($purchaseOrder->status)->toBe(PurchaseOrderStatus::Partial)
        ->and($stock->on_hand_qty)->toBe(6);

    app(InventoryService::class)->receivePurchaseOrder(
        user: $supplyHead,
        purchaseOrder: $purchaseOrder,
        lines: [['purchase_order_line_id' => $line->id, 'qty_received' => 2]],
        idempotencyKey: 'explicit-po-key-123',
    );
    app(InventoryService::class)->receivePurchaseOrder(
        user: $supplyHead,
        purchaseOrder: $purchaseOrder->fresh(),
        lines: [['purchase_order_line_id' => $line->id, 'qty_received' => 2]],
        idempotencyKey: 'explicit-po-key-123',
    );

    expect($line->fresh()->qty_received)->toBe(8)
        ->and($stock->fresh()->on_hand_qty)->toBe(8);
});

test('approved and partially issued requisitions commit stock against competing requisitions', function () {
    $issuer = createPriorityOneUser('Supply Head');
    $requester = User::factory()->assignedPosition(Position::factory()->create())->create();

    $product = Product::factory()->consumable()->create(['sku' => 'COMMIT-CON-1']);
    ProductStock::factory()->create(['product_id' => $product->id, 'on_hand_qty' => 10]);
    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_received' => 10,
        'qty_remaining' => 10,
        'received_at' => CarbonImmutable::now()->subDay(),
    ]);

    $firstReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::PartiallyIssued,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $firstReq->id,
        'product_id' => $product->id,
        'qty_requested' => 8,
        'qty_issued' => 2,
    ]);

    $secondReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Approved,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $secondReq->id,
        'product_id' => $product->id,
        'qty_requested' => 5,
        'qty_issued' => 0,
    ]);

    $service = app(InventoryService::class);

    expect(fn () => $service->issueRequisition($issuer, $secondReq))
        ->toThrow(RuntimeException::class, 'Insufficient stock');

    // Soft-deleting the committed requisition frees its uncommitted balance.
    $firstReq->delete();

    $service->issueRequisition($issuer, $secondReq->fresh());
    expect($secondReq->fresh()->status)->toBe(RequisitionStatus::Issued);
});

test('partially received purchase orders cannot be cancelled and booking bulk approve aborts cleanly on unauthorized selection', function () {
    $supplyHead = createPriorityOneUser('Supply Head');
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->consumable()->create();

    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrderStatus::Partial,
        'requested_by' => $supplyHead->id,
    ]);
    PurchaseOrderLine::factory()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'product_id' => $product->id,
        'qty_ordered' => 10,
        'qty_received' => 4,
    ]);

    $csrf = 'po-cancel-partial-token';

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->put(route('inventory.purchase-orders.cancel', $purchaseOrder, absolute: false), [
            '_token' => $csrf,
            'reason' => 'Duplicate order attempt',
        ])
        ->assertRedirect();

    expect($purchaseOrder->fresh()->status)->toBe(PurchaseOrderStatus::Partial);

    $admin = createPriorityOneUser('Admin');
    $locationA = Location::factory()->create(['is_bookable' => true]);
    $locationB = Location::factory()->create(['is_bookable' => true]);
    $assetA = Asset::factory()->create(['current_location_id' => $locationA->id]);
    $assetB = Asset::factory()->create(['current_location_id' => $locationB->id]);

    $requestedBooking = Booking::factory()->create([
        'asset_id' => $assetA->id,
        'requester_id' => $supplyHead->id,
        'status' => BookingStatus::Requested,
    ]);
    $alreadyApproved = Booking::factory()->create([
        'asset_id' => $assetB->id,
        'requester_id' => $supplyHead->id,
        'status' => BookingStatus::Approved,
    ]);

    $bookingCsrf = 'booking-bulk-auth-token';

    $this->actingAs($admin)
        ->withSession(['_token' => $bookingCsrf])
        ->post(route('inventory.bookings.bulk-approve', absolute: false), [
            '_token' => $bookingCsrf,
            'ids' => [$requestedBooking->id, $alreadyApproved->id],
        ])
        ->assertForbidden();

    expect($requestedBooking->fresh()->status)->toBe(BookingStatus::Requested);
});

test('physical count approves tagged asset discrepancies and updates consumable lots respecting qty_received bounds', function () {
    $counter = createPriorityOneUser('Property Custodian');
    $approver = createPriorityOneUser('Supply Head');

    $assetProduct = Product::factory()->asset()->create(['sku' => 'CNT-AST-1']);
    $asset = Asset::factory()->create([
        'product_id' => $assetProduct->id,
        'status' => AssetStatus::Available,
        'condition_status' => AssetConditionStatus::Serviceable,
    ]);

    $consumableFit = Product::factory()->consumable()->create(['sku' => 'CNT-CON-FIT']);
    ProductStock::factory()->create(['product_id' => $consumableFit->id, 'on_hand_qty' => 6]);
    $lotFit = StockLot::factory()->create([
        'product_id' => $consumableFit->id,
        'qty_received' => 10,
        'qty_remaining' => 6,
        'received_at' => CarbonImmutable::now()->subDays(2),
    ]);

    $consumableOverflow = Product::factory()->consumable()->create(['sku' => 'CNT-CON-OVF']);
    ProductStock::factory()->create(['product_id' => $consumableOverflow->id, 'on_hand_qty' => 8]);
    $lotOverflow = StockLot::factory()->create([
        'product_id' => $consumableOverflow->id,
        'qty_received' => 10,
        'qty_remaining' => 8,
        'received_at' => CarbonImmutable::now()->subDays(2),
    ]);

    $session = InventoryCountSession::factory()->create([
        'created_by' => $approver->id,
        'assigned_to' => $counter->id,
        'status' => InventoryCountStatus::InProgress,
    ]);

    $assetLine = InventoryCountLine::factory()->create([
        'inventory_count_session_id' => $session->id,
        'product_id' => $assetProduct->id,
        'asset_id' => $asset->id,
        'stock_lot_id' => null,
        'expected_qty_snapshot' => 1,
        'counted_qty' => 0,
        'expected_qty_at_count' => 1,
        'variance_qty' => -1,
        'counted_by' => $counter->id,
        'counted_at' => now(),
        'review_status' => InventoryCountReviewStatus::Pending,
    ]);

    $fitLine = InventoryCountLine::factory()->create([
        'inventory_count_session_id' => $session->id,
        'product_id' => $consumableFit->id,
        'asset_id' => null,
        'stock_lot_id' => $lotFit->id,
        'expected_qty_snapshot' => 6,
        'counted_qty' => 9,
        'expected_qty_at_count' => 6,
        'variance_qty' => 3,
        'counted_by' => $counter->id,
        'counted_at' => now(),
        'review_status' => InventoryCountReviewStatus::Pending,
    ]);

    $overflowLine = InventoryCountLine::factory()->create([
        'inventory_count_session_id' => $session->id,
        'product_id' => $consumableOverflow->id,
        'asset_id' => null,
        'stock_lot_id' => $lotOverflow->id,
        'expected_qty_snapshot' => 8,
        'counted_qty' => 12,
        'expected_qty_at_count' => 8,
        'variance_qty' => 4,
        'counted_by' => $counter->id,
        'counted_at' => now(),
        'review_status' => InventoryCountReviewStatus::Pending,
    ]);

    $service = app(PhysicalCountService::class);
    $session = $service->submit($counter, $session);
    $service->reviewLine($approver, $session, $assetLine->fresh(), InventoryCountReviewStatus::Approved, 'Missing asset verified');
    $service->reviewLine($approver, $session->fresh(), $fitLine->fresh(), InventoryCountReviewStatus::Approved, 'Found 3 extra inside lot capacity');
    $service->reviewLine($approver, $session->fresh(), $overflowLine->fresh(), InventoryCountReviewStatus::Approved, 'Found 4 extra overflowing lot capacity');
    $service->approve($approver, $session->fresh());

    $asset->refresh();
    $lotFit->refresh();
    $lotOverflow->refresh();
    $fitStock = ProductStock::query()->where('product_id', $consumableFit->id)->firstOrFail();
    $overflowStock = ProductStock::query()->where('product_id', $consumableOverflow->id)->firstOrFail();
    $createdOverflowLot = StockLot::query()
        ->where('product_id', $consumableOverflow->id)
        ->whereKeyNot($lotOverflow->id)
        ->first();

    expect($asset->status)->toBe(AssetStatus::Unserviceable)
        ->and($asset->condition_status)->toBe(AssetConditionStatus::Damaged)
        ->and(StockMovement::query()->where('asset_id', $asset->id)->where('movement_type', 'cycle_count')->exists())->toBeTrue()
        ->and($lotFit->qty_remaining)->toBe(9)
        ->and(StockLot::query()->where('product_id', $consumableFit->id)->count())->toBe(1)
        ->and($fitStock->on_hand_qty)->toBe(9)
        ->and($lotOverflow->qty_remaining)->toBe(8)
        ->and($createdOverflowLot)->not->toBeNull()
        ->and($createdOverflowLot->qty_received)->toBe(4)
        ->and($createdOverflowLot->qty_remaining)->toBe(4)
        ->and($overflowStock->on_hand_qty)->toBe(12);
});

test('attachments allow re-uploading after soft delete and trash cleanup skips referenced products', function () {
    Storage::fake('local');

    $supplyHead = createPriorityOneUser('Supply Head');
    $asset = Asset::factory()->create();
    $product = Product::factory()->consumable()->create();

    $csrf = 'attachment-reupload-token';
    $firstFile = UploadedFile::fake()->createWithContent('spec.pdf', 'identical-binary-payload');

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.attachments.store', ['attachableType' => 'asset', 'attachableId' => $asset->id], false), [
            '_token' => $csrf,
            'title' => 'First Spec',
            'category' => 'other',
            'file' => $firstFile,
        ])
        ->assertRedirect();

    $firstAttachment = Attachment::query()->where('attachable_id', $asset->id)->firstOrFail();

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->delete(route('inventory.attachments.destroy', $firstAttachment, absolute: false), [
            '_token' => $csrf,
        ])
        ->assertRedirect();

    expect($firstAttachment->fresh()->trashed())->toBeTrue();

    $secondFile = UploadedFile::fake()->createWithContent('spec.pdf', 'identical-binary-payload');

    $this->actingAs($supplyHead)
        ->withSession(['_token' => $csrf])
        ->post(route('inventory.attachments.store', ['attachableType' => 'asset', 'attachableId' => $asset->id], false), [
            '_token' => $csrf,
            'title' => 'Reuploaded Spec',
            'category' => 'other',
            'file' => $secondFile,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Attachment::query()->where('attachable_id', $asset->id)->count())->toBe(1);

    StockMovement::factory()->create([
        'product_id' => $product->id,
        'performed_by' => $supplyHead->id,
    ]);
    $product->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();
    $firstAttachment->forceFill(['deleted_at' => now()->subDays(45)])->saveQuietly();

    $this->artisan('trash:cleanup', ['--days' => 30])->assertExitCode(0);

    expect(Product::withTrashed()->whereKey($product->id)->exists())->toBeTrue()
        ->and(Attachment::withTrashed()->whereKey($firstAttachment->id)->exists())->toBeFalse();
});

test('requisition trash id search works reliably and audit reports validate invalid filters', function () {
    $admin = createPriorityOneUser('Admin');
    $supplyHead = createPriorityOneUser('Supply Head');
    $requester = User::factory()->assignedPosition(Position::factory()->create())->create();

    $trashedReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
    ]);
    $trashedReq->delete();

    Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
    ]);

    $this->actingAs($supplyHead)
        ->get(route('inventory.requisitions.trash', ['search' => (string) $trashedReq->id], false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('inventory/requisitions/Trash')
            ->has('requisitions.data', 1)
            ->where('requisitions.data.0.id', $trashedReq->id)
        );

    $this->actingAs($admin)
        ->get(route('inventory.reports.movements', [
            'format' => 'csv',
            'date_from' => 'not-a-date',
            'performed_by' => 'abc',
        ], false))
        ->assertSessionHasErrors(['date_from', 'performed_by']);

    $this->actingAs($supplyHead)
        ->get(route('inventory.reports.requisitions', [
            'format' => 'csv',
            'date_to' => 'invalid',
        ], false))
        ->assertSessionHasErrors(['date_to']);
});
