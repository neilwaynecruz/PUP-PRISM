<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementReasonCode;
use App\Models\Asset;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryRealtimeService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly InventoryRealtimeService $realtime,
    ) {}

    public function receive(User $user, Product $product, array $payload, ?string $ipAddress = null): void
    {
        $result = DB::transaction(fn (): array => $this->performReceipt(
            user: $user,
            product: $product,
            payload: $payload,
            ipAddress: $ipAddress,
        ));

        $this->dispatchReceiptRealtime($result);
    }

    public function receiveConsumable(
        User $user,
        Product $product,
        int $qty,
        ?string $referenceNo = null,
        ?CarbonImmutable $receivedAt = null,
        ?CarbonImmutable $expiresAt = null,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): void {
        $result = DB::transaction(fn (): array => $this->performReceipt(
            user: $user,
            product: $product,
            payload: [
                'qty' => $qty,
                'reference_no' => $referenceNo,
                'received_at' => $receivedAt,
                'expires_at' => $expiresAt,
                'notes' => $notes,
                'tag_codes' => null,
            ],
            ipAddress: $ipAddress,
        ));

        $this->dispatchReceiptRealtime($result);
    }

    /**
     * @param  array<int, string>  $tagCodes
     */
    public function receiveAssets(User $user, Product $product, array $tagCodes, ?string $notes = null, ?string $ipAddress = null): void
    {
        $result = DB::transaction(fn (): array => $this->performReceipt(
            user: $user,
            product: $product,
            payload: [
                'qty' => null,
                'reference_no' => null,
                'received_at' => null,
                'expires_at' => null,
                'notes' => $notes,
                'tag_codes' => $tagCodes,
            ],
            ipAddress: $ipAddress,
        ));

        $this->dispatchReceiptRealtime($result);
    }

    public function receiveAgainstPurchaseOrderLine(
        User $user,
        PurchaseOrderLine $purchaseOrderLine,
        array $payload,
        ?string $ipAddress = null,
    ): void {
        $result = DB::transaction(function () use ($user, $purchaseOrderLine, $payload, $ipAddress): array {
            $line = PurchaseOrderLine::query()
                ->with(['product:id,sku,name,type', 'purchaseOrder'])
                ->lockForUpdate()
                ->findOrFail($purchaseOrderLine->id);

            /** @var PurchaseOrder $purchaseOrder */
            $purchaseOrder = PurchaseOrder::query()
                ->lockForUpdate()
                ->findOrFail($line->purchase_order_id);

            if (! $purchaseOrder->canReceive()) {
                throw new \RuntimeException('Only sent or partially received purchase orders can accept receipts.');
            }

            $receivedQuantity = $this->resolveReceivedQuantity($line, $payload);

            if ($receivedQuantity > $line->remainingQty()) {
                throw new \RuntimeException("Receipt exceeds remaining quantity for product {$line->product?->sku}.");
            }

            $result = $this->performReceipt(
                user: $user,
                product: $line->product ?? Product::query()->findOrFail($line->product_id),
                payload: [
                    ...$payload,
                    'reference_no' => $payload['reference_no'] ?? $purchaseOrder->po_number,
                    'notes' => $payload['notes'] ?? "Received against {$purchaseOrder->po_number}.",
                ],
                ipAddress: $ipAddress,
            );

            $line->forceFill([
                'qty_received' => $line->qty_received + $receivedQuantity,
            ])->save();

            $purchaseOrder->refreshReceiptStatus();

            return $result;
        });

        $this->dispatchReceiptRealtime($result);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function batchReceive(User $user, array $lines, ?string $ipAddress = null): void
    {
        /** @var array<int, array{product: Product, on_hand_qty: int|null, delta: int}> $stockChanges */
        $stockChanges = DB::transaction(function () use ($user, $lines, $ipAddress): array {
            $changes = [];

            foreach ($lines as $line) {
                $product = Product::query()->where('sku', $line['sku'])->firstOrFail();

                $changes[] = $this->performReceipt(
                    user: $user,
                    product: $product,
                    payload: $line,
                    ipAddress: $ipAddress,
                );
            }

            return $changes;
        });

        foreach ($stockChanges as $change) {
            $this->dispatchReceiptRealtime($change);
        }
    }

    /**
     * @param  array<int, array{id: int, qty_to_issue: int}>|null  $linePayloads
     */
    public function issueRequisition(
        User $user,
        Requisition $requisition,
        ?array $linePayloads = null,
        bool $markAsBackordered = false,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): void {
        /** @var array{stock_changes: array<int, array{product: Product, on_hand_qty: int, delta: int, requisition_id: int}>, low_stock_alerts: array<int, array{product: Product, on_hand_qty: int}>} $result */
        $result = DB::transaction(function () use ($user, $requisition, $linePayloads, $markAsBackordered, $notes, $ipAddress): array {
            $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            $stockChanges = [];
            $lowStockAlerts = [];

            if (! in_array($requisition->status, [
                RequisitionStatus::Approved,
                RequisitionStatus::PartiallyIssued,
                RequisitionStatus::Backordered,
            ], true)) {
                throw new \RuntimeException('Only approved or partially fulfilled requisitions can be issued.');
            }

            /** @var Collection<int, RequisitionLine> $lines */
            $lines = RequisitionLine::query()
                ->where('requisition_id', $requisition->id)
                ->with('product:id,sku,type')
                ->lockForUpdate()
                ->get();

            /** @var array<int, int> $requestedQuantities */
            $requestedQuantities = collect($linePayloads ?? [])
                ->mapWithKeys(fn (array $line): array => [(int) $line['id'] => (int) $line['qty_to_issue']])
                ->all();

            $issuedAnyQuantity = false;

            foreach ($lines as $line) {
                $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->firstOrFail();

                if ($product->type !== ProductType::Consumable) {
                    throw new \RuntimeException('Only consumable requisition lines can be issued here.');
                }

                $qty = array_key_exists($line->id, $requestedQuantities)
                    ? $requestedQuantities[$line->id]
                    : $line->remainingQuantity();

                if ($qty === 0) {
                    continue;
                }

                $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();

                if ($stock->on_hand_qty < $qty) {
                    throw new \RuntimeException("Insufficient stock for SKU {$product->sku}.");
                }

                $runningOnHand = (int) $stock->on_hand_qty;
                $remaining = $qty;

                /** @var Collection<int, StockLot> $lots */
                $lots = StockLot::query()
                    ->where('product_id', $product->id)
                    ->where('qty_remaining', '>', 0)
                    ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expires_at')
                    ->orderBy('received_at')
                    ->lockForUpdate()
                    ->get();

                foreach ($lots as $lot) {
                    if ($remaining <= 0) {
                        break;
                    }

                    $consume = min($remaining, $lot->qty_remaining);

                    $lot->decrement('qty_remaining', $consume);
                    $qtyBefore = $runningOnHand;
                    $qtyAfter = $qtyBefore - $consume;

                    StockMovement::create([
                        'movement_type' => 'issue',
                        'product_id' => $product->id,
                        'stock_lot_id' => $lot->id,
                        'asset_id' => null,
                        'requisition_id' => $requisition->id,
                        'qty_delta' => -$consume,
                        'qty_before' => $qtyBefore,
                        'qty_after' => $qtyAfter,
                        'performed_by' => $user->id,
                        'accountable_position_id' => $requisition->requester_position_id,
                        'ip_address' => $ipAddress,
                        'performed_at' => CarbonImmutable::now(),
                        'notes' => $notes,
                    ]);

                    $runningOnHand = $qtyAfter;
                    $remaining -= $consume;
                }

                if ($remaining !== 0) {
                    throw new \RuntimeException("Unable to allocate lots for SKU {$product->sku}.");
                }

                $stock->decrement('on_hand_qty', $qty);

                $line->update([
                    'qty_issued' => $line->qty_issued + $qty,
                ]);

                $issuedAnyQuantity = true;

                $freshStock = $stock->fresh();
                if ($freshStock !== null) {
                    $stockChanges[] = [
                        'product' => $product->fresh() ?? $product,
                        'on_hand_qty' => (int) $freshStock->on_hand_qty,
                        'delta' => -$qty,
                        'requisition_id' => $requisition->id,
                    ];

                    if (
                        $product->reorder_threshold !== null
                        && $freshStock->on_hand_qty <= $product->reorder_threshold
                    ) {
                        $lowStockAlerts[] = [
                            'product' => $product->fresh() ?? $product,
                            'on_hand_qty' => (int) $freshStock->on_hand_qty,
                        ];
                    }
                }
            }

            if (! $issuedAnyQuantity && ! $markAsBackordered) {
                throw new \RuntimeException('No requisition quantities were selected for issuance.');
            }

            $hasRemainingQuantities = $lines->contains(
                fn (RequisitionLine $line) => $line->fresh()?->remainingQuantity() > 0,
            );

            $status = match (true) {
                ! $hasRemainingQuantities => RequisitionStatus::Issued,
                $markAsBackordered => RequisitionStatus::Backordered,
                default => RequisitionStatus::PartiallyIssued,
            };

            $requisition->update([
                'status' => $status,
                'issued_by' => $issuedAnyQuantity ? $user->id : $requisition->issued_by,
                'issued_position_id' => $issuedAnyQuantity ? $user->position_id : $requisition->issued_position_id,
                'issued_ip_address' => $issuedAnyQuantity ? $ipAddress : $requisition->issued_ip_address,
                'issued_at' => $issuedAnyQuantity ? CarbonImmutable::now() : $requisition->issued_at,
                'notes' => $notes ?? $requisition->notes,
            ]);

            return [
                'stock_changes' => $stockChanges,
                'low_stock_alerts' => $lowStockAlerts,
            ];
        });

        foreach ($result['stock_changes'] as $stockChange) {
            $this->realtime->stockIssued(
                $stockChange['product'],
                $stockChange['on_hand_qty'],
                $stockChange['delta'],
                $stockChange['requisition_id'],
            );
        }

        foreach ($result['low_stock_alerts'] as $lowStockAlert) {
            $this->notifications->lowStockAlert(
                $lowStockAlert['product'],
                $lowStockAlert['on_hand_qty'],
            );
        }
    }

    public function adjustConsumableStock(
        User $user,
        Product $product,
        int $qtyDelta,
        StockMovementReasonCode $reasonCode,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): void {
        $result = DB::transaction(fn (): array => $this->applyConsumableVariance(
            user: $user,
            product: $product,
            qtyDelta: $qtyDelta,
            movementType: 'adjustment',
            reasonCode: $reasonCode,
            countedQty: null,
            notes: $notes,
            ipAddress: $ipAddress,
        ));

        $this->dispatchInventoryDeltaRealtime($result);
    }

    public function recordCycleCount(
        User $user,
        Product $product,
        int $countedQuantity,
        StockMovementReasonCode $reasonCode,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): void {
        $result = DB::transaction(function () use ($user, $product, $countedQuantity, $reasonCode, $notes, $ipAddress): array {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $stock = ProductStock::query()->firstOrCreate(
                ['product_id' => $lockedProduct->id],
                ['on_hand_qty' => 0],
            );

            $stock = ProductStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $qtyDelta = $countedQuantity - (int) $stock->on_hand_qty;

            return $this->applyConsumableVariance(
                user: $user,
                product: $lockedProduct,
                qtyDelta: $qtyDelta,
                movementType: 'cycle_count',
                reasonCode: $reasonCode,
                countedQty: $countedQuantity,
                notes: $notes,
                ipAddress: $ipAddress,
            );
        });

        $this->dispatchInventoryDeltaRealtime($result);
    }

    /**
     * @return array{product: Product, on_hand_qty: int, delta: int}
     */
    private function performReceipt(
        User $user,
        Product $product,
        array $payload,
        ?string $ipAddress = null,
    ): array {
        if ($product->type === ProductType::Consumable) {
            return $this->performConsumableReceipt(
                user: $user,
                product: $product,
                qty: (int) ($payload['qty'] ?? $payload['qty_received'] ?? 0),
                referenceNo: $payload['reference_no'] ?? null,
                receivedAt: $payload['received_at'] ?? null,
                expiresAt: $payload['expires_at'] ?? null,
                notes: $payload['notes'] ?? null,
                ipAddress: $ipAddress,
            );
        }

        /** @var array<int, string> $tagCodes */
        $tagCodes = $payload['tag_codes'] ?? [];

        return $this->performAssetReceipt(
            user: $user,
            product: $product,
            tagCodes: $tagCodes,
            receivedAt: $payload['received_at'] ?? null,
            notes: $payload['notes'] ?? null,
            ipAddress: $ipAddress,
        );
    }

    /**
     * @return array{product: Product, on_hand_qty: int, delta: int}
     */
    private function performConsumableReceipt(
        User $user,
        Product $product,
        int $qty,
        ?string $referenceNo = null,
        ?CarbonImmutable $receivedAt = null,
        ?CarbonImmutable $expiresAt = null,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): array {
        $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        $lot = StockLot::create([
            'product_id' => $product->id,
            'reference_no' => $referenceNo,
            'received_at' => ($receivedAt ?? CarbonImmutable::now()),
            'expires_at' => $expiresAt?->toDateString(),
            'qty_received' => $qty,
            'qty_remaining' => $qty,
        ]);

        $stock = ProductStock::query()->firstOrCreate(
            ['product_id' => $product->id],
            ['on_hand_qty' => 0],
        );

        $qtyBefore = (int) $stock->on_hand_qty;
        $stock->increment('on_hand_qty', $qty);
        $qtyAfter = $qtyBefore + $qty;

        StockMovement::create([
            'movement_type' => 'receive',
            'product_id' => $product->id,
            'stock_lot_id' => $lot->id,
            'asset_id' => null,
            'requisition_id' => null,
            'qty_delta' => $qty,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'performed_by' => $user->id,
            'accountable_position_id' => null,
            'ip_address' => $ipAddress,
            'performed_at' => $receivedAt ?? CarbonImmutable::now(),
            'notes' => $notes,
        ]);

        return [
            'product' => $product->fresh() ?? $product,
            'on_hand_qty' => $qtyAfter,
            'delta' => $qty,
        ];
    }

    /**
     * @param  array<int, string>  $tagCodes
     * @return array{product: Product, on_hand_qty: int|null, delta: int}
     */
    private function performAssetReceipt(
        User $user,
        Product $product,
        array $tagCodes,
        ?CarbonImmutable $receivedAt = null,
        ?string $notes = null,
        ?string $ipAddress = null,
    ): array {
        $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        foreach ($tagCodes as $tagCode) {
            $asset = Asset::create([
                'product_id' => $product->id,
                'position_id' => null,
                'tag_code' => $tagCode,
                'status' => AssetStatus::Available,
            ]);

            StockMovement::create([
                'movement_type' => 'receive',
                'product_id' => $product->id,
                'stock_lot_id' => null,
                'asset_id' => $asset->id,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $user->id,
                'accountable_position_id' => null,
                'ip_address' => $ipAddress,
                'performed_at' => $receivedAt ?? CarbonImmutable::now(),
                'notes' => $notes,
            ]);
        }

        return [
            'product' => $product->fresh() ?? $product,
            'on_hand_qty' => null,
            'delta' => count($tagCodes),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveReceivedQuantity(PurchaseOrderLine $purchaseOrderLine, array $payload): int
    {
        if ($purchaseOrderLine->product?->type === ProductType::Consumable) {
            return (int) ($payload['qty_received'] ?? 0);
        }

        /** @var array<int, string> $tagCodes */
        $tagCodes = $payload['tag_codes'] ?? [];

        return count($tagCodes);
    }

    /**
     * @param  array{product: Product, on_hand_qty: int|null, delta: int}  $result
     */
    private function dispatchReceiptRealtime(array $result): void
    {
        $this->realtime->stockReceived(
            $result['product'],
            $result['on_hand_qty'],
            $result['delta'],
        );
    }

    /**
     * @return array{product: Product, on_hand_qty: int, delta: int}
     */
    private function applyConsumableVariance(
        User $user,
        Product $product,
        int $qtyDelta,
        string $movementType,
        StockMovementReasonCode $reasonCode,
        ?int $countedQty,
        ?string $notes,
        ?string $ipAddress,
    ): array {
        $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

        if ($product->type !== ProductType::Consumable) {
            throw new \RuntimeException('Only consumable products support stock adjustments and cycle counts.');
        }

        $stock = ProductStock::query()->firstOrCreate(
            ['product_id' => $product->id],
            ['on_hand_qty' => 0],
        );

        $stock = ProductStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
        $qtyBefore = (int) $stock->on_hand_qty;
        $qtyAfter = $qtyBefore + $qtyDelta;

        if ($qtyAfter < 0) {
            throw new \RuntimeException("Adjustment would reduce SKU {$product->sku} below zero.");
        }

        if ($qtyDelta > 0) {
            $lot = StockLot::create([
                'product_id' => $product->id,
                'reference_no' => strtoupper($movementType).'-'.CarbonImmutable::now()->format('YmdHis'),
                'received_at' => CarbonImmutable::now(),
                'expires_at' => null,
                'qty_received' => $qtyDelta,
                'qty_remaining' => $qtyDelta,
            ]);

            $stock->increment('on_hand_qty', $qtyDelta);

            StockMovement::create([
                'movement_type' => $movementType,
                'reason_code' => $reasonCode->value,
                'product_id' => $product->id,
                'stock_lot_id' => $lot->id,
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => $qtyDelta,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'counted_qty' => $countedQty,
                'variance_qty' => $qtyDelta,
                'performed_by' => $user->id,
                'accountable_position_id' => $user->position_id,
                'ip_address' => $ipAddress,
                'performed_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);
        } elseif ($qtyDelta < 0) {
            $remaining = abs($qtyDelta);
            $runningOnHand = $qtyBefore;

            /** @var Collection<int, StockLot> $lots */
            $lots = StockLot::query()
                ->where('product_id', $product->id)
                ->where('qty_remaining', '>', 0)
                ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expires_at')
                ->orderBy('received_at')
                ->lockForUpdate()
                ->get();

            foreach ($lots as $lot) {
                if ($remaining <= 0) {
                    break;
                }

                $consume = min($remaining, $lot->qty_remaining);
                $lot->decrement('qty_remaining', $consume);

                StockMovement::create([
                    'movement_type' => $movementType,
                    'reason_code' => $reasonCode->value,
                    'product_id' => $product->id,
                    'stock_lot_id' => $lot->id,
                    'asset_id' => null,
                    'requisition_id' => null,
                    'qty_delta' => -$consume,
                    'qty_before' => $runningOnHand,
                    'qty_after' => $runningOnHand - $consume,
                    'counted_qty' => $countedQty,
                    'variance_qty' => $qtyDelta,
                    'performed_by' => $user->id,
                    'accountable_position_id' => $user->position_id,
                    'ip_address' => $ipAddress,
                    'performed_at' => CarbonImmutable::now(),
                    'notes' => $notes,
                ]);

                $runningOnHand -= $consume;
                $remaining -= $consume;
            }

            if ($remaining > 0) {
                throw new \RuntimeException("Unable to allocate stock lots for SKU {$product->sku}.");
            }

            $stock->decrement('on_hand_qty', abs($qtyDelta));
        } else {
            StockMovement::create([
                'movement_type' => $movementType,
                'reason_code' => $reasonCode->value,
                'product_id' => $product->id,
                'stock_lot_id' => null,
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => 0,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyAfter,
                'counted_qty' => $countedQty,
                'variance_qty' => 0,
                'performed_by' => $user->id,
                'accountable_position_id' => $user->position_id,
                'ip_address' => $ipAddress,
                'performed_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);
        }

        return [
            'product' => $product->fresh() ?? $product,
            'on_hand_qty' => $qtyAfter,
            'delta' => $qtyDelta,
        ];
    }

    /**
     * @param  array{product: Product, on_hand_qty: int, delta: int}  $result
     */
    private function dispatchInventoryDeltaRealtime(array $result): void
    {
        if ($result['delta'] > 0) {
            $this->realtime->stockReceived(
                $result['product'],
                $result['on_hand_qty'],
                $result['delta'],
            );

            return;
        }

        if ($result['delta'] < 0) {
            $this->realtime->stockIssued(
                $result['product'],
                $result['on_hand_qty'],
                $result['delta'],
            );
        }
    }
}
