<?php

namespace Database\Seeders;

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class UatInventorySeeder extends Seeder
{
    public function run(): void
    {
        $timeline = CarbonImmutable::today()->setTime(9, 0);
        $origins = [];

        foreach ([
            'Main Campus',
            'LGU',
            'Donation',
            'CHED',
            'Research Grant',
        ] as $name) {
            $origins[$name] = Origin::query()->firstOrCreate(['name' => $name]);
        }

        $categories = [];

        foreach ([
            'Cleaning Supplies',
            'Furniture and Fixtures',
            'IT Equipment',
            'Medical Supplies',
            'Office Equipment',
            'Office Supplies',
            'Protective Equipment',
        ] as $name) {
            $categories[$name] = Category::query()->firstOrCreate(['name' => $name]);
        }

        /** @var array<string, Position> $positions */
        $positions = Position::query()->get()->keyBy('code')->all();
        $supplyHead = User::query()->where('email', 'supply@local.test')->firstOrFail();

        $assetProducts = [
            [
                'sku' => 'AST-LAP-001',
                'name' => 'Faculty Laptop',
                'category' => 'IT Equipment',
                'origin' => 'Main Campus',
                'assets' => [
                    ['tag_code' => 'AST-COE-0001', 'position' => 'POS-COE-PC', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-COED-0001', 'position' => 'POS-COED-PC', 'status' => AssetStatus::CheckedOut],
                    ['tag_code' => 'AST-ADMIN-0002', 'position' => 'POS-ADMIN-PC', 'status' => AssetStatus::Condemned],
                ],
            ],
            [
                'sku' => 'AST-PROJ-001',
                'name' => 'LCD Projector',
                'category' => 'IT Equipment',
                'origin' => 'Donation',
                'assets' => [
                    ['tag_code' => 'AST-DIR-0001', 'position' => 'POS-DIR-PC', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-STU-0001', 'position' => 'POS-STUDENT-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-PRN-001',
                'name' => 'Network Printer',
                'category' => 'Office Equipment',
                'origin' => 'LGU',
                'assets' => [
                    ['tag_code' => 'AST-REG-0001', 'position' => 'POS-REG-PC', 'status' => AssetStatus::CheckedOut],
                    ['tag_code' => 'AST-LIB-0001', 'position' => 'POS-LIB-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-TAB-001',
                'name' => 'Tablet Device',
                'category' => 'IT Equipment',
                'origin' => 'CHED',
                'assets' => [
                    ['tag_code' => 'AST-SPMO-0001', 'position' => 'POS-PC-CHIEF', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-ADMIN-0001', 'position' => 'POS-ADMIN-PC', 'status' => AssetStatus::Unserviceable],
                ],
            ],
            [
                'sku' => 'AST-CAB-001',
                'name' => 'Metal Filing Cabinet',
                'category' => 'Furniture and Fixtures',
                'origin' => 'Research Grant',
                'assets' => [
                    ['tag_code' => 'AST-BACC-0001', 'position' => 'POS-BACC-PC', 'status' => AssetStatus::Available],
                ],
            ],
        ];

        foreach ($assetProducts as $definition) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories[$definition['category']]->id,
                    'origin_id' => $origins[$definition['origin']]->id,
                    'type' => ProductType::Asset,
                    'reorder_threshold' => 0,
                    'is_active' => true,
                ],
            );

            foreach ($definition['assets'] as $assetDefinition) {
                Asset::query()->updateOrCreate(
                    ['tag_code' => $assetDefinition['tag_code']],
                    [
                        'product_id' => $product->id,
                        'position_id' => $positions[$assetDefinition['position']]?->id,
                        'status' => $assetDefinition['status'],
                    ],
                );
            }
        }

        $consumables = [
            [
                'sku' => 'CON-PAPER-A4',
                'name' => 'Bond Paper A4',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 90,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-PAPER-001', 'received_at' => $timeline->subMonths(5)->subDays(4), 'qty_received' => 120, 'qty_remaining' => 60, 'expires_at' => null],
                    ['reference_no' => 'UAT-PAPER-002', 'received_at' => $timeline->subMonths(3)->subDays(6), 'qty_received' => 140, 'qty_remaining' => 70, 'expires_at' => null],
                    ['reference_no' => 'UAT-PAPER-003', 'received_at' => $timeline->subMonths(1)->subDays(10), 'qty_received' => 90, 'qty_remaining' => 50, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-INK-BLK',
                'name' => 'Printer Ink Black',
                'category' => 'Office Supplies',
                'origin' => 'LGU',
                'reorder_threshold' => 18,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-INK-001', 'received_at' => $timeline->subMonths(4)->subDays(3), 'qty_received' => 20, 'qty_remaining' => 8, 'expires_at' => null],
                    ['reference_no' => 'UAT-INK-002', 'received_at' => $timeline->subMonths(2)->subDays(9), 'qty_received' => 20, 'qty_remaining' => 10, 'expires_at' => null],
                    ['reference_no' => 'UAT-INK-003', 'received_at' => $timeline->subDays(24), 'qty_received' => 12, 'qty_remaining' => 6, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-ALCOHOL-70',
                'name' => 'Isopropyl Alcohol 70%',
                'category' => 'Medical Supplies',
                'origin' => 'Donation',
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-ALC-001', 'received_at' => $timeline->subMonths(6)->subDays(1), 'qty_received' => 36, 'qty_remaining' => 18, 'expires_at' => $timeline->addMonths(8)->toDateString()],
                    ['reference_no' => 'UAT-ALC-002', 'received_at' => $timeline->subMonths(3)->subDays(12), 'qty_received' => 30, 'qty_remaining' => 20, 'expires_at' => $timeline->addMonths(10)->toDateString()],
                    ['reference_no' => 'UAT-ALC-003', 'received_at' => $timeline->subDays(35), 'qty_received' => 30, 'qty_remaining' => 22, 'expires_at' => $timeline->addMonths(12)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-BAT-AA',
                'name' => 'Alkaline Battery AA',
                'category' => 'Office Supplies',
                'origin' => 'CHED',
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-BAT-001', 'received_at' => $timeline->subMonths(5), 'qty_received' => 20, 'qty_remaining' => 8, 'expires_at' => $timeline->addMonths(14)->toDateString()],
                    ['reference_no' => 'UAT-BAT-002', 'received_at' => $timeline->subMonths(2)->subDays(16), 'qty_received' => 25, 'qty_remaining' => 16, 'expires_at' => $timeline->addMonths(16)->toDateString()],
                    ['reference_no' => 'UAT-BAT-003', 'received_at' => $timeline->subDays(18), 'qty_received' => 20, 'qty_remaining' => 15, 'expires_at' => $timeline->addMonths(18)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-DISINF-01',
                'name' => 'Surface Disinfectant',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 10,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-DIS-001', 'received_at' => $timeline->subMonths(2)->subDays(20), 'qty_received' => 14, 'qty_remaining' => 3, 'expires_at' => $timeline->addDays(4)->toDateString()],
                    ['reference_no' => 'UAT-DIS-002', 'received_at' => $timeline->subDays(28), 'qty_received' => 16, 'qty_remaining' => 5, 'expires_at' => $timeline->addDays(40)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-GLOVES-M',
                'name' => 'Nitrile Gloves Medium',
                'category' => 'Protective Equipment',
                'origin' => 'Donation',
                'reorder_threshold' => 12,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'UAT-GLV-001', 'received_at' => $timeline->subDays(20), 'qty_received' => 18, 'qty_remaining' => 6, 'expires_at' => $timeline->addDays(6)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-FILEBOX-01',
                'name' => 'Archive File Box',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 10,
                'is_active' => false,
                'lots' => [
                    ['reference_no' => 'UAT-FILE-001', 'received_at' => $timeline->subMonths(4)->subDays(8), 'qty_received' => 40, 'qty_remaining' => 40, 'expires_at' => null],
                ],
            ],
        ];

        foreach ($consumables as $definition) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories[$definition['category']]->id,
                    'origin_id' => $origins[$definition['origin']]->id,
                    'type' => ProductType::Consumable,
                    'reorder_threshold' => $definition['reorder_threshold'],
                    'is_active' => $definition['is_active'],
                ],
            );

            $stockQty = collect($definition['lots'])->sum('qty_remaining');

            ProductStock::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['on_hand_qty' => $stockQty],
            );

            $runningReceivedQty = 0;

            foreach ($definition['lots'] as $lotDefinition) {
                $lot = StockLot::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'reference_no' => $lotDefinition['reference_no'],
                    ],
                    [
                        'received_at' => $lotDefinition['received_at'],
                        'expires_at' => $lotDefinition['expires_at'],
                        'qty_received' => $lotDefinition['qty_received'],
                        'qty_remaining' => $lotDefinition['qty_remaining'],
                    ],
                );

                $this->syncReceiveMovement(
                    product: $product,
                    lot: $lot,
                    performedBy: $supplyHead,
                    beforeQty: $runningReceivedQty,
                    notes: "[UAT] Received stock for {$product->sku} via {$lot->reference_no}",
                );

                $runningReceivedQty += $lot->qty_received;
            }
        }

        $deletedProduct = Product::withTrashed()->firstOrNew(['sku' => 'CON-MARKER-BLK']);
        $deletedProduct->fill([
            'name' => 'Permanent Marker Black',
            'category_id' => $categories['Office Supplies']->id,
            'origin_id' => $origins['LGU']->id,
            'type' => ProductType::Consumable,
            'reorder_threshold' => 8,
            'is_active' => false,
        ]);
        $deletedProduct->save();

        ProductStock::query()->updateOrCreate(
            ['product_id' => $deletedProduct->id],
            ['on_hand_qty' => 14],
        );

        $deletedLot = StockLot::query()->updateOrCreate(
            [
                'product_id' => $deletedProduct->id,
                'reference_no' => 'UAT-MARKER-001',
            ],
            [
                'received_at' => $timeline->subMonths(3)->subDays(2),
                'expires_at' => null,
                'qty_received' => 14,
                'qty_remaining' => 14,
            ],
        );

        $this->syncReceiveMovement(
            product: $deletedProduct,
            lot: $deletedLot,
            performedBy: $supplyHead,
            beforeQty: 0,
            notes: "[UAT] Received stock for {$deletedProduct->sku} via {$deletedLot->reference_no}",
        );

        if (! $deletedProduct->trashed()) {
            $deletedProduct->delete();
        }

        Product::withTrashed()->whereKey($deletedProduct->id)->update([
            'deleted_at' => $timeline->subDays(2),
            'deleted_by' => $supplyHead->id,
            'deletion_reason' => 'Retired duplicate SKU retained only for historical reporting.',
        ]);
    }

    private function syncReceiveMovement(
        Product $product,
        StockLot $lot,
        User $performedBy,
        int $beforeQty,
        string $notes,
    ): void {
        $qtyReceived = (int) $lot->qty_received;

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'receive',
                'product_id' => $product->id,
                'stock_lot_id' => $lot->id,
                'performed_at' => $lot->received_at,
            ],
            [
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => $qtyReceived,
                'qty_before' => $beforeQty,
                'qty_after' => $beforeQty + $qtyReceived,
                'performed_by' => $performedBy->id,
                'accountable_position_id' => $performedBy->position_id,
                'ip_address' => '10.250.1.10',
                'notes' => $notes,
            ],
        );
    }
}
