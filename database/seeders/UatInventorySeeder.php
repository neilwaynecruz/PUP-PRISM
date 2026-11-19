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
use App\Models\Supplier;
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

        if (Supplier::query()->count() === 0) {
            $this->call(SupplierSeeder::class);
        }

        /** @var array<string, Supplier> $suppliers */
        $suppliers = Supplier::query()->get()->keyBy('name')->all();

        /** @var array<string, Position> $positions */
        $positions = Position::query()->get()->keyBy('code')->all();
        $supplyHead = User::query()->where('email', 'norman.cruz0701@gmail.com')->firstOrFail();

        $assetProducts = [
            [
                'sku' => 'AST-LAP-001',
                'name' => 'Business Laptop, 14-inch, 16 GB RAM, 512 GB SSD',
                'category' => 'IT Equipment',
                'origin' => 'Main Campus',
                'supplier' => 'Northstar Digital Systems Corporation',
                'brand' => 'Northstar',
                'model' => 'NS14-Pro',
                'variant' => '14-inch · 16 GB RAM · 512 GB SSD',
                'lead_time_days' => 10,
                'unit_price' => 52000.00,
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
                'supplier' => 'VisionCast Audio Visual Technologies, Inc.',
                'brand' => 'VisionCast',
                'model' => 'VC-4000HD',
                'variant' => '4,000 ANSI Lumens · Full HD',
                'lead_time_days' => 12,
                'unit_price' => 48500.00,
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
                'supplier' => 'PrintCore Imaging and Document Systems',
                'brand' => 'PrintCore',
                'model' => 'PC-M410N',
                'variant' => 'Monochrome · Duplex Networking',
                'lead_time_days' => 8,
                'unit_price' => 24900.00,
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
                'supplier' => 'Northstar Digital Systems Corporation',
                'brand' => 'Northstar',
                'model' => 'NS-Tab11',
                'variant' => '10.9-inch · 128 GB · Wi-Fi',
                'lead_time_days' => 10,
                'unit_price' => 26500.00,
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
                'supplier' => 'PrimeLine Furniture and Storage Solutions',
                'brand' => 'PrimeLine',
                'model' => 'PL-4DC',
                'variant' => '4-Drawer · Central Lock',
                'lead_time_days' => 14,
                'unit_price' => 12800.00,
                'assets' => [
                    ['tag_code' => 'AST-BACC-0001', 'position' => 'POS-BACC-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-SCN-001',
                'name' => 'High-Speed Duplex Document Scanner with Automatic Feeder',
                'category' => 'Office Equipment',
                'origin' => 'Main Campus',
                'supplier' => 'PrintCore Imaging and Document Systems',
                'brand' => 'PrintCore',
                'model' => 'PC-DS900',
                'variant' => 'Duplex · 50-Sheet ADF',
                'lead_time_days' => 8,
                'unit_price' => 28900.00,
                'assets' => [
                    ['tag_code' => 'AST-REG-0002', 'position' => 'POS-REG-PC', 'status' => AssetStatus::Available],
                ],
            ],
            [
                'sku' => 'AST-SWCH-001',
                'name' => '24-Port Managed Gigabit Network Switch',
                'category' => 'IT Equipment',
                'origin' => 'Main Campus',
                'supplier' => 'NetBridge Enterprise Networks Corporation',
                'brand' => 'NetBridge',
                'model' => 'NB-G24M',
                'variant' => '24-Port · Managed Gigabit',
                'lead_time_days' => 14,
                'unit_price' => 19800.00,
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
                'supplier' => 'CoolWorks Mechanical Systems Corporation',
                'brand' => 'CoolWorks',
                'model' => 'CW-S20INV',
                'variant' => '2.0 HP · Inverter Split-Type',
                'lead_time_days' => 21,
                'unit_price' => 46500.00,
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
                    'supplier_id' => $suppliers[$definition['supplier']]->id ?? null,
                    'unit_of_measurement_id' => $units['piece']->id,
                    'brand' => $definition['brand'],
                    'model' => $definition['model'],
                    'variant' => $definition['variant'],
                    'specifications' => ['institutional_classification' => $definition['category']],
                    'lead_time_days' => $definition['lead_time_days'],
                    'unit_price' => $definition['unit_price'],
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
                        'acquisition_cost' => $definition['unit_price'],
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
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'brand' => 'PaperOne',
                'variant' => 'A4 · 80 gsm · 500 Sheets',
                'lead_time_days' => 5,
                'unit_price' => 235.00,
                'reorder_threshold' => 90,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01042', 'received_at' => $timeline->subMonths(5)->subDays(4), 'qty_received' => 120, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-01887', 'received_at' => $timeline->subMonths(3)->subDays(6), 'qty_received' => 140, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02631', 'received_at' => $timeline->subMonths(1)->subDays(10), 'qty_received' => 90, 'qty_remaining' => 36, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-INK-BLK',
                'name' => 'Black Printer Ink Bottle, 135 mL',
                'category' => 'Office Supplies',
                'origin' => 'LGU',
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'brand' => 'PrintCore',
                'variant' => 'Pigment Black · 135 mL',
                'lead_time_days' => 4,
                'unit_price' => 675.00,
                'reorder_threshold' => 18,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01308', 'received_at' => $timeline->subMonths(4)->subDays(3), 'qty_received' => 20, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02174', 'received_at' => $timeline->subMonths(2)->subDays(9), 'qty_received' => 20, 'qty_remaining' => 0, 'expires_at' => null],
                    ['reference_no' => 'DR-2026-02915', 'received_at' => $timeline->subDays(24), 'qty_received' => 20, 'qty_remaining' => 12, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-ALCOHOL-70',
                'name' => '70% Isopropyl Alcohol, 500 mL Bottle',
                'category' => 'Medical Supplies',
                'origin' => 'Donation',
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'brand' => 'MedClean',
                'variant' => '70% Solution · 500 mL',
                'lead_time_days' => 3,
                'unit_price' => 145.00,
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
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'brand' => 'VoltCell',
                'variant' => 'AA 1.5 V · 4-Pack',
                'lead_time_days' => 5,
                'unit_price' => 95.00,
                'reorder_threshold' => 20,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-01196', 'received_at' => $timeline->subMonths(5), 'qty_received' => 20, 'qty_remaining' => 0, 'expires_at' => $timeline->addMonths(14)->toDateString()],
                    ['reference_no' => 'DR-2026-02218', 'received_at' => $timeline->subMonths(2)->subDays(16), 'qty_received' => 25, 'qty_remaining' => 0, 'expires_at' => $timeline->addMonths(16)->toDateString()],
                    ['reference_no' => 'DR-2026-03004', 'received_at' => $timeline->subDays(18), 'qty_received' => 22, 'qty_remaining' => 18, 'expires_at' => $timeline->addMonths(18)->toDateString()],
                ],
            ],
            [
                'sku' => 'CON-DISINF-01',
                'name' => 'Ready-to-Use Surface Disinfectant, 1 Liter Bottle',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'supplier' => 'Greenfield Janitorial and Sanitation Supply',
                'brand' => 'Greenfield',
                'variant' => 'Hospital Grade · 1 L',
                'lead_time_days' => 3,
                'unit_price' => 210.00,
                'reorder_threshold' => 15,
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
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'brand' => 'MediShield',
                'variant' => 'Powder-Free · Medium · 100s',
                'lead_time_days' => 4,
                'unit_price' => 380.00,
                'reorder_threshold' => 15,
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
                'supplier' => 'SecureHold Packaging and Office Products',
                'brand' => 'SecureHold',
                'variant' => 'Archival Board · Legal',
                'lead_time_days' => 5,
                'unit_price' => 160.00,
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
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'brand' => 'PrintCore',
                'variant' => 'Standard Yield · 3,050 Pages',
                'lead_time_days' => 5,
                'unit_price' => 5890.00,
                'reorder_threshold' => 8,
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
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'brand' => 'MediShield',
                'variant' => 'Earloop · 50 Pieces/Box',
                'lead_time_days' => 4,
                'unit_price' => 165.00,
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
                'supplier' => 'VoltEdge Electrical Supply Corporation',
                'brand' => 'VoltEdge',
                'variant' => '12 W · Daylight · E27',
                'lead_time_days' => 7,
                'unit_price' => 125.00,
                'reorder_threshold' => 15,
                'is_active' => true,
                'lots' => [
                    ['reference_no' => 'DR-2026-02794', 'received_at' => $timeline->subDays(120), 'qty_received' => 48, 'qty_remaining' => 48, 'expires_at' => null],
                ],
            ],
            [
                'sku' => 'CON-HSOAP-500',
                'name' => 'Antibacterial Liquid Hand Soap, 500 mL Pump Bottle',
                'category' => 'Cleaning Supplies',
                'origin' => 'Main Campus',
                'supplier' => 'Greenfield Janitorial and Sanitation Supply',
                'brand' => 'Greenfield',
                'variant' => 'Antibacterial · 500 mL',
                'lead_time_days' => 3,
                'unit_price' => 115.00,
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
                    'supplier_id' => $suppliers[$definition['supplier']]->id ?? null,
                    'unit_of_measurement_id' => $this->unitForConsumable($definition['sku'], $units)->id,
                    'brand' => $definition['brand'],
                    'variant' => $definition['variant'],
                    'lead_time_days' => $definition['lead_time_days'],
                    'unit_price' => $definition['unit_price'],
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
            suppliers: $suppliers,
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

        StockMovement::query()
            ->where('product_id', $handSoapProduct->id)
            ->where('stock_lot_id', $handSoapLot->id)
            ->where('movement_type', 'issue')
            ->where('notes', 'Monthly washroom sanitation supplies issued to Facilities Management.')
            ->delete();

        $handSoapRunning = 24;
        foreach ([
            ['daysAgo' => 35, 'qty' => 6, 'note' => 'Washroom sanitation batch #1 issued to Facilities Management.'],
            ['daysAgo' => 28, 'qty' => 6, 'note' => 'Washroom sanitation batch #2 issued to Facilities Management.'],
            ['daysAgo' => 21, 'qty' => 6, 'note' => 'Washroom sanitation batch #3 issued to Facilities Management.'],
            ['daysAgo' => 14, 'qty' => 6, 'note' => 'Washroom sanitation batch #4 issued to Facilities Management.'],
        ] as $batch) {
            $before = $handSoapRunning;
            $handSoapRunning -= $batch['qty'];

            StockMovement::query()->updateOrCreate(
                [
                    'movement_type' => 'issue',
                    'product_id' => $handSoapProduct->id,
                    'stock_lot_id' => $handSoapLot->id,
                    'notes' => $batch['note'],
                ],
                [
                    'reason_code' => null,
                    'asset_id' => null,
                    'requisition_id' => null,
                    'qty_delta' => -$batch['qty'],
                    'qty_before' => $before,
                    'qty_after' => $handSoapRunning,
                    'counted_qty' => null,
                    'variance_qty' => null,
                    'performed_by' => $supplyHead->id,
                    'accountable_position_id' => $positions['POS-FMO-PC']->id,
                    'ip_address' => '10.250.1.10',
                    'performed_at' => $timeline->subDays($batch['daysAgo']),
                ],
            );
        }

        $deletedProduct = Product::withTrashed()->firstOrNew(['sku' => 'CON-MARKER-BLK']);
        $deletedProduct->fill([
            'name' => 'Permanent Marker, Black, Bullet Tip',
            'category_id' => $categories['Office Supplies']->id,
            'origin_id' => $origins['LGU']->id,
            'supplier_id' => $suppliers['BoardPro Classroom Supplies Corporation']->id ?? null,
            'unit_of_measurement_id' => $units['piece']->id,
            'brand' => 'BoardPro',
            'variant' => 'Bullet Tip · Permanent Black',
            'lead_time_days' => 6,
            'unit_price' => 48.00,
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
     * @param  array<string, Supplier>  $suppliers
     */
    private function seedExpandedInstitutionalCatalog(
        array $categories,
        array $origins,
        array $units,
        array $positions,
        array $suppliers,
        User $performedBy,
        CarbonImmutable $timeline,
    ): void {
        $consumableDefinitions = $this->institutionalConsumableDefinitions();

        $supplierByBrand = [
            'Institutional Standard' => 'Metro Manila Office Solutions Corporation',
            'WriteRight' => 'Metro Manila Office Solutions Corporation',
            'BoardPro' => 'BoardPro Classroom Supplies Corporation',
            'SecureHold' => 'SecureHold Packaging and Office Products',
            'PrintCore' => 'PrintCore Imaging and Document Systems',
            'Northstar' => 'Northstar Digital Systems Corporation',
            'CorePoint' => 'Northstar Digital Systems Corporation',
            'NetBridge' => 'NetBridge Enterprise Networks Corporation',
            'DataVault' => 'DataVault Storage Solutions Philippines',
            'VisionCast' => 'VisionCast Audio Visual Technologies, Inc.',
            'CampusCraft' => 'CampusCraft Institutional Furnishings, Inc.',
            'CoolWorks' => 'CoolWorks Mechanical Systems Corporation',
            'AirFlow' => 'AirFlow Ventilation and Facilities Supply',
        ];

        $calibratedStockLevels = [
            // Urgent (<= 7 days of supply under forecasted daily demand)
            'OFF-PAPER-A4-80' => ['qty' => 20, 'threshold' => 30, 'lead_time' => 7],
            'OFF-PAPER-LONG-80' => ['qty' => 14, 'threshold' => 25, 'lead_time' => 7],
            'OFF-PEN-BLU' => ['qty' => 45, 'threshold' => 60, 'lead_time' => 5],
            'OFF-PEN-BLA' => ['qty' => 36, 'threshold' => 50, 'lead_time' => 5],
            'OFF-MARK-BLA-REF' => ['qty' => 12, 'threshold' => 20, 'lead_time' => 5],
            'OFF-LAS-BLA' => ['qty' => 4, 'threshold' => 6, 'lead_time' => 10],
            'OFF-TAPE-MASK-24mm' => ['qty' => 10, 'threshold' => 18, 'lead_time' => 5],
            // At-risk (8..14 days of supply)
            'OFF-PAPER-A4-70' => ['qty' => 28, 'threshold' => 25, 'lead_time' => 7],
            'OFF-PAPER-SHORT-80' => ['qty' => 22, 'threshold' => 20, 'lead_time' => 7],
            'OFF-FOLDER-DOCU-BL' => ['qty' => 26, 'threshold' => 24, 'lead_time' => 7],
            'OFF-INJ-BLA' => ['qty' => 10, 'threshold' => 8, 'lead_time' => 10],
            'OFF-TAPE-SCOT-24mm' => ['qty' => 20, 'threshold' => 16, 'lead_time' => 5],
            'OFF-CLIP-M' => ['qty' => 25, 'threshold' => 20, 'lead_time' => 7],
            // Slow-moving (idle >100 days with positive stock on hand)
            'OFF-MAG-CRD' => ['qty' => 28, 'threshold' => 10, 'lead_time' => 7],
            'OFF-RUB-L' => ['qty' => 32, 'threshold' => 12, 'lead_time' => 7],
            'OFF-CLIP-L' => ['qty' => 24, 'threshold' => 10, 'lead_time' => 7],
            'OFF-FOLDER-SHOR-MA' => ['qty' => 35, 'threshold' => 15, 'lead_time' => 7],
            'OFF-TAPE-DOUB-24mm' => ['qty' => 22, 'threshold' => 10, 'lead_time' => 5],
        ];

        foreach ($consumableDefinitions as $index => $definition) {
            $sku = $definition['sku'];
            $brand = $definition['brand'];
            $supplierName = $supplierByBrand[$brand ?? 'Institutional Standard'] ?? 'Metro Manila Office Solutions Corporation';
            $calibration = $calibratedStockLevels[$sku] ?? null;

            $leadTimeDays = $calibration['lead_time'] ?? match (true) {
                str_starts_with($sku, 'OFF-INJ-'), str_starts_with($sku, 'OFF-LAS-') => 10,
                str_starts_with($sku, 'OFF-TAPE-'), str_starts_with($sku, 'OFF-MARK-') => 5,
                default => 7,
            };

            $reorderThreshold = $calibration['threshold'] ?? match (true) {
                str_starts_with($sku, 'OFF-LAS-') => 4,
                str_starts_with($sku, 'OFF-INJ-') => 6,
                str_starts_with($sku, 'OFF-PEN-') => 25,
                str_starts_with($sku, 'OFF-PAPER-') => 18,
                default => 12 + ($index % 9),
            };

            $quantity = $calibration['qty'] ?? match (true) {
                str_starts_with($sku, 'OFF-LAS-') => 12 + ($index % 5),
                str_starts_with($sku, 'OFF-INJ-') => 20 + ($index % 8),
                str_starts_with($sku, 'OFF-PEN-') => 68 + (($index * 7) % 35),
                default => 34 + (($index * 7) % 42),
            };

            $product = Product::query()->updateOrCreate(
                ['sku' => $sku],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories['Office Supplies']->id,
                    'origin_id' => $origins['Main Campus']->id,
                    'unit_of_measurement_id' => $units[$definition['unit']]->id,
                    'supplier_id' => ($suppliers[$supplierName] ?? collect($suppliers)->first())?->id,
                    'variant' => $definition['variant'],
                    'brand' => $brand,
                    'model' => null,
                    'color' => $definition['color'],
                    'size' => $definition['size'],
                    'specifications' => $definition['specifications'],
                    'type' => ProductType::Consumable,
                    'reorder_threshold' => $reorderThreshold,
                    'lead_time_days' => $leadTimeDays,
                    'unit_price' => $this->realisticCatalogConsumablePrice($sku),
                    'is_active' => true,
                    'is_bookable' => false,
                ],
            );

            $lot = StockLot::query()->updateOrCreate(
                ['product_id' => $product->id, 'reference_no' => 'CATALOG-'.$sku],
                [
                    'received_at' => $timeline->startOfMonth()->subDays(110 + ($index % 25)),
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
            $assetSupplierName = $supplierByBrand[$definition['brand']] ?? 'Northstar Digital Systems Corporation';

            $product = Product::query()->updateOrCreate(
                ['sku' => $definition['sku']],
                [
                    'name' => $definition['name'],
                    'category_id' => $categories[$definition['category']]->id,
                    'origin_id' => $origins[$definition['origin']]->id,
                    'unit_of_measurement_id' => $units['piece']->id,
                    'supplier_id' => ($suppliers[$assetSupplierName] ?? collect($suppliers)->first())?->id,
                    'variant' => $definition['variant'],
                    'brand' => $definition['brand'],
                    'model' => $definition['model'],
                    'color' => $definition['color'],
                    'size' => $definition['size'],
                    'specifications' => $definition['specifications'],
                    'type' => ProductType::Asset,
                    'reorder_threshold' => 0,
                    'lead_time_days' => match ($definition['category']) {
                        'Equipment' => 14,
                        'Facilities Equipment' => 21,
                        default => 18,
                    },
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

    private function realisticCatalogConsumablePrice(string $sku): float
    {
        return match (true) {
            str_starts_with($sku, 'OFF-PAPER-') && str_ends_with($sku, '-80') => str_contains($sku, 'A4') || str_contains($sku, 'SHORT') ? 285.00 : 310.00,
            str_starts_with($sku, 'OFF-PAPER-') => str_contains($sku, 'A4') || str_contains($sku, 'SHORT') ? 245.00 : 265.00,
            str_starts_with($sku, 'OFF-PEN-') => 22.00,
            str_starts_with($sku, 'OFF-FOLDER-EXPA-') => 65.00,
            str_starts_with($sku, 'OFF-FOLDER-SHOR-') => 18.00,
            str_starts_with($sku, 'OFF-FOLDER-') => 24.00,
            $sku === 'OFF-MAG-PLS' => 145.00,
            $sku === 'OFF-MAG-CRD' => 68.00,
            $sku === 'OFF-CLIP-S' => 28.00,
            $sku === 'OFF-CLIP-M' => 36.00,
            $sku === 'OFF-CLIP-L' => 48.00,
            $sku === 'OFF-RUB-S' => 42.00,
            $sku === 'OFF-RUB-M' => 58.00,
            $sku === 'OFF-RUB-L' => 75.00,
            str_starts_with($sku, 'OFF-MARK-') && str_ends_with($sku, '-REF') => 68.00,
            str_starts_with($sku, 'OFF-MARK-') => 48.00,
            $sku === 'OFF-TAPE-SCOT-12mm' => 28.00,
            $sku === 'OFF-TAPE-SCOT-24mm' => 45.00,
            $sku === 'OFF-TAPE-MASK-12mm' => 42.00,
            $sku === 'OFF-TAPE-MASK-24mm' => 68.00,
            $sku === 'OFF-TAPE-PACK-12mm' => 52.00,
            $sku === 'OFF-TAPE-PACK-24mm' => 78.00,
            $sku === 'OFF-TAPE-DOUB-12mm' => 55.00,
            $sku === 'OFF-TAPE-DOUB-24mm' => 85.00,
            $sku === 'OFF-TAPE-ELEC-12mm' => 45.00,
            $sku === 'OFF-TAPE-ELEC-24mm' => 72.00,
            $sku === 'OFF-INJ-BLA' => 420.00,
            str_starts_with($sku, 'OFF-INJ-') => 395.00,
            $sku === 'OFF-LAS-BLA' => 2850.00,
            str_starts_with($sku, 'OFF-LAS-') => 3150.00,
            default => 55.00,
        };
    }
}
