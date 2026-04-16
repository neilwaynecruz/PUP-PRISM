<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Origin;
use App\Models\Position;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockLot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class UatInventorySeeder extends Seeder
{
    public function run(): void
    {
        $origins = [];

        foreach ([
            'Main Campus',
            'LGU',
            'Donation',
            'CHED',
        ] as $name) {
            $origins[$name] = Origin::query()->firstOrCreate(['name' => $name]);
        }

        $categories = [];

        foreach ([
            'Cleaning Supplies',
            'IT Equipment',
            'Medical Supplies',
            'Office Equipment',
            'Office Supplies',
        ] as $name) {
            $categories[$name] = Category::query()->firstOrCreate(['name' => $name]);
        }

        /** @var array<string, Position> $positions */
        $positions = Position::query()->get()->keyBy('code')->all();

        $assetProducts = [
            [
                'sku' => 'AST-LAP-001',
                'name' => 'Faculty Laptop',
                'category' => 'IT Equipment',
                'origin' => 'Main Campus',
                'assets' => [
                    ['tag_code' => 'AST-COE-0001', 'position' => 'POS-COE-PC', 'status' => 'Available'],
                    ['tag_code' => 'AST-COED-0001', 'position' => 'POS-COED-PC', 'status' => 'Checked_Out'],
                ],
            ],
            [
                'sku' => 'AST-PROJ-001',
                'name' => 'LCD Projector',
                'category' => 'IT Equipment',
                'origin' => 'Donation',
                'assets' => [
                    ['tag_code' => 'AST-DIR-0001', 'position' => 'POS-DIR-PC', 'status' => 'Available'],
                    ['tag_code' => 'AST-STU-0001', 'position' => 'POS-STUDENT-PC', 'status' => 'Available'],
                ],
            ],
            [
                'sku' => 'AST-PRN-001',
                'name' => 'Network Printer',
                'category' => 'Office Equipment',
                'origin' => 'LGU',
                'assets' => [
                    ['tag_code' => 'AST-REG-0001', 'position' => 'POS-REG-PC', 'status' => 'Checked_Out'],
                    ['tag_code' => 'AST-LIB-0001', 'position' => 'POS-LIB-PC', 'status' => 'Available'],
                ],
            ],
            [
                'sku' => 'AST-TAB-001',
                'name' => 'Tablet Device',
                'category' => 'IT Equipment',
                'origin' => 'CHED',
                'assets' => [
                    ['tag_code' => 'AST-SPMO-0001', 'position' => 'POS-PC-CHIEF', 'status' => 'Available'],
                    ['tag_code' => 'AST-ADMIN-0001', 'position' => 'POS-ADMIN-PC', 'status' => 'Unserviceable'],
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
                    'type' => 'asset',
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

        $receivedAt = CarbonImmutable::now()->subDays(20);

        $consumables = [
            [
                'sku' => 'CON-PAPER-A4',
                'name' => 'Bond Paper A4',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 30,
                'on_hand_qty' => 180,
                'lots' => [
                    ['reference_no' => 'UAT-PAPER-001', 'qty_received' => 100, 'qty_remaining' => 100, 'expires_at' => null],
                    ['reference_no' => 'UAT-PAPER-002', 'qty_received' => 80, 'qty_remaining' => 80, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-INK-BLK',
                'name' => 'Printer Ink Black',
                'category' => 'Office Supplies',
                'origin' => 'LGU',
                'reorder_threshold' => 12,
                'on_hand_qty' => 24,
                'lots' => [
                    ['reference_no' => 'UAT-INK-001', 'qty_received' => 12, 'qty_remaining' => 12, 'expires_at' => null],
                    ['reference_no' => 'UAT-INK-002', 'qty_received' => 12, 'qty_remaining' => 12, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-ALCOHOL-70',
                'name' => 'Isopropyl Alcohol 70%',
                'category' => 'Medical Supplies',
                'origin' => 'Donation',
                'reorder_threshold' => 20,
                'on_hand_qty' => 60,
                'lots' => [
                    ['reference_no' => 'UAT-ALC-001', 'qty_received' => 30, 'qty_remaining' => 30, 'expires_at' => $receivedAt->addMonths(8)->toDateString()],
                    ['reference_no' => 'UAT-ALC-002', 'qty_received' => 30, 'qty_remaining' => 30, 'expires_at' => $receivedAt->addMonths(10)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-BAT-AA',
                'name' => 'Alkaline Battery AA',
                'category' => 'Office Supplies',
                'origin' => 'CHED',
                'reorder_threshold' => 15,
                'on_hand_qty' => 45,
                'lots' => [
                    ['reference_no' => 'UAT-BAT-001', 'qty_received' => 20, 'qty_remaining' => 20, 'expires_at' => $receivedAt->addMonths(14)->toDateString()],
                    ['reference_no' => 'UAT-BAT-002', 'qty_received' => 25, 'qty_remaining' => 25, 'expires_at' => $receivedAt->addMonths(18)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-DISINF-01',
                'name' => 'Surface Disinfectant',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 10,
                'on_hand_qty' => 18,
                'lots' => [
                    ['reference_no' => 'UAT-DIS-001', 'qty_received' => 18, 'qty_remaining' => 18, 'expires_at' => $receivedAt->addMonths(6)->toDateString()],
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
                    'type' => 'consumable',
                    'reorder_threshold' => $definition['reorder_threshold'],
                    'is_active' => true,
                ],
            );

            ProductStock::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['on_hand_qty' => $definition['on_hand_qty'], 'reserved_qty' => 0],
            );

            foreach ($definition['lots'] as $index => $lotDefinition) {
                StockLot::query()->updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'reference_no' => $lotDefinition['reference_no'],
                    ],
                    [
                        'received_at' => $receivedAt->addDays($index * 2),
                        'expires_at' => $lotDefinition['expires_at'],
                        'qty_received' => $lotDefinition['qty_received'],
                        'qty_remaining' => $lotDefinition['qty_remaining'],
                    ],
                );
            }
        }
    }
}
