<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Asset;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Database\UniqueConstraintViolationException;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutVite();
    Role::findOrCreate('Admin');
    $user = User::factory()->withTwoFactor()->create();
    $user->assignRole('Admin');
    $this->actingAs($user);
});

test('reviewed manufacturer serials stay paired with tags through each receiving mode', function (string $mode) {
    $product = Product::factory()->asset()->create();
    $line = ['tag_codes' => ['OCR-TAG-A', 'OCR-TAG-B', 'OCR-TAG-C'], 'serial_numbers' => ['SERIAL-O0-I1', null, null]];

    if ($mode === 'po') {
        $po = PurchaseOrder::factory()->create(['status' => PurchaseOrderStatus::Sent]);
        $poLine = PurchaseOrderLine::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'qty_ordered' => 4, 'qty_received' => 0]);
        $response = $this->post(route('inventory.purchase-orders.receive', $po), ['lines' => [[...$line, 'purchase_order_line_id' => $poLine->id]]]);
    } elseif ($mode === 'batch') {
        $response = $this->post(route('inventory.receiving.batch'), ['lines' => [[...$line, 'sku' => $product->sku]]]);
    } else {
        $response = $this->post(route('inventory.receiving.store'), [...$line, 'sku' => $product->sku]);
    }

    $response->assertRedirect()->assertSessionHasNoErrors();
    expect(Asset::query()->where('tag_code', 'OCR-TAG-A')->firstOrFail()->serial_number)->toBe('SERIAL-O0-I1');
    expect(Asset::query()->where('tag_code', 'OCR-TAG-B')->firstOrFail()->serial_number)->toBeNull();
    expect(Asset::query()->where('product_id', $product->id)->count())->toBe(3);
    expect(StockMovement::query()->where('product_id', $product->id)->count())->toBe(3);
    if (isset($poLine)) {
        expect($poLine->fresh()->qty_received)->toBe(3);
        expect($po->fresh()->status)->toBe(PurchaseOrderStatus::Partial);
    }
})->with(['single', 'batch', 'po']);

test('invalid serial suggestions do not create assets or receipt movements', function (array $serials, bool $existing) {
    $product = Product::factory()->asset()->create();
    if ($existing) {
        Asset::factory()->create(['serial_number' => 'SN-EXISTS']);
    }
    $before = Asset::query()->count();
    $response = $this->post(route('inventory.receiving.store'), [
        'sku' => $product->sku,
        'tag_codes' => ['OCR-NEW-A', 'OCR-NEW-B'],
        'serial_numbers' => $serials,
    ]);
    $response->assertSessionHasErrors();
    expect(Asset::query()->count())->toBe($before);
    expect(StockMovement::query()->where('product_id', $product->id)->count())->toBe(0);
})->with([
    'duplicate within receipt' => [['SN-DUP', 'SN-DUP'], false],
    'already registered' => [['SN-EXISTS', null], true],
    'wrong mapping length' => [['SN-ONE'], false],
    'serial too long' => [[str_repeat('A', 121), null], false],
]);

test('postgresql retains reviewed serials and its unique constraint rolls back duplicate receipts', function () {
    withPostgresTestingDatabase(function (): void {
        $product = Product::factory()->asset()->create();
        $user = User::factory()->create();
        $inventory = app(InventoryService::class);
        $inventory->receive($user, $product, ['tag_codes' => ['OCR-PG-TAG-1'], 'serial_numbers' => ['OCR-PG-SERIAL-1']]);

        expect(Asset::query()->where('tag_code', 'OCR-PG-TAG-1')->firstOrFail()->serial_number)->toBe('OCR-PG-SERIAL-1');
        expect(fn () => $inventory->receive($user, $product, ['tag_codes' => ['OCR-PG-TAG-2'], 'serial_numbers' => ['OCR-PG-SERIAL-1']]))
            ->toThrow(UniqueConstraintViolationException::class);
        expect(Asset::query()->where('tag_code', 'OCR-PG-TAG-2')->exists())->toBeFalse();
        expect(StockMovement::query()->where('product_id', $product->id)->count())->toBe(1);
    });
});

test('serials duplicated across batch products are rejected before any receipt is posted', function () {
    $products = Product::factory()->asset()->count(2)->create();
    $this->post(route('inventory.receiving.batch'), ['lines' => $products->map(fn (Product $product, int $index): array => [
        'sku' => $product->sku,
        'tag_codes' => ['OCR-BATCH-'.$index],
        'serial_numbers' => ['OCR-SHARED-SERIAL'],
    ])->all()])->assertSessionHasErrors();

    expect(Asset::query()->count())->toBe(0);
    expect(StockMovement::query()->count())->toBe(0);
});
