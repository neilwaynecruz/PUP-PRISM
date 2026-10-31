<?php

use App\Enums\AssetStatus;
use App\Enums\BookingStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Booking;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\ExpiringStockAlertNotification;
use App\Notifications\LowStockAlertNotification;
use App\Notifications\ProcurementRecommendationNotification;
use App\Notifications\RequisitionStatusChangedNotification;
use App\Notifications\RequisitionSubmittedNotification;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function createBulkTestUser(string $role, ?Position $position = null): User
{
    $position = $position ?? Position::factory()->create();
    $factory = User::factory()->assignedPosition($position);

    if (in_array($role, ['Admin', 'Supply Head'], true)) {
        $factory = $factory->withTwoFactor();
    }

    $user = $factory->create();
    $user->assignRole($role);

    return $user;
}

test('supply head and admin can bulk approve submitted requisitions while gracefully skipping non-submitted ones', function () {
    Notification::fake();

    $approver = createBulkTestUser('Supply Head');
    $requester = createBulkTestUser('Property Custodian');
    $product = Product::factory()->consumable()->create(['sku' => 'SKU-BULK-01', 'name' => 'Stapler']);

    $submitted1 = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Submitted,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $submitted1->id,
        'product_id' => $product->id,
        'qty_requested' => 5,
        'qty_issued' => 0,
    ]);

    $submitted2 = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Submitted,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $submitted2->id,
        'product_id' => $product->id,
        'qty_requested' => 3,
        'qty_issued' => 0,
    ]);

    $alreadyIssued = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Issued,
    ]);

    $draft = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Draft,
    ]);

    $response = $this->actingAs($approver)
        ->from(route('inventory.requisitions.index', absolute: false))
        ->post(route('inventory.requisitions.bulk-approve', absolute: false), [
            'ids' => [$submitted1->id, $submitted2->id, $alreadyIssued->id, $draft->id],
        ]);

    $response->assertRedirect(route('inventory.requisitions.index', absolute: false));
    $response->assertSessionHas('inertia.flash_data.toast', fn ($toast) => is_array($toast) && $toast['type'] === 'success' && str_contains($toast['message'], '2 requisitions approved') && str_contains($toast['message'], '2 skipped'));

    expect($submitted1->fresh()->status)->toBe(RequisitionStatus::Approved);
    expect($submitted2->fresh()->status)->toBe(RequisitionStatus::Approved);
    expect($alreadyIssued->fresh()->status)->toBe(RequisitionStatus::Issued);
    expect($draft->fresh()->status)->toBe(RequisitionStatus::Draft);

    Notification::assertSentTo(
        $requester,
        RequisitionStatusChangedNotification::class,
        fn ($n) => $n->action === 'approved',
    );
});

test('requester cannot approve their own requisition in bulk', function () {
    $adminRequester = createBulkTestUser('Admin');
    $otherRequester = createBulkTestUser('Property Custodian');
    $product = Product::factory()->consumable()->create();

    $ownRequisition = Requisition::factory()->create([
        'requester_id' => $adminRequester->id,
        'requester_position_id' => $adminRequester->position_id,
        'status' => RequisitionStatus::Submitted,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $ownRequisition->id,
        'product_id' => $product->id,
        'qty_requested' => 2,
    ]);

    $otherRequisition = Requisition::factory()->create([
        'requester_id' => $otherRequester->id,
        'requester_position_id' => $otherRequester->position_id,
        'status' => RequisitionStatus::Submitted,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $otherRequisition->id,
        'product_id' => $product->id,
        'qty_requested' => 4,
    ]);

    $response = $this->actingAs($adminRequester)
        ->post(route('inventory.requisitions.bulk-approve', absolute: false), [
            'ids' => [$ownRequisition->id, $otherRequisition->id],
        ]);

    $response->assertRedirect();
    expect($ownRequisition->fresh()->status)->toBe(RequisitionStatus::Submitted);
    expect($otherRequisition->fresh()->status)->toBe(RequisitionStatus::Approved);
});

test('unauthorized users cannot perform bulk approval or bulk issuance', function () {
    $custodian = createBulkTestUser('Property Custodian');

    $requisition = Requisition::factory()->create([
        'status' => RequisitionStatus::Submitted,
    ]);

    $this->actingAs($custodian)
        ->post(route('inventory.requisitions.bulk-approve', absolute: false), [
            'ids' => [$requisition->id],
        ])
        ->assertForbidden();

    $this->actingAs($custodian)
        ->post(route('inventory.requisitions.bulk-issue', absolute: false), [
            'ids' => [$requisition->id],
        ])
        ->assertForbidden();
});

test('bulk issue processes approved requisitions and skips non-issuable items', function () {
    Notification::fake();

    $issuer = createBulkTestUser('Supply Head');
    $requester = createBulkTestUser('Property Custodian');

    $product = Product::factory()->consumable()->create(['sku' => 'SKU-ISSUE-01', 'name' => 'Marker']);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'on_hand_qty' => 50,
    ]);
    StockLot::factory()->create([
        'product_id' => $product->id,
        'qty_remaining' => 50,
    ]);

    $approvedReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Approved,
    ]);
    RequisitionLine::factory()->create([
        'requisition_id' => $approvedReq->id,
        'product_id' => $product->id,
        'qty_requested' => 10,
        'qty_issued' => 0,
    ]);

    $submittedReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Submitted,
    ]);

    $issuedReq = Requisition::factory()->create([
        'requester_id' => $requester->id,
        'requester_position_id' => $requester->position_id,
        'status' => RequisitionStatus::Issued,
    ]);

    $response = $this->actingAs($issuer)
        ->from(route('inventory.requisitions.index', absolute: false))
        ->post(route('inventory.requisitions.bulk-issue', absolute: false), [
            'ids' => [$approvedReq->id, $submittedReq->id, $issuedReq->id],
        ]);

    $response->assertRedirect(route('inventory.requisitions.index', absolute: false));
    $response->assertSessionHas('inertia.flash_data.toast', fn ($toast) => is_array($toast) && $toast['type'] === 'success' && str_contains($toast['message'], '1 requisition issued') && str_contains($toast['message'], '2 skipped'));

    expect($approvedReq->fresh()->status)->toBe(RequisitionStatus::Issued);
    expect($submittedReq->fresh()->status)->toBe(RequisitionStatus::Submitted);
    expect($issuedReq->fresh()->status)->toBe(RequisitionStatus::Issued);

    Notification::assertSentTo(
        $requester,
        RequisitionStatusChangedNotification::class,
        fn ($n) => $n->action === 'issued',
    );
});

test('NotificationService delivers requisition submitted notifications to Admin and Supply Head', function () {
    Notification::fake();

    $admin = createBulkTestUser('Admin');
    $supplyHead = createBulkTestUser('Supply Head');
    $custodianRequester = createBulkTestUser('Property Custodian');

    $requisition = Requisition::factory()->create([
        'requester_id' => $custodianRequester->id,
        'status' => RequisitionStatus::Submitted,
    ]);

    app(NotificationService::class)->requisitionSubmitted($requisition);

    Notification::assertSentTo($admin, RequisitionSubmittedNotification::class);
    Notification::assertSentTo($supplyHead, RequisitionSubmittedNotification::class);
    Notification::assertNotSentTo($custodianRequester, RequisitionSubmittedNotification::class);
});

test('NotificationService delivers booking submitted notifications to Admin and Property Custodian', function () {
    Notification::fake();

    $admin = createBulkTestUser('Admin');
    $custodian = createBulkTestUser('Property Custodian');
    $facultyRequester = createBulkTestUser('Property Custodian');

    $product = Product::factory()->create(['type' => ProductType::Asset, 'is_active' => true]);
    $asset = Asset::factory()->create(['product_id' => $product->id, 'status' => AssetStatus::Available]);

    $booking = Booking::factory()->create([
        'asset_id' => $asset->id,
        'requester_id' => $facultyRequester->id,
        'status' => BookingStatus::Requested,
    ]);

    app(NotificationService::class)->bookingSubmitted($booking);

    Notification::assertSentTo($admin, BookingSubmittedNotification::class);
    Notification::assertSentTo($custodian, BookingSubmittedNotification::class);
});

test('NotificationService delivers low stock, expiring stock, and procurement recommendations to Admin and Supply Head', function () {
    Notification::fake();

    $admin = createBulkTestUser('Admin');
    $supplyHead = createBulkTestUser('Supply Head');
    $custodian = createBulkTestUser('Property Custodian');

    $product = Product::factory()->consumable()->create(['reorder_threshold' => 10]);
    $lot = StockLot::factory()->create([
        'product_id' => $product->id,
        'expires_at' => CarbonImmutable::now()->addDays(5)->toDateString(),
    ]);

    $service = app(NotificationService::class);

    $service->lowStockAlert($product, 3);
    Notification::assertSentTo($admin, LowStockAlertNotification::class);
    Notification::assertSentTo($supplyHead, LowStockAlertNotification::class);
    Notification::assertNotSentTo($custodian, LowStockAlertNotification::class);

    $service->expiringStockAlert($lot, 5);
    Notification::assertSentTo($admin, ExpiringStockAlertNotification::class);
    Notification::assertSentTo($supplyHead, ExpiringStockAlertNotification::class);
    Notification::assertNotSentTo($custodian, ExpiringStockAlertNotification::class);

    $service->procurementRecommendation($product, 7, 20);
    Notification::assertSentTo($admin, ProcurementRecommendationNotification::class);
    Notification::assertSentTo($supplyHead, ProcurementRecommendationNotification::class);
    Notification::assertNotSentTo($custodian, ProcurementRecommendationNotification::class);
});
