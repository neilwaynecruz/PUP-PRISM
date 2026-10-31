<?php

namespace Database\Seeders;

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\BookingStatus;
use App\Enums\InventoryCountReviewStatus;
use App\Enums\InventoryCountStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\AssetLifecycleEvent;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\ForecastProfile;
use App\Models\ForecastSnapshot;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Forecasting\DemandForecaster;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsConsumableLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Four years of operational history for the institutional catalog.
 *
 * The window is the 47 complete months before the seeding month; the curated seeders cover
 * the current month, so the dataset spans 48 calendar months. Every record has a natural key
 * derived from its business date, so reruns update in place, later runs roll the window
 * forward, and records that fall out of the window are removed. Choices that would normally
 * be random use a stable hash of the record's key, so the data never depends on run order.
 */
class UatOperationalHistorySeeder extends Seeder
{
    use SeedsConsumableLedger;

    private const HISTORY_MONTHS = 47;

    private const IP_ADDRESS = '10.250.8.30';

    /**
     * Seasonal demand index by month (100 = average): enrollment (Jun–Aug, Jan),
     * examinations (Mar, Oct) and the term breaks (Apr–May, Dec).
     *
     * @var array<int, int>
     */
    private const SEASONALITY = [1 => 110, 2 => 95, 3 => 120, 4 => 80, 5 => 70, 6 => 105, 7 => 115, 8 => 125, 9 => 100, 10 => 120, 11 => 95, 12 => 75];

    /**
     * Requesting offices, the user who files their monthly requisition, and the supplies
     * they draw with a base monthly quantity.
     *
     * @var array<string, array{requester: string, lines: array<string, int>}>
     */
    private const REQUESTING_OFFICES = [
        'College of Engineering' => ['requester' => 'eng.custodian@local.test', 'lines' => ['OFF-PAPER-A4-80' => 18, 'OFF-MARK-BLA-REF' => 12, 'OFF-MARK-BLU-REF' => 8, 'OFF-PEN-BLU' => 30, 'OFF-INJ-BLA' => 3]],
        'College of Education' => ['requester' => 'education.custodian@local.test', 'lines' => ['OFF-PAPER-SHORT-80' => 16, 'OFF-MARK-RED-DIS' => 10, 'OFF-PEN-BLA' => 24, 'OFF-TAPE-MASK-24mm' => 8, 'OFF-FOLDER-FILE-BL' => 20]],
        'College of Business and Accountancy' => ['requester' => 'business.custodian@local.test', 'lines' => ['OFF-PAPER-LONG-80' => 14, 'OFF-PEN-RED' => 12, 'OFF-CLIP-M' => 6, 'OFF-LAS-BLA' => 2, 'OFF-FOLDER-EXPA-MA' => 10]],
        'Registrar Office' => ['requester' => 'registrar.custodian@local.test', 'lines' => ['OFF-PAPER-LEGAL-80' => 12, 'OFF-FOLDER-LONG-BL' => 25, 'OFF-TAPE-SCOT-12mm' => 10, 'OFF-RUB-M' => 4, 'OFF-LAS-BLA' => 2]],
    ];

    /**
     * Supplier for each replenished SKU prefix.
     *
     * @var array<string, string>
     */
    private const SUPPLIER_BY_PREFIX = [
        'OFF-PAPER' => 'Metro Manila Office Solutions Corporation',
        'OFF-FOLDER' => 'Metro Manila Office Solutions Corporation',
        'OFF-CLIP' => 'Metro Manila Office Solutions Corporation',
        'OFF-RUB' => 'Metro Manila Office Solutions Corporation',
        'OFF-PEN' => 'BoardPro Classroom Supplies Corporation',
        'OFF-MARK' => 'BoardPro Classroom Supplies Corporation',
        'OFF-TAPE' => 'SecureHold Packaging and Office Products',
        'OFF-INJ' => 'PrintCore Imaging and Document Systems',
        'OFF-LAS' => 'PrintCore Imaging and Document Systems',
    ];

    /**
     * Supplier for each catalog asset brand.
     *
     * @var array<string, string>
     */
    private const SUPPLIER_BY_BRAND = [
        'Northstar' => 'Northstar Digital Systems Corporation',
        'CorePoint' => 'Northstar Digital Systems Corporation',
        'NetBridge' => 'NetBridge Enterprise Networks Corporation',
        'PrintCore' => 'PrintCore Imaging and Document Systems',
        'DataVault' => 'DataVault Storage Solutions Philippines',
        'VisionCast' => 'VisionCast Audio Visual Technologies, Inc.',
        'CampusCraft' => 'CampusCraft Institutional Furnishings, Inc.',
        'CoolWorks' => 'CoolWorks Mechanical Systems Corporation',
        'AirFlow' => 'AirFlow Ventilation and Facilities Supply',
    ];

    /**
     * Products that receive quarterly historical forecast snapshots.
     *
     * @var array<int, string>
     */
    private const FORECAST_SKUS = ['OFF-PAPER-A4-80', 'OFF-PEN-BLU', 'OFF-MARK-BLA-REF', 'OFF-LAS-BLA'];

    private CarbonImmutable $today;

    private CarbonImmutable $windowStart;

    /** @var array<string, User> */
    private array $users = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new \RuntimeException('Operational seed data cannot be seeded in production.');
        }

        $this->today = CarbonImmutable::today();
        $this->windowStart = $this->today->startOfMonth()->subMonths(self::HISTORY_MONTHS);
        $this->users = User::query()
            ->whereIn('email', [
                'admin@local.test', 'supply@local.test', 'custodian@local.test', 'inventory.analyst@local.test',
                'facilities.custodian@local.test', 'it.expert@local.test', 'director@local.test',
                ...array_column(self::REQUESTING_OFFICES, 'requester'),
            ])
            ->get()
            ->keyBy('email')
            ->all();

        // Historical rows must not trigger live notifications or broadcasts, and one
        // transaction keeps a run all-or-nothing (and fast on SQLite).
        Model::withoutEvents(fn () => DB::transaction(function (): void {
            $this->seedConsumableHistory();

            $catalogAssets = $this->catalogAssets();

            $this->seedAssetAcquisitions($catalogAssets);
            $this->seedAssetTransfers($catalogAssets);
            $this->seedMaintenanceHistory($catalogAssets);
            $this->seedRetirements($catalogAssets);
            $this->seedBookings($catalogAssets);
            $this->seedPhysicalCounts();
            $this->seedForecastHistory();
        }));
    }

    /**
     * Monthly departmental requisitions issued from quarterly purchase-order deliveries.
     */
    private function seedConsumableHistory(): void
    {
        $products = Product::query()
            ->whereIn('sku', collect(self::REQUESTING_OFFICES)->flatMap(fn (array $office): array => array_keys($office['lines']))->unique()->all())
            ->with('stock')
            ->get()
            ->keyBy('sku');

        /** @var array<string, array<int, array<string, mixed>>> $eventsBySku */
        $eventsBySku = [];
        /** @var array<string, array<string, int>> $quarterDemand quarter => sku => qty */
        $quarterDemand = [];
        $requisitionNotes = [];

        foreach ($this->historyMonths() as $month) {
            foreach (self::REQUESTING_OFFICES as $office => $definition) {
                $requester = $this->users[$definition['requester']];
                $notes = "Monthly {$office} supply requisition for {$month->format('F Y')}";
                $requisitionNotes[] = $notes;
                $createdAt = $month->addDays(2 + $this->hash($notes) % 4)->setTime(9, 0);
                $approvedAt = $createdAt->addDay()->setTime(10, 30);
                $issuedAt = $createdAt->addDays(2)->setTime(14, 0);

                $requisition = Requisition::withTrashed()->updateOrCreate(
                    ['requester_id' => $requester->id, 'notes' => $notes],
                    [
                        'requester_position_id' => $requester->position_id,
                        'approver_id' => $this->users['supply@local.test']->id,
                        'approver_position_id' => $this->users['supply@local.test']->position_id,
                        'approved_at' => $approvedAt,
                        'issued_by' => $this->users['inventory.analyst@local.test']->id,
                        'issued_position_id' => $this->users['inventory.analyst@local.test']->position_id,
                        'issued_at' => $issuedAt,
                        'status' => RequisitionStatus::Issued,
                        'requested_ip_address' => self::IP_ADDRESS,
                        'approved_ip_address' => self::IP_ADDRESS,
                        'issued_ip_address' => self::IP_ADDRESS,
                    ],
                );
                $this->stamp($requisition, $createdAt, $issuedAt);

                $lineProductIds = [];

                foreach ($definition['lines'] as $sku => $baseQuantity) {
                    $product = $products[$sku];
                    $quantity = $this->monthlyQuantity($baseQuantity, $month, "{$notes}|{$sku}");
                    $lineProductIds[] = $product->id;

                    RequisitionLine::query()->updateOrCreate(
                        ['requisition_id' => $requisition->id, 'product_id' => $product->id],
                        ['qty_requested' => $quantity, 'qty_issued' => $quantity],
                    );

                    $eventsBySku[$sku][] = [
                        'performed_at' => $issuedAt,
                        'qty_delta' => -$quantity,
                        'movement_type' => 'issue',
                        'performed_by' => $this->users['inventory.analyst@local.test'],
                        'accountable_position_id' => $requester->position_id,
                        'notes' => "Issued against {$notes}",
                        'requisition_id' => $requisition->id,
                    ];

                    $quarterKey = $month->startOfQuarter()->format('Y-m');
                    $quarterDemand[$quarterKey][$sku] = ($quarterDemand[$quarterKey][$sku] ?? 0) + $quantity;
                }

                RequisitionLine::query()
                    ->where('requisition_id', $requisition->id)
                    ->whereNotIn('product_id', $lineProductIds)
                    ->delete();
            }
        }

        $deliveryReferences = $this->seedReplenishment($products, $quarterDemand);
        $this->pruneConsumableHistory($products, $requisitionNotes, $deliveryReferences);

        foreach ($products as $sku => $product) {
            $this->seedConsumableLedger($product->fresh(['stock']), $eventsBySku[$sku] ?? []);
        }
    }

    /**
     * Each quarter, one purchase order per supplier delivers exactly that quarter's demand,
     * received a week before the quarter starts. Lot quantities equal received PO quantities.
     *
     * @param  Collection<string, Product>  $products
     * @param  array<string, array<string, int>>  $quarterDemand
     * @return array<int, string>
     */
    private function seedReplenishment(Collection $products, array $quarterDemand): array
    {
        $suppliers = Supplier::query()->get()->keyBy('name');
        $references = [];
        $poNumbers = [];

        foreach ($quarterDemand as $quarterKey => $demandBySku) {
            $quarter = CarbonImmutable::createFromFormat('Y-m-d', "{$quarterKey}-01")->startOfDay();
            $receivedAt = $quarter->subDays(7)->setTime(9, 0);
            $quarterIndex = intdiv((int) round($this->windowStart->startOfQuarter()->diffInMonths($quarter)), 3);
            $linesBySupplier = [];

            foreach ($demandBySku as $sku => $quantity) {
                $product = $products[$sku];
                $reference = "DR-{$quarter->format('Ym')}-{$sku}";
                $references[] = $reference;

                $lot = StockLot::query()->updateOrCreate(
                    ['product_id' => $product->id, 'reference_no' => $reference],
                    ['received_at' => $receivedAt, 'expires_at' => null, 'qty_received' => $quantity, 'qty_remaining' => 0],
                );

                StockMovement::query()->updateOrCreate(
                    ['movement_type' => 'receive', 'product_id' => $product->id, 'stock_lot_id' => $lot->id, 'notes' => "Quarterly replenishment delivery {$reference}"],
                    [
                        'asset_id' => null, 'requisition_id' => null,
                        'qty_delta' => $quantity, 'qty_before' => 0, 'qty_after' => $quantity,
                        'performed_by' => $this->users['inventory.analyst@local.test']->id,
                        'accountable_position_id' => $this->users['inventory.analyst@local.test']->position_id,
                        'ip_address' => self::IP_ADDRESS, 'performed_at' => $receivedAt,
                    ],
                );

                $supplierName = $this->supplierForSku($sku);
                // Prices rise about 1.2% a quarter across the window.
                $unitPrice = round((float) $product->unit_price * (0.82 + ($quarterIndex * 0.012)), 2);
                $linesBySupplier[$supplierName][] = ['product' => $product, 'qty' => $quantity, 'unit_price' => $unitPrice];
            }

            foreach ($linesBySupplier as $supplierName => $lines) {
                $poNumber = sprintf('PO-HIST-%s-%s', $quarter->format('Ym'), $this->supplierCode($supplierName));
                $poNumbers[] = $poNumber;

                $this->syncReceivedPurchaseOrder(
                    poNumber: $poNumber,
                    supplier: $suppliers[$supplierName],
                    createdAt: $receivedAt->subDays(21),
                    receivedAt: $receivedAt,
                    notes: "Quarterly office-supply replenishment for {$quarter->format('F Y')} – {$quarter->addMonths(2)->format('F Y')}.",
                    lines: $lines,
                );
            }
        }

        $this->pruneHistoricalPurchaseOrders('PO-HIST-%-%', $poNumbers);

        return $references;
    }

    /**
     * Remove requisitions, deliveries, and their movements that have rolled out of the window.
     *
     * @param  Collection<string, Product>  $products
     * @param  array<int, string>  $requisitionNotes
     * @param  array<int, string>  $deliveryReferences
     */
    private function pruneConsumableHistory(Collection $products, array $requisitionNotes, array $deliveryReferences): void
    {
        $staleRequisitionIds = Requisition::withTrashed()
            ->where('notes', 'like', 'Monthly % supply requisition for %')
            ->whereNotIn('notes', $requisitionNotes)
            ->pluck('id');

        if ($staleRequisitionIds->isNotEmpty()) {
            StockMovement::query()->whereIn('requisition_id', $staleRequisitionIds)->delete();
            RequisitionLine::query()->whereIn('requisition_id', $staleRequisitionIds)->delete();
            Requisition::withTrashed()->whereKey($staleRequisitionIds)->forceDelete();
        }

        foreach ($products as $sku => $product) {
            $staleLotIds = StockLot::query()
                ->where('product_id', $product->id)
                ->where('reference_no', 'like', "DR-%-{$sku}")
                ->whereNotIn('reference_no', $deliveryReferences)
                ->pluck('id');

            if ($staleLotIds->isNotEmpty()) {
                StockMovement::query()->whereIn('stock_lot_id', $staleLotIds)->delete();
                StockLot::query()->whereKey($staleLotIds)->delete();
            }
        }
    }

    /**
     * @param  array<int, array{product: Product, qty: int, unit_price: float}>  $lines
     */
    private function syncReceivedPurchaseOrder(
        string $poNumber,
        Supplier $supplier,
        CarbonImmutable $createdAt,
        CarbonImmutable $receivedAt,
        string $notes,
        array $lines,
    ): PurchaseOrder {
        $purchaseOrder = PurchaseOrder::withTrashed()->updateOrCreate(
            ['po_number' => $poNumber],
            [
                'supplier_id' => $supplier->id,
                'status' => PurchaseOrderStatus::Received,
                'subtotal' => 0,
                'tax' => 0,
                'total_amount' => 0,
                'requested_by' => $this->users['inventory.analyst@local.test']->id,
                'approved_by' => $this->users['supply@local.test']->id,
                'expected_delivery_at' => $receivedAt,
                'sent_at' => $createdAt->addDays(2)->setTime(10, 0),
                'received_at' => $receivedAt,
                'notes' => $notes,
            ],
        );

        $productIds = [];

        foreach ($lines as $line) {
            $productIds[] = $line['product']->id;

            PurchaseOrderLine::query()->updateOrCreate(
                ['purchase_order_id' => $purchaseOrder->id, 'product_id' => $line['product']->id],
                [
                    'qty_ordered' => $line['qty'],
                    'qty_received' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'subtotal' => round($line['qty'] * $line['unit_price'], 2),
                ],
            );
        }

        PurchaseOrderLine::query()->where('purchase_order_id', $purchaseOrder->id)->whereNotIn('product_id', $productIds)->delete();

        $purchaseOrder->recalculateTotals();
        $purchaseOrder->forceFill(['status' => PurchaseOrderStatus::Received, 'received_at' => $receivedAt])->save();
        $this->stamp($purchaseOrder, $createdAt, $receivedAt);
        $this->syncAuditLog('receive', $purchaseOrder, "Purchase order {$poNumber} received in full.", $receivedAt->addHour(), 'inventory.analyst@local.test');

        return $purchaseOrder;
    }

    /**
     * @param  array<int, string>  $currentPoNumbers
     */
    private function pruneHistoricalPurchaseOrders(string $pattern, array $currentPoNumbers): void
    {
        $staleIds = PurchaseOrder::withTrashed()
            ->where('po_number', 'like', $pattern)
            ->whereNotIn('po_number', $currentPoNumbers)
            ->pluck('id');

        if ($staleIds->isEmpty()) {
            return;
        }

        PurchaseOrderLine::query()->whereIn('purchase_order_id', $staleIds)->delete();
        AuditLog::query()->where('model_type', 'PurchaseOrder')->whereIn('model_id', $staleIds)->delete();
        PurchaseOrder::withTrashed()->whereKey($staleIds)->forceDelete();
    }

    /**
     * Catalog assets (serial-numbered) with their product and room.
     *
     * @return Collection<int, Asset>
     */
    private function catalogAssets(): Collection
    {
        return Asset::query()
            ->with(['product:id,sku,name,brand,category_id,is_bookable,unit_price', 'product.category:id,name', 'currentLocation:id,code,facility_group'])
            ->where('serial_number', 'like', 'PUP-%')
            ->whereNotNull('acquisition_date')
            ->orderBy('tag_code')
            ->get();
    }

    /**
     * One acquisition purchase order per supplier and month, matching the acquired assets,
     * plus an acquisition lifecycle record for every asset.
     *
     * @param  Collection<int, Asset>  $assets
     */
    private function seedAssetAcquisitions(Collection $assets): void
    {
        $suppliers = Supplier::query()->get()->keyBy('name');
        $poNumbers = [];

        $groups = $assets
            ->filter(fn (Asset $asset): bool => CarbonImmutable::parse($asset->acquisition_date)->isBefore($this->today))
            ->groupBy(fn (Asset $asset): string => CarbonImmutable::parse($asset->acquisition_date)->format('Ym').'|'.(self::SUPPLIER_BY_BRAND[$asset->product->brand] ?? 'Metro Manila Office Solutions Corporation'));

        foreach ($groups as $groupKey => $groupAssets) {
            [$yearMonth, $supplierName] = explode('|', $groupKey);
            $receivedAt = $groupAssets->map(fn (Asset $asset): CarbonImmutable => CarbonImmutable::parse($asset->acquisition_date))->min()->setTime(10, 0);
            $poNumber = sprintf('PO-ACQ-%s-%s', $yearMonth, $this->supplierCode($supplierName));
            $poNumbers[] = $poNumber;
            $lines = $groupAssets->groupBy('product_id')->map(fn (Collection $productAssets): array => [
                'product' => $productAssets->first()->product,
                'qty' => $productAssets->count(),
                'unit_price' => (float) $productAssets->first()->acquisition_cost,
            ])->values()->all();

            $this->syncReceivedPurchaseOrder(
                poNumber: $poNumber,
                supplier: $suppliers[$supplierName],
                createdAt: $receivedAt->subDays(30),
                receivedAt: $receivedAt,
                notes: 'Equipment and fixtures acquisition received, inspected, and tagged by the Supply and Property Management Office.',
                lines: $lines,
            );
        }

        $this->pruneHistoricalPurchaseOrders('PO-ACQ-%', $poNumbers);

        foreach ($assets as $asset) {
            $acquiredAt = CarbonImmutable::parse($asset->acquisition_date)->setTime(11, 0);

            AssetLifecycleEvent::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'event_type' => AssetLifecycleEventType::Acquisition, 'supporting_reference' => "ACQ-{$asset->tag_code}"],
                [
                    'status' => ApprovalStatus::Approved,
                    'from_lifecycle_status' => null, 'to_lifecycle_status' => AssetLifecycleStatus::Active,
                    'from_condition_status' => null, 'to_condition_status' => AssetConditionStatus::Serviceable,
                    'reason' => 'New acquisition received, inspected, and recorded in the property register.',
                    'method' => 'Purchase', 'proposed_for' => null, 'occurred_at' => $acquiredAt,
                    'initiated_by' => $this->users['custodian@local.test']->id,
                    'approved_by' => $this->users['admin@local.test']->id, 'approved_at' => $acquiredAt->addHours(3),
                    'notes' => "Serial {$asset->serial_number}; acquisition cost ".number_format((float) $asset->acquisition_cost, 2).'.',
                ],
            );
        }
    }

    /**
     * New assets are received at the property holding area and transferred to their room.
     *
     * @param  Collection<int, Asset>  $assets
     */
    private function seedAssetTransfers(Collection $assets): void
    {
        $holdingArea = Location::query()->where('code', 'SPMO-PROP')->first();

        if ($holdingArea === null) {
            return;
        }

        foreach ($assets as $asset) {
            $transferDate = CarbonImmutable::parse($asset->acquisition_date)->addDays(5 + $this->hash($asset->tag_code) % 10)->setTime(13, 0);

            if ($asset->current_location_id === null || $asset->current_location_id === $holdingArea->id || ! $transferDate->isBefore($this->today)) {
                AssetTransfer::query()->where('asset_id', $asset->id)->where('reason', 'like', 'Deployment from the property holding area%')->delete();

                continue;
            }

            $reason = "Deployment from the property holding area to {$asset->currentLocation?->code}";

            // An asset moved to another room since the last run keeps only its current deployment.
            AssetTransfer::query()
                ->where('asset_id', $asset->id)
                ->where('reason', 'like', 'Deployment from the property holding area%')
                ->where('reason', '!=', $reason)
                ->delete();

            $transfer = AssetTransfer::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'reason' => $reason],
                [
                    'from_location_id' => $holdingArea->id, 'to_location_id' => $asset->current_location_id,
                    'from_position_id' => $this->users['custodian@local.test']->position_id, 'to_position_id' => $asset->position_id,
                    'status' => AssetTransferStatus::Approved, 'transfer_date' => $transferDate->toDateString(),
                    'initiated_by' => $this->users['custodian@local.test']->id,
                    'approved_by' => $this->users['admin@local.test']->id, 'approved_at' => $transferDate,
                    'notes' => 'Installed and acknowledged by the receiving office.',
                ],
            );
            $this->stamp($transfer, $transferDate->subDay(), $transferDate);
            $this->syncAuditLog('approve', $transfer, "Asset {$asset->tag_code} transferred to {$asset->currentLocation?->code}.", $transferDate, 'admin@local.test');
        }
    }

    /**
     * Preventive maintenance at each asset's interval, plus occasional corrective repairs.
     *
     * @param  Collection<int, Asset>  $assets
     */
    private function seedMaintenanceHistory(Collection $assets): void
    {
        $providers = [
            'Facilities Equipment' => 'CoolWorks Mechanical Systems Corporation',
            'Equipment' => 'Information Technology Office',
        ];
        // History covers completed months only, so day-to-day reruns within a month stay identical.
        $lastDue = $this->today->startOfMonth();

        foreach ($assets as $asset) {
            $acquired = CarbonImmutable::parse($asset->acquisition_date);
            $keptIds = [];
            $interval = (int) ($asset->maintenance_interval_days ?? 0);
            $category = $asset->product->category?->name ?? 'Equipment';

            if ($interval > 0) {
                for ($cycle = 1; $cycle <= 12; $cycle++) {
                    $scheduled = $acquired->addDays($interval * $cycle);

                    if (! $scheduled->isBefore($lastDue)) {
                        break;
                    }

                    $keptIds[] = $this->syncMaintenance($asset, MaintenanceType::Preventive, $scheduled, [
                        'reported_issue' => 'Scheduled preventive maintenance',
                        'description' => $category === 'Facilities Equipment'
                            ? 'Cleaned filters and coils, checked electrical connections, and verified operating performance.'
                            : 'Cleaned internals, updated firmware, and ran hardware diagnostics.',
                        'service_provider' => $providers[$category] ?? 'Facilities Management Office',
                        'repair_cost' => $category === 'Facilities Equipment' ? 850 + ($this->hash($asset->tag_code.$cycle) % 6) * 150 : 0,
                        'findings' => 'Operating within specification.',
                    ]);
                }
            }

            // Roughly one asset in nine needs one corrective repair during its life.
            $repairDate = $acquired->addDays(150 + $this->hash($asset->tag_code) % 240);

            if ($this->hash('repair'.$asset->tag_code) % 9 === 0 && $repairDate->isBefore($lastDue)) {
                $keptIds[] = $this->syncMaintenance($asset, MaintenanceType::Corrective, $repairDate, [
                    'reported_issue' => $category === 'Facilities Equipment' ? 'Unit stopped cooling or running intermittently.' : 'Device fails to start or shuts down unexpectedly.',
                    'description' => 'Diagnosed the fault and replaced the failed component.',
                    'service_provider' => $providers[$category] ?? 'Facilities Management Office',
                    'repair_cost' => 1500 + ($this->hash('cost'.$asset->tag_code) % 10) * 500,
                    'findings' => 'Component failure from normal wear; unit restored to service.',
                ]);
            }

            AssetMaintenance::query()
                ->where('asset_id', $asset->id)
                ->whereIn('maintenance_type', [MaintenanceType::Preventive->value, MaintenanceType::Corrective->value])
                ->where('notes', 'Historical maintenance record')
                ->whereNotIn('id', $keptIds)
                ->delete();
        }
    }

    /**
     * @param  array{reported_issue: string, description: string, service_provider: string, repair_cost: int|float, findings: string}  $details
     */
    private function syncMaintenance(Asset $asset, MaintenanceType $type, CarbonImmutable $scheduled, array $details): int
    {
        $completedAt = $scheduled->addDays($this->hash($asset->tag_code.$scheduled->toDateString()) % 3)->setTime(15, 0);

        $maintenance = AssetMaintenance::query()->updateOrCreate(
            // A Carbon key is formatted exactly as Eloquent stores the date, on SQLite and PostgreSQL.
            ['asset_id' => $asset->id, 'maintenance_type' => $type, 'scheduled_for' => $scheduled->startOfDay()],
            [
                ...$details,
                'status' => MaintenanceStatus::Completed,
                'reported_by' => $this->users['facilities.custodian@local.test']->id,
                'reported_date' => $scheduled->subDays($type === MaintenanceType::Preventive ? 7 : 1)->toDateString(),
                'assigned_to' => $this->users['it.expert@local.test']->id,
                'completed_at' => $completedAt,
                'notes' => 'Historical maintenance record',
            ],
        );
        $this->stamp($maintenance, $scheduled->subDays(7)->setTime(8, 0), $completedAt);

        return (int) $maintenance->id;
    }

    /**
     * Storage devices and printers from the first acquisition wave are retired after
     * roughly four years of service.
     *
     * @param  Collection<int, Asset>  $assets
     */
    private function seedRetirements(Collection $assets): void
    {
        $retireable = ['AST-HDD-2TB', 'AST-USB-128', 'AST-PRN-INK'];

        foreach ($assets as $asset) {
            $acquired = CarbonImmutable::parse($asset->acquisition_date);
            $retiredAt = $acquired->addMonths(42)->setTime(10, 0);

            if (! in_array($asset->product->sku, $retireable, true) || ! $retiredAt->isBefore($this->today)) {
                continue;
            }

            AssetLifecycleEvent::query()->updateOrCreate(
                ['asset_id' => $asset->id, 'event_type' => AssetLifecycleEventType::Retirement, 'supporting_reference' => "RET-{$asset->tag_code}"],
                [
                    'status' => ApprovalStatus::Approved,
                    'from_lifecycle_status' => AssetLifecycleStatus::Active, 'to_lifecycle_status' => AssetLifecycleStatus::Retired,
                    'from_condition_status' => AssetConditionStatus::Serviceable, 'to_condition_status' => AssetConditionStatus::Unserviceable,
                    'reason' => 'End of useful life; repeated failures and outdated capacity.', 'method' => 'Beyond economical repair',
                    'proposed_for' => $retiredAt->toDateString(), 'occurred_at' => $retiredAt,
                    'initiated_by' => $this->users['custodian@local.test']->id,
                    'approved_by' => $this->users['admin@local.test']->id, 'approved_at' => $retiredAt->addDay(),
                    'notes' => 'Removed from service and tagged as unserviceable pending disposal.',
                ],
            );

            // Retired assets are no longer available for use, booking, or handover.
            $asset->forceFill([
                'status' => AssetStatus::Unserviceable,
                'lifecycle_status' => AssetLifecycleStatus::Retired,
                'condition_status' => AssetConditionStatus::Unserviceable,
            ])->save();
            $this->syncAuditLog('approve', $asset, "Asset {$asset->tag_code} retired after {$acquired->diffInYears($retiredAt, true)} years of service.", $retiredAt->addDay(), 'admin@local.test');
        }
    }

    /**
     * Bookings of bookable equipment, busiest in event months. Each booking in a month uses a
     * different day, so no two bookings of the same asset can overlap.
     *
     * @param  Collection<int, Asset>  $assets
     */
    private function seedBookings(Collection $assets): void
    {
        $bookable = $assets->filter(fn (Asset $asset): bool => (bool) $asset->product->is_bookable)->values();
        $requesters = collect(self::REQUESTING_OFFICES)->pluck('requester')->push('director@local.test')->values();
        $purposes = ['Departmental orientation', 'Faculty training session', 'Thesis defense', 'Board meeting', 'Student organization event', 'Accreditation visit', 'Seminar and workshop'];
        $keptKeys = [];

        if ($bookable->isEmpty()) {
            return;
        }

        foreach ($this->historyMonths() as $month) {
            $count = in_array($month->month, [2, 3, 8, 10], true) ? 6 : 3;

            for ($slot = 0; $slot < $count; $slot++) {
                $key = $month->format('Y-m').'#'.$slot;
                $asset = $bookable[$this->hash("asset{$key}") % $bookable->count()];
                $startAt = $month->addDays(2 + ($slot * 4))->setTime($slot % 2 === 0 ? 8 : 13, 0);

                if (CarbonImmutable::parse($asset->acquisition_date)->isAfter($startAt)) {
                    continue;
                }

                $keptKeys[] = $asset->id.'|'.$startAt->toDateTimeString();
                $requester = $this->users[$requesters[$this->hash("req{$key}") % $requesters->count()]];
                $outcome = $this->hash("outcome{$key}") % 12;
                $status = match (true) {
                    $outcome === 0 => BookingStatus::Rejected,
                    $outcome === 1 => BookingStatus::Cancelled,
                    default => BookingStatus::Approved,
                };
                $approver = $status === BookingStatus::Cancelled ? null : $this->users['custodian@local.test'];

                $booking = Booking::withTrashed()->updateOrCreate(
                    ['asset_id' => $asset->id, 'start_at' => $startAt],
                    [
                        'requester_id' => $requester->id, 'requester_position_id' => $requester->position_id,
                        'approver_id' => $approver?->id, 'approver_position_id' => $approver?->position_id,
                        'requested_ip_address' => self::IP_ADDRESS, 'approved_ip_address' => $approver === null ? null : self::IP_ADDRESS,
                        'end_at' => $startAt->addHours(4),
                        'status' => $status,
                        'purpose' => $purposes[$this->hash("purpose{$key}") % count($purposes)].' – '.$month->format('F Y'),
                    ],
                );
                $this->stamp($booking, $startAt->subDays(7), $startAt->subDays(5));
            }
        }

        Booking::withTrashed()
            ->whereIn('asset_id', $bookable->pluck('id'))
            ->where('start_at', '<', $this->today->startOfMonth())
            ->get(['id', 'asset_id', 'start_at'])
            ->reject(fn (Booking $booking): bool => in_array($booking->asset_id.'|'.CarbonImmutable::parse($booking->start_at)->toDateTimeString(), $keptKeys, true))
            ->each(fn (Booking $booking) => $booking->forceDelete());
    }

    /**
     * Quarterly asset counts in rotating rooms, all reconciled without variance.
     */
    private function seedPhysicalCounts(): void
    {
        $locations = Location::query()
            ->whereIn('facility_group', ['academic', 'administrative', 'events', 'sports'])
            ->whereHas('assets')
            ->orderBy('code')
            ->get();
        $references = [];

        if ($locations->isEmpty()) {
            return;
        }

        $quarter = $this->windowStart->startOfQuarter()->addQuarter();

        for (; $quarter->addMonths(3)->isBefore($this->today->startOfQuarter()->addDay()); $quarter = $quarter->addQuarter()) {
            $quarterEnd = $quarter->endOfQuarter()->subDays(3)->setTime(9, 0);
            $location = $locations[$this->hash($quarter->format('Y-m')) % $locations->count()];
            $reference = sprintf('PHY-HIST-%sQ%d-%s', $quarter->format('Y'), $quarter->quarter, $location->code);
            $references[] = $reference;

            $session = InventoryCountSession::query()->updateOrCreate(
                ['reference_no' => $reference],
                [
                    'location_id' => $location->id,
                    'created_by' => $this->users['inventory.analyst@local.test']->id,
                    'assigned_to' => $this->users['custodian@local.test']->id,
                    'approved_by' => $this->users['admin@local.test']->id,
                    'counting_date' => $quarterEnd->toDateString(), 'scope' => "Quarterly property count – {$location->name}",
                    'status' => InventoryCountStatus::Completed, 'snapshot_at' => $quarterEnd,
                    // The last ledger movement recorded before the count snapshot.
                    'movement_cutoff_id' => StockMovement::query()->where('performed_at', '<=', $quarterEnd)->max('id')
                        ?? StockMovement::query()->min('id'),
                    'submitted_at' => $quarterEnd->setTime(16, 0), 'approved_at' => $quarterEnd->addDay()->setTime(10, 0),
                    'completed_at' => $quarterEnd->addDay()->setTime(11, 0), 'cancelled_at' => null,
                    'remarks' => 'All tagged property in the room was located and verified.',
                ],
            );
            $this->stamp($session, $quarterEnd->subDays(2), $quarterEnd->addDay()->setTime(11, 0));

            $assetIds = Asset::query()
                ->where('current_location_id', $location->id)
                ->where('lifecycle_status', AssetLifecycleStatus::Active)
                ->whereDate('acquisition_date', '<=', $quarterEnd->toDateString())
                ->pluck('id');

            foreach ($assetIds as $assetId) {
                InventoryCountLine::query()->updateOrCreate(
                    ['inventory_count_session_id' => $session->id, 'asset_id' => $assetId],
                    [
                        'product_id' => Asset::query()->whereKey($assetId)->value('product_id'), 'stock_lot_id' => null,
                        'expected_qty_snapshot' => 1, 'counted_qty' => 1, 'expected_qty_at_count' => 1, 'variance_qty' => 0,
                        'counted_by' => $this->users['custodian@local.test']->id, 'counted_at' => $quarterEnd->setTime(11, 0),
                        'review_status' => InventoryCountReviewStatus::Approved, 'count_notes' => 'Tag and serial verified.',
                        'review_notes' => null, 'reviewed_by' => $this->users['admin@local.test']->id,
                        'reviewed_at' => $quarterEnd->addDay()->setTime(10, 0), 'adjustment_movement_id' => null,
                    ],
                );
            }

            InventoryCountLine::query()->where('inventory_count_session_id', $session->id)->whereNotIn('asset_id', $assetIds)->delete();
        }

        $staleIds = InventoryCountSession::query()->where('reference_no', 'like', 'PHY-HIST-%')->whereNotIn('reference_no', $references)->pluck('id');
        InventoryCountLine::query()->whereIn('inventory_count_session_id', $staleIds)->delete();
        InventoryCountSession::query()->whereKey($staleIds)->delete();
    }

    /**
     * Quarter-end forecast snapshots computed by the application's DemandForecaster from the
     * seeded issue history, so every snapshot is traceable to actual movements. The forecaster
     * is used directly: the scheduled command would also raise alerts and send notifications
     * for stock levels that no longer apply.
     */
    private function seedForecastHistory(): void
    {
        $forecaster = app(DemandForecaster::class);
        $products = Product::query()->whereIn('sku', self::FORECAST_SKUS)->get();

        foreach ($products as $product) {
            ForecastProfile::query()->updateOrCreate(
                ['product_id' => $product->id],
                [
                    'method' => str_starts_with($product->sku, 'OFF-PAPER') ? 'seasonal' : 'moving_average',
                    'lookback_days' => 365, 'forecast_horizon_days' => 60,
                    'lead_time_days' => (int) ($product->lead_time_days ?? 14), 'safety_stock_days' => 10,
                    'smoothing_factor' => null, 'trend_factor' => null, 'is_active' => true,
                ],
            );

            $dates = [];

            // Snapshots need a year of history, so they start one year into the window.
            for ($quarter = $this->windowStart->addYear()->startOfQuarter(); $quarter->isBefore($this->today->startOfQuarter()); $quarter = $quarter->addQuarter()) {
                $asOf = $quarter->endOfQuarter();
                $dates[] = $asOf->toDateString();

                foreach ($forecaster->forecastAll($asOf, $product->id) as $productId => $result) {
                    ForecastSnapshot::query()->updateOrCreate(
                        ['product_id' => $productId, 'forecast_date' => $result->generatedAt->startOfDay()],
                        $result->toSnapshotPayload(),
                    );
                }
            }

            $product->forecastSnapshots()
                ->whereDate('forecast_date', '<', $this->today->toDateString())
                ->get(['id', 'forecast_date'])
                ->reject(fn (ForecastSnapshot $snapshot): bool => in_array(CarbonImmutable::parse($snapshot->forecast_date)->toDateString(), $dates, true))
                ->each->delete();
        }
    }

    private function syncAuditLog(string $action, Model $model, string $description, CarbonImmutable $at, string $actorEmail): void
    {
        $log = AuditLog::query()->updateOrCreate(
            ['action' => $action, 'model_type' => class_basename($model), 'model_id' => $model->getKey(), 'description' => $description],
            ['user_id' => $this->users[$actorEmail]->id, 'ip_address' => self::IP_ADDRESS, 'user_agent' => 'PRISM UAT history'],
        );
        $this->stamp($log, $at, $at);
    }

    /**
     * Set historical timestamps without letting Eloquent replace them with "now".
     */
    private function stamp(Model $model, CarbonImmutable $createdAt, CarbonImmutable $updatedAt): void
    {
        $model->timestamps = false;
        $model->forceFill(['created_at' => $createdAt, 'updated_at' => $updatedAt])->save();
        $model->timestamps = true;
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    private function historyMonths(): array
    {
        $months = [];

        for ($offset = self::HISTORY_MONTHS; $offset >= 1; $offset--) {
            $months[] = $this->today->startOfMonth()->subMonths($offset);
        }

        return $months;
    }

    private function monthlyQuantity(int $base, CarbonImmutable $month, string $key): int
    {
        // Demand grows ~10% a year, follows the academic calendar, and varies ±10% per request.
        $yearsIntoWindow = $this->windowStart->diffInMonths($month) / 12;
        $variation = 90 + ($this->hash($key) % 21);

        return max(1, (int) round($base * self::SEASONALITY[$month->month] / 100 * (0.85 + $yearsIntoWindow * 0.1) * $variation / 100));
    }

    private function supplierForSku(string $sku): string
    {
        foreach (self::SUPPLIER_BY_PREFIX as $prefix => $supplier) {
            if (str_starts_with($sku, $prefix)) {
                return $supplier;
            }
        }

        return 'Metro Manila Office Solutions Corporation';
    }

    private function supplierCode(string $supplierName): string
    {
        return strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $supplierName) ?? 'SUPPLIER', 0, 6));
    }

    private function hash(string $value): int
    {
        return crc32($value);
    }
}
