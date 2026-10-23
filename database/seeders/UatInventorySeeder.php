<?php

namespace Database\Seeders;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Enums\StockMovementReasonCode;
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
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

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
            'Electrical Supplies',
            'Furniture and Fixtures',
            'IT Equipment',
            'Laboratory Supplies',
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
                'name' => 'Business Laptop, 14-inch, 16 GB RAM, 512 GB SSD',
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
                'name' => 'LCD Projector, 4,000 ANSI Lumens, Full HD',
                'category' => 'IT Equipment',
                'origin' => 'Donation',
                'assets' => [
                    ['tag_code' => 'AST-DIR-0001', 'position' => 'POS-DIR-PC', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-STU-0001', 'position' => 'POS-STUDENT-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-PRN-001',
                'name' => 'Monochrome Network Laser Printer with Duplex Printing',
                'category' => 'Office Equipment',
                'origin' => 'LGU',
                'assets' => [
                    ['tag_code' => 'AST-REG-0001', 'position' => 'POS-REG-PC', 'status' => AssetStatus::CheckedOut],
                    ['tag_code' => 'AST-LIB-0001', 'position' => 'POS-LIB-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-TAB-001',
                'name' => '10.9-inch Tablet, 128 GB, Wi-Fi',
                'category' => 'IT Equipment',
                'origin' => 'CHED',
                'assets' => [
                    ['tag_code' => 'AST-SPMO-0001', 'position' => 'POS-PC-CHIEF', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-ADMIN-0001', 'position' => 'POS-ADMIN-PC', 'status' => AssetStatus::Unserviceable],
                ],
            ],
            [
                'sku' => 'AST-CAB-001',
                'name' => 'Four-Drawer Steel Filing Cabinet with Central Lock',
                'category' => 'Furniture and Fixtures',
                'origin' => 'Research Grant',
                'assets' => [
                    ['tag_code' => 'AST-BACC-0001', 'position' => 'POS-BACC-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-SCN-001',
                'name' => 'High-Speed Duplex Document Scanner with Automatic Feeder',
                'category' => 'Office Equipment',
                'origin' => 'Main Campus',
                'assets' => [
                    ['tag_code' => 'AST-REG-0002', 'position' => 'POS-REG-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-SWCH-001',
                'name' => '24-Port Managed Gigabit Network Switch',
                'category' => 'IT Equipment',
                'origin' => 'Main Campus',
                'assets' => [
                    ['tag_code' => 'AST-IT-0001', 'position' => 'POS-IT-EXPERT', 'status' => AssetStatus::Available],
                    ['tag_code' => 'AST-IT-0002', 'position' => 'POS-IT-EXPERT', 'status' => AssetStatus::Condemned],
                ],
            ],
            [
                'sku' => 'AST-AIR-001',
                'name' => '2.0 HP Inverter Split-Type Air Conditioner',
                'category' => 'Office Equipment',
                'origin' => 'Main Campus',
                'assets' => [
                    ['tag_code' => 'AST-FMO-0001', 'position' => 'POS-FMO-PC', 'status' => AssetStatus::Unserviceable],
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
                        'lifecycle_status' => $assetDefinition['status'] === AssetStatus::Condemned
                            ? AssetLifecycleStatus::Retired
                            : AssetLifecycleStatus::Active,
                        'condition_status' => match ($assetDefinition['status']) {
                            AssetStatus::Condemned => AssetConditionStatus::Unserviceable,
                            AssetStatus::Unserviceable => AssetConditionStatus::Damaged,
                            default => AssetConditionStatus::Serviceable,
                        },
                    ],
                );
            }
        }

        $consumables = [
            [
                'sku' => 'CON-PAPER-A4',
                'name' => 'Multipurpose Copy Paper, A4, 80 gsm, 500 Sheets/Ream',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 90,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01042', 'received_at' => $timeline->subMonths(5)->subDays(4), 'qty_received' => 120, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-01887', 'received_at' => $timeline->subMonths(3)->subDays(6), 'qty_received' => 140, 'qty_remaining' => 90, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02631', 'received_at' => $timeline->subMonths(1)->subDays(10), 'qty_received' => 90, 'qty_remaining' => 90, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-INK-BLK',
                'name' => 'Black Printer Ink Bottle, 135 mL',
                'category' => 'Office Supplies',
                'origin' => 'LGU',
                'reorder_threshold' => 18,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01308', 'received_at' => $timeline->subMonths(4)->subDays(3), 'qty_received' => 20, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02174', 'received_at' => $timeline->subMonths(2)->subDays(9), 'qty_received' => 20, 'qty_remaining' => 12, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02915', 'received_at' => $timeline->subDays(24), 'qty_received' => 12, 'qty_remaining' => 12, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-ALCOHOL-70',
                'name' => '70% Isopropyl Alcohol, 500 mL Bottle',
                'category' => 'Medical Supplies',
                'origin' => 'Donation',
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-00841', 'received_at' => $timeline->subMonths(6)->subDays(1), 'qty_received' => 36, 'qty_remaining' => 0, 'expires_at' => $timeline->addMonths(8)->toDateString()],
                    ['reference_no' => 'DR-2026-01962', 'received_at' => $timeline->subMonths(3)->subDays(12), 'qty_received' => 30, 'qty_remaining' => 30, 'expires_at' => $timeline->addMonths(10)->toDateString()],
                    ['reference_no' => 'DR-2026-02833', 'received_at' => $timeline->subDays(35), 'qty_received' => 30, 'qty_remaining' => 30, 'expires_at' => $timeline->addMonths(12)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-BAT-AA',
                'name' => 'AA Alkaline Battery, 1.5 V, 4 Pieces/Pack',
                'category' => 'Office Supplies',
                'origin' => 'CHED',
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01196', 'received_at' => $timeline->subMonths(5), 'qty_received' => 20, 'qty_remaining' => 0, 'expires_at' => $timeline->addMonths(14)->toDateString()],
                    ['reference_no' => 'DR-2026-02218', 'received_at' => $timeline->subMonths(2)->subDays(16), 'qty_received' => 25, 'qty_remaining' => 19, 'expires_at' => $timeline->addMonths(16)->toDateString()],
                    ['reference_no' => 'DR-2026-03004', 'received_at' => $timeline->subDays(18), 'qty_received' => 20, 'qty_remaining' => 20, 'expires_at' => $timeline->addMonths(18)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-DISINF-01',
                'name' => 'Ready-to-Use Surface Disinfectant, 1 Liter Bottle',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 10,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-02347', 'received_at' => $timeline->subMonths(2)->subDays(20), 'qty_received' => 14, 'qty_remaining' => 0, 'expires_at' => $timeline->addDays(4)->toDateString()],
                    ['reference_no' => 'DR-2026-02942', 'received_at' => $timeline->subDays(28), 'qty_received' => 16, 'qty_remaining' => 8, 'expires_at' => $timeline->addDays(40)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-GLOVES-M',
                'name' => 'Nitrile Examination Gloves, Medium, 100 Pieces/Box',
                'category' => 'Protective Equipment',
                'origin' => 'Donation',
                'reorder_threshold' => 12,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-03018', 'received_at' => $timeline->subDays(20), 'qty_received' => 18, 'qty_remaining' => 6, 'expires_at' => $timeline->addDays(6)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-FILEBOX-01',
                'name' => 'Acid-Free Archive File Box, Legal Size',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 10,
                'is_active' => false,
                'lots' => [
                    ['reference_no' => 'DR-2026-01274', 'received_at' => $timeline->subMonths(4)->subDays(8), 'qty_received' => 40, 'qty_remaining' => 40, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-TONER-151A',
                'name' => 'Black Laser Printer Toner Cartridge, 3,050-Page Yield',
                'category' => 'Office Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 6,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-03041', 'received_at' => $timeline->subDays(12), 'qty_received' => 12, 'qty_remaining' => 12, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-MASK-3PLY',
                'name' => 'Three-Ply Surgical Face Mask, 50 Pieces/Box',
                'category' => 'Protective Equipment',
                'origin' => 'Donation',
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2025-04182', 'received_at' => $timeline->subMonths(13), 'qty_received' => 40, 'qty_remaining' => 0, 'expires_at' => $timeline->subDays(15)->toDateString()],
                    ['reference_no' => 'DR-2026-03057', 'received_at' => $timeline->subDays(10), 'qty_received' => 80, 'qty_remaining' => 80, 'expires_at' => $timeline->addMonths(18)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-LIGHT-LED12',
                'name' => 'LED Bulb, 12 W, Daylight, E27 Base',
                'category' => 'Electrical Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 15,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-02794', 'received_at' => $timeline->subDays(45), 'qty_received' => 48, 'qty_remaining' => 48, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-HSOAP-500',
                'name' => 'Antibacterial Liquid Hand Soap, 500 mL Pump Bottle',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'reorder_threshold' => 12,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-02411', 'received_at' => $timeline->subMonths(2), 'qty_received' => 24, 'qty_remaining' => 0, 'expires_at' => $timeline->addMonths(10)->toDateString()],
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
                    notes: "Delivery receipt {$lot->reference_no}: {$product->name}",
                );

                $runningReceivedQty += $lot->qty_received;
            }
        }

        $expiredMaskProduct = Product::query()->where('sku', 'CON-MASK-3PLY')->firstOrFail();
        $expiredMaskLot = $expiredMaskProduct->lots()->where('reference_no', 'DR-2025-04182')->firstOrFail();
        $currentMaskLot = $expiredMaskProduct->lots()->where('reference_no', 'DR-2026-03057')->firstOrFail();

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'adjustment',
                'product_id' => $expiredMaskProduct->id,
                'stock_lot_id' => $expiredMaskLot->id,
                'notes' => 'Expired face masks removed from issuable stock after storeroom inspection.',
            ],
            [
                'reason_code' => StockMovementReasonCode::Expired,
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => -40,
                'qty_before' => 40,
                'qty_after' => 0,
                'counted_qty' => 0,
                'variance_qty' => -40,
                'performed_by' => $supplyHead->id,
                'accountable_position_id' => $supplyHead->position_id,
                'ip_address' => '10.250.1.10',
                'performed_at' => $timeline->subDays(15),
            ],
        );

        StockMovement::query()
            ->where('movement_type', 'receive')
            ->where('stock_lot_id', $currentMaskLot->id)
            ->update(['qty_before' => 0, 'qty_after' => 80]);

        $handSoapProduct = Product::query()->where('sku', 'CON-HSOAP-500')->firstOrFail();
        $handSoapLot = $handSoapProduct->lots()->where('reference_no', 'DR-2026-02411')->firstOrFail();

        StockMovement::query()->updateOrCreate(
            [
                'movement_type' => 'issue',
                'product_id' => $handSoapProduct->id,
                'stock_lot_id' => $handSoapLot->id,
                'notes' => 'Monthly washroom sanitation supplies issued to Facilities Management.',
            ],
            [
                'reason_code' => null,
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => -24,
                'qty_before' => 24,
                'qty_after' => 0,
                'counted_qty' => null,
                'variance_qty' => null,
                'performed_by' => $supplyHead->id,
                'accountable_position_id' => $positions['POS-FMO-PC']->id,
                'ip_address' => '10.250.1.10',
                'performed_at' => $timeline->subDays(20),
            ],
        );

        $deletedProduct = Product::withTrashed()->firstOrNew(['sku' => 'CON-MARKER-BLK']);
        $deletedProduct->fill([
            'name' => 'Permanent Marker, Black, Bullet Tip',
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
                'reference_no' => 'DR-2026-02009',
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
            notes: "Delivery receipt {$deletedLot->reference_no}: {$deletedProduct->name}",
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
                'performed_at' => $lot->received_at,
                'notes' => $notes,
            ],
        );
    }
}
