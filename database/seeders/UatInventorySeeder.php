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
use App\Models\UnitOfMeasurement;
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
            'Equipment',
            'Facilities Equipment',
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

        $units = $this->seedUnitsOfMeasurement();

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
                    'unit_of_measurement_id' => $units['piece']->id,
                    'type' => ProductType::Asset,
                    'reorder_threshold' => 0,
                    'is_active' => true,
                    'is_bookable' => in_array($definition['sku'], ['AST-LAP-001', 'AST-PROJ-001', 'AST-TAB-001'], true),
                ],
            );

            foreach ($definition['assets'] as $assetDefinition) {
                Asset::query()->updateOrCreate(
                    ['tag_code' => $assetDefinition['tag_code']],
                    [
                        'product_id' => $product->id,
                        'position_id' => $positions[$assetDefinition['position']]->id,
                        'status' => $assetDefinition['status'],
                        'serial_number' => 'PUP-'.str_replace('AST-', '', $assetDefinition['tag_code']),
                        'acquisition_date' => $timeline->subMonths(18)->toDateString(),
                        'acquisition_cost' => 25000,
                        'warranty_expires_at' => $timeline->addMonths(18)->toDateString(),
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
                    // Received before the PHY-2026-0001 count (14 days ago) that adjusts this lot.
                    ['reference_no' => 'DR-2026-03041', 'received_at' => $timeline->subDays(20), 'qty_received' => 12, 'qty_remaining' => 12, 'expires_at' => null],
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
                    'unit_of_measurement_id' => $this->unitForConsumable($definition['sku'], $units)->id,
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

        $this->seedExpandedInstitutionalCatalog(
            categories: $categories,
            origins: $origins,
            units: $units,
            positions: $positions,
            performedBy: $supplyHead,
            timeline: $timeline,
        );

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
            'unit_of_measurement_id' => $units['piece']->id,
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

    /**
     * @return array<string, UnitOfMeasurement>
     */
    private function seedUnitsOfMeasurement(): array
    {
        $units = [];

        foreach ([
            ['code' => 'piece', 'name' => 'Piece', 'abbreviation' => 'pc'],
            ['code' => 'ream', 'name' => 'Ream', 'abbreviation' => 'ream'],
            ['code' => 'box', 'name' => 'Box', 'abbreviation' => 'box'],
            ['code' => 'bottle', 'name' => 'Bottle', 'abbreviation' => 'btl'],
            ['code' => 'roll', 'name' => 'Roll', 'abbreviation' => 'roll'],
            ['code' => 'pack', 'name' => 'Pack', 'abbreviation' => 'pk'],
            ['code' => 'set', 'name' => 'Set', 'abbreviation' => 'set'],
        ] as $definition) {
            $units[$definition['code']] = UnitOfMeasurement::query()->updateOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'abbreviation' => $definition['abbreviation'],
                    'is_active' => true,
                ],
            );
        }

        return $units;
    }

    /**
     * @param  array<string, UnitOfMeasurement>  $units
     */
    private function unitForConsumable(string $sku, array $units): UnitOfMeasurement
    {
        if (str_contains($sku, 'PAPER')) {
            return $units['ream'];
        }

        if (str_contains($sku, 'GLOVES') || str_contains($sku, 'MASK') || str_contains($sku, 'FILEBOX')) {
            return $units['box'];
        }

        if (str_contains($sku, 'ALCOHOL') || str_contains($sku, 'HSOAP') || str_contains($sku, 'DISINF')) {
            return $units['bottle'];
        }

        if (str_contains($sku, 'TAPE')) {
            return $units['roll'];
        }

        if (str_contains($sku, 'BAT-AA')) {
            return $units['pack'];
        }

        return $units['piece'];
    }

    /**
     * @param  array<string, Category>  $categories
     * @param  array<string, Origin>  $origins
     * @param  array<string, UnitOfMeasurement>  $units
     * @param  array<string, Position>  $positions
     */
    private function seedExpandedInstitutionalCatalog(
        array $categories,
        array $origins,
        array $units,
        array $positions,
        User $performedBy,
        CarbonImmutable $timeline,
    ): void {
        $consumableDefinitions = $this->institutionalConsumableDefinitions();

        foreach ($consumableDefinitions as $index => $definition) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories['Office Supplies']->id,
                    'origin_id' => $origins['Main Campus']->id,
                    'unit_of_measurement_id' => $units[$definition['unit']]->id,
                    'variant' => $definition['variant'],
                    'brand' => $definition['brand'],
                    'model' => null,
                    'color' => $definition['color'],
                    'size' => $definition['size'],
                    'specifications' => $definition['specifications'],
                    'type' => ProductType::Consumable,
                    'reorder_threshold' => 8 + ($index % 13),
                    'lead_time_days' => 7 + ($index % 15),
                    'unit_price' => 12.50 + ($index * 3.75),
                    'is_active' => true,
                    'is_bookable' => false,
                ],
            );

            $quantity = 24 + (($index * 7) % 57);
            $lot = StockLot::query()->updateOrCreate(
                ['product_id' => $product->id, 'reference_no' => 'CATALOG-'.$definition['sku']],
                [
                    'received_at' => $timeline->startOfMonth()->subDays(15 + ($index % 12)),
                    'expires_at' => null,
                    'qty_received' => $quantity,
                    'qty_remaining' => $quantity,
                ],
            );

            ProductStock::query()->updateOrCreate(
                ['product_id' => $product->id],
                ['on_hand_qty' => $quantity],
            );

            $this->syncReceiveMovement(
                product: $product,
                lot: $lot,
                performedBy: $performedBy,
                beforeQty: 0,
                notes: "Institutional catalog opening balance: {$product->name}",
            );
        }

        $positionCycle = collect($positions)->values();

        foreach ($this->institutionalAssetProductDefinitions() as $index => $definition) {
            $product = Product::query()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories[$definition['category']]->id,
                    'origin_id' => $origins[$definition['origin']]->id,
                    'unit_of_measurement_id' => $units['piece']->id,
                    'variant' => $definition['variant'],
                    'brand' => $definition['brand'],
                    'model' => $definition['model'],
                    'color' => $definition['color'],
                    'size' => $definition['size'],
                    'specifications' => $definition['specifications'],
                    'type' => ProductType::Asset,
                    'reorder_threshold' => 0,
                    'lead_time_days' => 20 + ($index % 25),
                    'unit_price' => $definition['cost'],
                    'is_active' => true,
                    'is_bookable' => $definition['bookable'],
                ],
            );

            for ($assetNumber = 1; $assetNumber <= 5; $assetNumber++) {
                $acquisitionDate = $timeline->startOfMonth()
                    ->subMonths(47 - (($index * 5 + $assetNumber) % 48))
                    ->addDays(($index + $assetNumber) % 20);
                $assetTag = sprintf('AST-%s-%03d', str_replace('AST-', '', $definition['sku']), $assetNumber);

                Asset::query()->updateOrCreate(
                    ['tag_code' => $assetTag],
                    [
                        'product_id' => $product->id,
                        'position_id' => $positionCycle->get(($index + $assetNumber) % $positionCycle->count())?->id,
                        'serial_number' => sprintf('PUP-%s-%03d', str_replace('AST-', '', $definition['sku']), $assetNumber),
                        'status' => AssetStatus::Available,
                        'lifecycle_status' => AssetLifecycleStatus::Active,
                        'condition_status' => AssetConditionStatus::Serviceable,
                        'acquisition_date' => $acquisitionDate->toDateString(),
                        'acquisition_cost' => $definition['cost'],
                        'warranty_expires_at' => $acquisitionDate->addYears(3)->toDateString(),
                        'installed_at' => $definition['installed'] ? $acquisitionDate->addDays(7)->toDateString() : null,
                        'maintenance_interval_days' => $definition['maintenance_interval_days'],
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, array{sku: string, name: string, unit: string, variant: string, brand: string|null, color: string|null, size: string|null, specifications: array<string, string>}>
     */
    private function institutionalConsumableDefinitions(): array
    {
        $definitions = [];

        foreach (['Short' => '8.5 × 11 in', 'A4' => '210 × 297 mm', 'Long' => '8.5 × 13 in', 'Legal' => '8.5 × 14 in', 'Folio' => '8.5 × 13.5 in'] as $code => $dimensions) {
            foreach ([70, 80] as $gsm) {
                $skuCode = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? $code);
                $definitions[] = [
                    'sku' => "OFF-PAPER-{$skuCode}-{$gsm}",
                    'name' => "Bond Paper, {$code}, {$gsm} gsm, 500 Sheets/Ream",
                    'unit' => 'ream',
                    'variant' => "{$code} · {$gsm} gsm",
                    'brand' => 'Institutional Standard',
                    'color' => 'White',
                    'size' => $dimensions,
                    'specifications' => ['sheet_count' => '500', 'weight_gsm' => (string) $gsm],
                ];
            }
        }

        foreach (['Blue', 'Black', 'Red', 'Green'] as $color) {
            $definitions[] = [
                'sku' => 'OFF-PEN-'.strtoupper(substr($color, 0, 3)),
                'name' => "Ballpen, {$color}, 0.7 mm",
                'unit' => 'piece',
                'variant' => '0.7 mm retractable',
                'brand' => 'WriteRight',
                'color' => $color,
                'size' => '0.7 mm',
                'specifications' => ['ink_type' => 'Oil-based'],
            ];
        }

        foreach (['Expanding', 'File', 'Document', 'Long', 'Short'] as $folderType) {
            foreach (['Blue', 'Maroon'] as $color) {
                $definitions[] = [
                    'sku' => 'OFF-FOLDER-'.strtoupper(substr($folderType, 0, 4)).'-'.strtoupper(substr($color, 0, 2)),
                    'name' => "{$folderType} Folder, {$color}",
                    'unit' => 'piece',
                    'variant' => "{$folderType} · {$color}",
                    'brand' => null,
                    'color' => $color,
                    'size' => str_contains($folderType, 'Long') ? 'Long' : 'A4',
                    'specifications' => ['material' => $folderType === 'Expanding' ? 'Polypropylene' : 'Card stock'],
                ];
            }
        }

        foreach ([
            ['MAG-PLS', 'Magazine File, Plastic, 80 mm', 'piece', 'Plastic · 80 mm', null, null, '80 mm'],
            ['MAG-CRD', 'Magazine File, Cardboard, 75 mm', 'piece', 'Cardboard · 75 mm', null, null, '75 mm'],
            ['CLIP-S', 'Paper Clip, Small, 100 Pieces/Box', 'box', 'Small', null, 'Silver', '28 mm'],
            ['CLIP-M', 'Paper Clip, Medium, 100 Pieces/Box', 'box', 'Medium', null, 'Silver', '33 mm'],
            ['CLIP-L', 'Paper Clip, Large, 50 Pieces/Box', 'box', 'Large', null, 'Silver', '50 mm'],
            ['RUB-S', 'Rubber Bond, Small, 100 Pieces/Box', 'box', 'Small', null, 'Natural', 'Small'],
            ['RUB-M', 'Rubber Bond, Medium, 100 Pieces/Box', 'box', 'Medium', null, 'Natural', 'Medium'],
            ['RUB-L', 'Rubber Bond, Large, 100 Pieces/Box', 'box', 'Large', null, 'Natural', 'Large'],
        ] as [$sku, $name, $unit, $variant, $brand, $color, $size]) {
            $definitions[] = [
                'sku' => 'OFF-'.$sku,
                'name' => $name,
                'unit' => $unit,
                'variant' => $variant,
                'brand' => $brand,
                'color' => $color,
                'size' => $size,
                'specifications' => ['packaging' => $unit],
            ];
        }

        foreach (['Black', 'Blue', 'Red', 'Green'] as $color) {
            foreach (['Disposable', 'Refillable'] as $type) {
                $definitions[] = [
                    // Three letters keep Black (BLA) and Blue (BLU) from sharing one SKU.
                    'sku' => 'OFF-MARK-'.strtoupper(substr($color, 0, 3)).'-'.strtoupper(substr($type, 0, 3)),
                    'name' => "Board Marker, {$color}, {$type}",
                    'unit' => 'piece',
                    'variant' => $type,
                    'brand' => 'BoardPro',
                    'color' => $color,
                    'size' => 'Bullet tip',
                    'specifications' => ['tip' => 'Bullet'],
                ];
            }
        }

        foreach (['Scotch', 'Masking', 'Packaging', 'Double-Sided', 'Electrical'] as $tapeType) {
            foreach (['12 mm', '24 mm'] as $width) {
                $slug = strtoupper(substr(str_replace('-', '', $tapeType), 0, 4));
                $definitions[] = [
                    'sku' => 'OFF-TAPE-'.$slug.'-'.str_replace(' ', '', $width),
                    'name' => "{$tapeType} Tape, {$width}",
                    'unit' => 'piece',
                    'variant' => "{$tapeType} · {$width}",
                    'brand' => 'SecureHold',
                    'color' => $tapeType === 'Electrical' ? 'Black' : 'Clear',
                    'size' => $width,
                    'specifications' => ['length' => '20 m'],
                ];
            }
        }

        foreach (['Ink' => 'INJ', 'Toner' => 'LAS'] as $medium => $technology) {
            foreach (['Black', 'Cyan', 'Magenta', 'Yellow'] as $color) {
                $definitions[] = [
                    'sku' => 'OFF-'.$technology.'-'.strtoupper(substr($color, 0, 3)),
                    'name' => "{$medium} Cartridge, {$color}, Universal Office Series",
                    'unit' => 'piece',
                    'variant' => "{$medium} · {$color}",
                    'brand' => 'PrintCore',
                    'color' => $color,
                    'size' => null,
                    'specifications' => ['printer_series' => 'Universal Office Series', 'medium' => $medium],
                ];
            }
        }

        return $definitions;
    }

    /**
     * @return array<int, array{sku: string, name: string, category: string, origin: string, variant: string, brand: string, model: string, color: string|null, size: string|null, specifications: array<string, string>, cost: float, bookable: bool, installed: bool, maintenance_interval_days: int|null}>
     */
    private function institutionalAssetProductDefinitions(): array
    {
        $definitions = [
            ['LAP-14', 'Laptop, 14-inch, 16 GB RAM, 512 GB SSD', 'Equipment', 'Northstar', 'NS14-16', true, false, null, 52000],
            ['LAP-15', 'Laptop, 15.6-inch, 16 GB RAM, 1 TB SSD', 'Equipment', 'Northstar', 'NS15-16', true, false, null, 61000],
            ['DESK-I5', 'Desktop Computer, Core i5, 16 GB RAM, 512 GB SSD', 'Equipment', 'CorePoint', 'CPD-I5', false, false, 365, 48000],
            ['DESK-I7', 'Desktop Computer, Core i7, 32 GB RAM, 1 TB SSD', 'Equipment', 'CorePoint', 'CPD-I7', false, false, 365, 72000],
            ['RTR-24', 'Dual-Band Enterprise Router, 24-Port', 'Equipment', 'NetBridge', 'NB-R24', false, true, 180, 18500],
            ['PRN-LAS', 'Monochrome Laser Printer with Duplex Networking', 'Equipment', 'PrintCore', 'PC-L420', false, false, 365, 27500],
            ['PRN-INK', 'Color Ink Tank Multifunction Printer', 'Equipment', 'PrintCore', 'PC-I310', false, false, 365, 16500],
            ['HDD-2TB', 'External Hard Drive, 2 TB, USB 3.2', 'Equipment', 'DataVault', 'DV-H2', false, false, null, 4800],
            ['SSD-1TB', 'External Solid-State Drive, 1 TB, USB-C', 'Equipment', 'DataVault', 'DV-S1', false, false, null, 6500],
            ['USB-128', 'USB Flash Drive, 128 GB, USB 3.2', 'Equipment', 'DataVault', 'DV-U128', false, false, null, 950],
            ['PROJ-FHD', 'Projector, Full HD, 4,000 ANSI Lumens', 'Equipment', 'VisionCast', 'VC-4000', true, false, 180, 58000],
            ['TV-55', 'Television, 55-inch, 4K Smart Display', 'Equipment', 'VisionCast', 'VC-TV55', true, true, 365, 38500],
            ['TBL-OFF', 'Office Table, Laminated Wood, 1.5 m', 'Furniture and Fixtures', 'CampusCraft', 'CC-OT150', false, false, null, 9200],
            ['TBL-CLS', 'Classroom Table, Two-Seater', 'Furniture and Fixtures', 'CampusCraft', 'CC-CT2', false, false, null, 3800],
            ['TBL-CMP', 'Computer Table with Cable Tray', 'Furniture and Fixtures', 'CampusCraft', 'CC-CPT', false, false, null, 6200],
            ['TBL-CON', 'Conference Table, 12-Seater', 'Furniture and Fixtures', 'CampusCraft', 'CC-CF12', true, false, null, 42000],
            ['TBL-LAB', 'Laboratory Table, Chemical-Resistant Top', 'Furniture and Fixtures', 'CampusCraft', 'CC-LT180', false, false, null, 18500],
            ['CHR-OFF', 'Ergonomic Office Chair', 'Furniture and Fixtures', 'CampusCraft', 'CC-OC1', false, false, null, 7500],
            ['CHR-CLS', 'Classroom Chair with Writing Arm', 'Furniture and Fixtures', 'CampusCraft', 'CC-CWA', false, false, null, 2100],
            ['CHR-MONO', 'Monoblock Chair, Heavy Duty', 'Furniture and Fixtures', 'CampusCraft', 'CC-MB1', true, false, null, 850],
            ['CAB-STEEL', 'Steel Storage Cabinet, Double Door', 'Furniture and Fixtures', 'CampusCraft', 'CC-SC2', false, false, null, 12500],
            ['CAB-FILE', 'Filing Cabinet, Four Drawer', 'Furniture and Fixtures', 'CampusCraft', 'CC-FC4', false, false, null, 10500],
            ['SHELF-5', 'Storage Shelves, Five Tier', 'Furniture and Fixtures', 'CampusCraft', 'CC-ST5', false, false, null, 6800],
            ['BOOK-6', 'Bookshelves, Six Tier', 'Furniture and Fixtures', 'CampusCraft', 'CC-BS6', false, false, null, 8900],
            ['SOFA-3', 'Sofa, Three-Seater, Institutional Grade', 'Furniture and Fixtures', 'CampusCraft', 'CC-SF3', false, false, null, 28500],
            ['AC-WIN', 'Air Conditioner, Window Type, 1.5 HP', 'Facilities Equipment', 'CoolWorks', 'CW-W15', false, true, 120, 29500],
            ['AC-SPLIT', 'Air Conditioner, Split Type Inverter, 2.0 HP', 'Facilities Equipment', 'CoolWorks', 'CW-S20', false, true, 120, 48500],
            ['FAN-CEIL', 'Electric Fan, Ceiling Type, 56-inch', 'Facilities Equipment', 'AirFlow', 'AF-C56', false, true, 180, 4800],
            ['FAN-STAND', 'Electric Fan, Stand Type, 18-inch', 'Facilities Equipment', 'AirFlow', 'AF-S18', false, false, 180, 3200],
            ['FAN-DESK', 'Electric Fan, Desk Type, 12-inch', 'Facilities Equipment', 'AirFlow', 'AF-D12', false, false, 180, 1850],
            ['FAN-EXH', 'Exhaust Fan, Wall-Mounted, 12-inch', 'Facilities Equipment', 'AirFlow', 'AF-E12', false, true, 180, 5600],
        ];

        return collect($definitions)->map(static function (array $definition): array {
            [$sku, $name, $category, $brand, $model, $bookable, $installed, $interval, $cost] = $definition;

            return [
                'sku' => 'AST-'.$sku,
                'name' => $name,
                'category' => $category,
                'origin' => 'Main Campus',
                'variant' => str_contains($name, ',') ? trim(substr($name, strpos($name, ',') + 1)) : $name,
                'brand' => $brand,
                'model' => $model,
                'color' => null,
                'size' => null,
                'specifications' => ['institutional_classification' => $category],
                'cost' => (float) $cost,
                'bookable' => $bookable,
                'installed' => $installed,
                'maintenance_interval_days' => $interval,
            ];
        })->all();
    }
}
