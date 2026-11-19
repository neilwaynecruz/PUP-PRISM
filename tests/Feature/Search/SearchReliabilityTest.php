<?php

use App\Enums\AssetStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Asset;
use App\Models\Position;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\LowStockAlertNotification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();

    Role::findOrCreate('Admin');
    Role::findOrCreate('Supply Head');
    Role::findOrCreate('Property Custodian');
});

function reliabilityUser(string $role): User
{
    $user = User::factory()->withTwoFactor()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

test('global search finds purchase orders by PO number and supplier name', function () {
    $supplier = Supplier::factory()->create(['name' => 'Northwind Office Trading']);
    $purchaseOrder = PurchaseOrder::factory()->create([
        'supplier_id' => $supplier->id,
        'po_number' => 'PO-2026-0042',
        'status' => PurchaseOrderStatus::Draft,
    ]);

    $this->actingAs(reliabilityUser('Supply Head'))
        ->getJson(route('search', ['q' => 'po-2026-0042'], absolute: false))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'purchase_order',
            'id' => $purchaseOrder->id,
            'title' => 'PO-2026-0042',
            'url' => route('inventory.purchase-orders.show', $purchaseOrder, absolute: false),
        ]);

    $this->actingAs(reliabilityUser('Supply Head'))
        ->getJson(route('search', ['q' => 'northwind'], absolute: false))
        ->assertOk()
        ->assertJsonFragment(['type' => 'purchase_order', 'id' => $purchaseOrder->id]);
});

test('global search sends asset results to the asset record', function () {
    $asset = Asset::factory()->assignedToPosition(Position::factory()->create())->create([
        'product_id' => Product::factory()->asset()->create(['name' => 'Document Camera'])->id,
        'tag_code' => 'AST-SEARCH-7781',
        'status' => AssetStatus::Available,
    ]);

    $this->actingAs(reliabilityUser('Property Custodian'))
        ->getJson(route('search', ['q' => 'ast-search-7781'], absolute: false))
        ->assertOk()
        ->assertJsonFragment([
            'type' => 'asset',
            'id' => $asset->id,
            'url' => route('inventory.assets.show', $asset, absolute: false),
        ]);
});

test('global search accepts record numbers written with a hash sign', function () {
    $requisition = Requisition::factory()->create();

    $this->actingAs(reliabilityUser('Admin'))
        ->getJson(route('search', ['q' => "#{$requisition->id}"], absolute: false))
        ->assertOk()
        ->assertJsonFragment(['type' => 'requisition', 'id' => $requisition->id]);
});

test('global search trims surrounding spaces', function () {
    Product::factory()->consumable()->create(['name' => 'Trimmed Stapler', 'sku' => 'SKU-TRIM-1']);

    $this->actingAs(reliabilityUser('Admin'))
        ->getJson(route('search', ['q' => '   Trimmed Stapler   '], absolute: false))
        ->assertOk()
        ->assertJsonFragment(['title' => 'Trimmed Stapler']);
});

test('notification history search and category filters return matching notifications', function () {
    $user = reliabilityUser('Supply Head');
    $stapler = Product::factory()->create(['sku' => 'SKU-STAPLER-01', 'reorder_threshold' => 10]);
    $paper = Product::factory()->create(['sku' => 'SKU-PAPER-02', 'reorder_threshold' => 10]);
    $user->notify(new LowStockAlertNotification($stapler, 1));
    $user->notify(new LowStockAlertNotification($paper, 2));

    $category = $user->notifications()->firstOrFail()->data['category'];

    $this->actingAs($user)
        ->get(route('notifications.index', ['search' => 'sku-stapler'], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 1));

    $this->actingAs($user)
        ->get(route('notifications.index', ['category' => $category], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2));

    $this->actingAs($user)
        ->get(route('notifications.index', ['date_from' => 'not-a-date'], absolute: false))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('notifications.data', 2));
});

test('notification search and category filters work on postgresql', function () {
    withPostgresTestingDatabase(function (): void {
        Role::findOrCreate('Supply Head');
        $user = reliabilityUser('Supply Head');
        $product = Product::factory()->create(['sku' => 'SKU-LAMINATE-'.strtoupper(bin2hex(random_bytes(3))), 'reorder_threshold' => 10]);
        $user->notifyNow(new LowStockAlertNotification($product, 1));
        $category = $user->notifications()->firstOrFail()->data['category'];

        $this->actingAs($user)
            ->get(route('notifications.index', ['search' => 'sku-laminate', 'category' => $category], absolute: false))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('notifications.data', 1));
    });
});

test('procurement exports list the PO number rather than the internal id', function () {
    $purchaseOrder = PurchaseOrder::factory()->create(['po_number' => 'PO-2026-0777']);

    $response = $this->actingAs(reliabilityUser('Supply Head'))
        ->get(route('inventory.reports.procurement', ['format' => 'csv'], absolute: false))
        ->assertOk();

    expect($response->streamedContent())->toContain('PO-2026-0777')
        ->and($purchaseOrder->po_number)->toBe('PO-2026-0777');
});
