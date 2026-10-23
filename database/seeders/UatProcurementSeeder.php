<?php

namespace Database\Seeders;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UatProcurementSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        $this->call(SupplierSeeder::class);

        $requestedBy = User::query()->where('email', 'supply@local.test')->firstOrFail();
        $approvedBy = User::query()->where('email', 'admin@local.test')->firstOrFail();

        $suppliers = Supplier::query()->get()->keyBy('name');
        $products = Product::query()->get()->keyBy('sku');

        foreach ([
            'CON-PAPER-A4' => [
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'lead_time_days' => 5,
                'unit_price' => 218.50,
            ],
            'CON-INK-BLK' => [
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'lead_time_days' => 4,
                'unit_price' => 675.00,
            ],
            'CON-BAT-AA' => [
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'lead_time_days' => 5,
                'unit_price' => 32.00,
            ],
            'CON-ALCOHOL-70' => [
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'lead_time_days' => 3,
                'unit_price' => 145.00,
            ],
            'CON-GLOVES-M' => [
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'lead_time_days' => 4,
                'unit_price' => 380.00,
            ],
            'CON-DISINF-01' => [
                'supplier' => 'Greenfield Janitorial and Sanitation Supply',
                'lead_time_days' => 2,
                'unit_price' => 195.00,
            ],
            'AST-LAP-001' => [
                'supplier' => 'Northstar Digital Systems Corporation',
                'lead_time_days' => 10,
                'unit_price' => 38500.00,
            ],
            'AST-PRN-001' => [
                'supplier' => 'Northstar Digital Systems Corporation',
                'lead_time_days' => 8,
                'unit_price' => 14990.00,
            ],
            'CON-TONER-151A' => [
                'supplier' => 'Metro Manila Office Solutions Corporation',
                'lead_time_days' => 5,
                'unit_price' => 5890.00,
            ],
            'CON-MASK-3PLY' => [
                'supplier' => 'Luzon Medical and Laboratory Supply, Inc.',
                'lead_time_days' => 4,
                'unit_price' => 165.00,
            ],
            'CON-LIGHT-LED12' => [
                'supplier' => 'VoltEdge Electrical Supply Corporation',
                'lead_time_days' => 7,
                'unit_price' => 118.00,
            ],
            'CON-HSOAP-500' => [
                'supplier' => 'Greenfield Janitorial and Sanitation Supply',
                'lead_time_days' => 3,
                'unit_price' => 92.50,
            ],
            'AST-CAB-001' => [
                'supplier' => 'PrimeLine Furniture and Storage Solutions',
                'lead_time_days' => 14,
                'unit_price' => 12800.00,
            ],
        ] as $sku => $procurementAttributes) {
            /** @var Product|null $product */
            $product = $products->get($sku);
            /** @var Supplier|null $supplier */
            $supplier = $suppliers->get($procurementAttributes['supplier']);

            if (! $product instanceof Product || ! $supplier instanceof Supplier) {
                throw new \RuntimeException("Missing required procurement seed dependency for {$sku}.");
            }

            $product->forceFill([
                'supplier_id' => $supplier->id,
                'lead_time_days' => $procurementAttributes['lead_time_days'],
                'unit_price' => $procurementAttributes['unit_price'],
            ])->save();
        }

        $timeline = CarbonImmutable::today()->setTime(10, 0);

        $this->syncPurchaseOrder(
            poNumber: 'PO-20261001-0001',
            supplier: $suppliers['Metro Manila Office Solutions Corporation'],
            requestedBy: $requestedBy,
            approvedBy: null,
            status: PurchaseOrderStatus::Draft,
            createdAt: $timeline->subDays(6),
            expectedDeliveryAt: $timeline->addDays(2),
            sentAt: null,
            receivedAt: null,
            tax: 0,
            notes: 'Draft generated from low-stock alert review.',
            lines: [
                ['sku' => 'CON-PAPER-A4', 'qty_ordered' => 150, 'qty_received' => 0, 'unit_price' => 218.50],
                ['sku' => 'CON-INK-BLK', 'qty_ordered' => 24, 'qty_received' => 0, 'unit_price' => 675.00],
            ],
        );

        $this->syncPurchaseOrder(
            poNumber: 'PO-20261001-0002',
            supplier: $suppliers['Luzon Medical and Laboratory Supply, Inc.'],
            requestedBy: $requestedBy,
            approvedBy: $approvedBy,
            status: PurchaseOrderStatus::Sent,
            createdAt: $timeline->subDays(10),
            expectedDeliveryAt: $timeline->subDays(2),
            sentAt: $timeline->subDays(9),
            receivedAt: null,
            tax: 125.50,
            notes: 'Awaiting delivery confirmation from supplier.',
            lines: [
                ['sku' => 'CON-ALCOHOL-70', 'qty_ordered' => 40, 'qty_received' => 0, 'unit_price' => 145.00],
                ['sku' => 'CON-GLOVES-M', 'qty_ordered' => 18, 'qty_received' => 0, 'unit_price' => 380.00],
            ],
        );

        $this->syncPurchaseOrder(
            poNumber: 'PO-20261001-0003',
            supplier: $suppliers['Greenfield Janitorial and Sanitation Supply'],
            requestedBy: $requestedBy,
            approvedBy: $approvedBy,
            status: PurchaseOrderStatus::Partial,
            createdAt: $timeline->subDays(14),
            expectedDeliveryAt: $timeline->subDays(6),
            sentAt: $timeline->subDays(13),
            receivedAt: null,
            tax: 0,
            notes: 'First delivery completed. Remaining cases still in transit.',
            lines: [
                ['sku' => 'CON-DISINF-01', 'qty_ordered' => 20, 'qty_received' => 8, 'unit_price' => 195.00],
            ],
        );

        $this->syncPurchaseOrder(
            poNumber: 'PO-20261001-0004',
            supplier: $suppliers['Northstar Digital Systems Corporation'],
            requestedBy: $requestedBy,
            approvedBy: $approvedBy,
            status: PurchaseOrderStatus::Received,
            createdAt: $timeline->subDays(21),
            expectedDeliveryAt: $timeline->subDays(12),
            sentAt: $timeline->subDays(20),
            receivedAt: $timeline->subDays(11),
            tax: 1500.00,
            notes: 'Fully delivered and inspected.',
            lines: [
                ['sku' => 'AST-LAP-001', 'qty_ordered' => 2, 'qty_received' => 2, 'unit_price' => 38500.00],
                ['sku' => 'AST-PRN-001', 'qty_ordered' => 1, 'qty_received' => 1, 'unit_price' => 14990.00],
            ],
        );

        $this->syncPurchaseOrder(
            poNumber: 'PO-20261001-0005',
            supplier: $suppliers['Metro Manila Office Solutions Corporation'],
            requestedBy: $requestedBy,
            approvedBy: $approvedBy,
            status: PurchaseOrderStatus::Cancelled,
            createdAt: $timeline->subDays(18),
            expectedDeliveryAt: $timeline->subDays(9),
            sentAt: $timeline->subDays(17),
            receivedAt: null,
            tax: 0,
            notes: 'Cancelled after request consolidation with a later PO.',
            lines: [
                ['sku' => 'CON-BAT-AA', 'qty_ordered' => 60, 'qty_received' => 0, 'unit_price' => 32.00],
            ],
        );

        DB::table('purchase_order_sequences')->updateOrInsert(
            ['date_prefix' => '20261001'],
            [
                'last_sequence' => 5,
                'created_at' => $timeline->subDays(21),
                'updated_at' => $timeline,
            ],
        );
    }

    /**
     * @param  array<int, array{sku: string, qty_ordered: int, qty_received: int, unit_price: float}>  $lines
     */
    private function syncPurchaseOrder(
        string $poNumber,
        Supplier $supplier,
        User $requestedBy,
        ?User $approvedBy,
        PurchaseOrderStatus $status,
        CarbonImmutable $createdAt,
        ?CarbonImmutable $expectedDeliveryAt,
        ?CarbonImmutable $sentAt,
        ?CarbonImmutable $receivedAt,
        float $tax,
        ?string $notes,
        array $lines,
    ): void {
        $purchaseOrder = PurchaseOrder::query()->updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'status' => $status,
                'subtotal' => 0,
                'tax' => round($tax, 2),
                'total_amount' => 0,
                'requested_by' => $requestedBy->id,
                'approved_by' => $approvedBy?->id,
                'expected_delivery_at' => $expectedDeliveryAt,
                'sent_at' => $sentAt,
                'received_at' => $receivedAt,
                'notes' => $notes,
                'created_at' => $createdAt,
                'updated_at' => $receivedAt ?? $sentAt ?? $createdAt,
            ],
        );

        foreach ($lines as $line) {
            $product = Product::query()->where('sku', $line['sku'])->firstOrFail();

            PurchaseOrderLine::query()->updateOrCreate(
                [
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                ],
                [
                    'qty_ordered' => $line['qty_ordered'],
                    'qty_received' => $line['qty_received'],
                    'unit_price' => round($line['unit_price'], 2),
                    'subtotal' => round($line['qty_ordered'] * $line['unit_price'], 2),
                ],
            );
        }

        $purchaseOrder->recalculateTotals();
        $purchaseOrder->forceFill([
            'status' => $status,
            'received_at' => $receivedAt,
            'updated_at' => $receivedAt ?? $sentAt ?? $createdAt,
        ])->save();
    }
}
