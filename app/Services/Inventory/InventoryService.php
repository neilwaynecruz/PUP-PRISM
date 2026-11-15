<?php

namespace App\Services\Inventory;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Enums\StockMovementReasonCode;
use App\Models\Asset;
use App\Models\InventoryCountLine;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\InventoryRealtimeService;
use App\Services\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type LockedStockMap Collection<int, ProductStock>
 */
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

    /**
     * Atomically receive multiple lines against a purchase order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function receivePurchaseOrder(
        User $user,
        PurchaseOrder $purchaseOrder,
        array $lines,
        ?string $ipAddress = null,
        ?string $idempotencyKey = null,
    ): PurchaseOrder {
        if ($lines === []) {
            throw new \InvalidArgumentException('No line items provided for receipt.');
        }

        // Serialize identical requests so concurrent retries cannot both pass the
        // idempotency check before either transaction records its completion.
        $normalizedIdempotencyKey = $idempotencyKey !== null && trim($idempotencyKey) !== ''
            ? trim($idempotencyKey)
            : null;

        $execute = function () use ($user, $purchaseOrder, $lines, $ipAddress): PurchaseOrder {
            /** @var array{0: PurchaseOrder, 1: array<int, array{product: Product, on_hand_qty: int|null, delta: int}>} $transactionResult */
            $transactionResult = DB::transaction(function () use ($user, $purchaseOrder, $lines, $ipAddress): array {
                // 1. Lock the PurchaseOrder first to serialize receipt attempts and prevent concurrent race conditions
                /** @var PurchaseOrder $lockedPo */
                $lockedPo = PurchaseOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($purchaseOrder->id);

                if (! $lockedPo->canReceive()) {
                    throw new \RuntimeException('Only sent or partially received purchase orders can accept receipts.');
                }

                // 2. Lock all affected purchase order lines in strictly ascending ID order to avoid deadlocks
                $submittedLineIds = collect($lines)->pluck('purchase_order_line_id')->map(fn ($id) => (int) $id)->sort()->values()->all();

                $lockedLines = PurchaseOrderLine::query()
                    ->where('purchase_order_id', $lockedPo->id)
                    ->whereIn('id', $submittedLineIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                // 3. Lock all affected products in strictly ascending ID order
                $productIds = $lockedLines->pluck('product_id')->unique()->sort()->values()->all();
                $lockedProducts = Product::query()
                    ->whereIn('id', $productIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $consumableIds = $lockedProducts
                    ->filter(fn (Product $product): bool => $product->type === ProductType::Consumable)
                    ->pluck('id')
                    ->sort()
                    ->values()
                    ->all();

                /** @var Collection<int, ProductStock> $lockedStocks */
                $lockedStocks = $consumableIds !== []
                    ? ProductStock::query()
                        ->whereIn('product_id', $consumableIds)
                        ->orderBy('product_id')
                        ->lockForUpdate()
                        ->get()
                        ->keyBy('product_id')
                    : collect();

                $events = [];

                // 4. Validate and process each line
                foreach ($lines as $linePayload) {
                    $lineId = (int) $linePayload['purchase_order_line_id'];
                    /** @var PurchaseOrderLine|null $line */
                    $line = $lockedLines->get($lineId);

                    if (! $line) {
                        throw new \RuntimeException("Purchase order line #{$lineId} does not belong to this purchase order.");
                    }

                    $product = $lockedProducts->get($line->product_id);
                    if (! $product) {
                        throw new \RuntimeException("Product #{$line->product_id} not found.");
                    }

                    $remainingQty = $line->remainingQty();
                    $receivedQuantity = $this->resolveReceivedQuantity($line, $linePayload, $product);

                    if ($receivedQuantity <= 0) {
                        throw new \RuntimeException("Received quantity must be greater than zero for product {$product->sku}.");
                    }

                    if ($receivedQuantity > $remainingQty) {
                        throw new \RuntimeException("Receipt exceeds remaining quantity for product {$product->sku}.");
                    }

                    // If asset, check tag uniqueness against database
                    if ($product->type === ProductType::Asset) {
                        /** @var array<int, string> $tagCodes */
                        $tagCodes = $linePayload['tag_codes'] ?? [];
                        if (Asset::query()->whereIn('tag_code', $tagCodes)->exists()) {
                            throw new \RuntimeException('One or more asset tag codes have already been registered.');
                        }
                    }

                    // Execute receipt
                    $receiptResult = $this->performReceipt(
                        user: $user,
                        product: $product,
                        payload: [
                            ...$linePayload,
                            'reference_no' => $linePayload['reference_no'] ?? $lockedPo->po_number,
                            'notes' => $linePayload['notes'] ?? "Received against {$lockedPo->po_number}.",
                        ],
                        ipAddress: $ipAddress,
                        productLocked: true,
                        lockedStocksByProductId: $lockedStocks,
                    );

                    $line->forceFill([
                        'qty_received' => $line->qty_received + $receivedQuantity,
                    ])->save();

                    $events[] = $receiptResult;
                }

                // 5. Update purchase order receipt status and audit log atomically
                $oldValues = [
                    'status' => $lockedPo->status->value,
                    'received_at' => $lockedPo->received_at?->toIso8601String(),
                ];

                $lockedPo->refreshReceiptStatus();

                AuditLogService::logUpdated($lockedPo, $oldValues);

                return [$lockedPo->load(['lines.product', 'supplier']), $events];
            });

            [$updatedPo, $realtimeEvents] = $transactionResult;

            // Dispatch realtime events strictly after transaction has committed.
            foreach ($realtimeEvents as $event) {
                $this->dispatchReceiptRealtime($event);
            }

            return $updatedPo;
        };

        if ($normalizedIdempotencyKey === null) {
            return $execute();
        }

        $cacheKey = 'po_receive_idempotency:'.$purchaseOrder->id.':'.$normalizedIdempotencyKey;
        $lockKey = $cacheKey.':lock';

        try {
            return Cache::lock($lockKey, 120)->block(5, function () use ($cacheKey, $purchaseOrder, $execute): PurchaseOrder {
                if (Cache::has($cacheKey)) {
                    return $purchaseOrder->fresh(['supplier', 'requester', 'approver', 'lines.product']) ?? $purchaseOrder;
                }

                $updatedPo = $execute();

                // 6. Record explicit idempotency token before releasing the request lock.
                Cache::put($cacheKey, true, now()->addSeconds(60));

                return $updatedPo;
            });
        } catch (LockTimeoutException $exception) {
            throw new \RuntimeException(
                'Another receipt for this purchase order is still being processed. Wait a moment, then retry.',
                previous: $exception,
            );
        }
    }

    public function receiveAgainstPurchaseOrderLine(
        User $user,
        PurchaseOrderLine $purchaseOrderLine,
        array $payload,
        ?string $ipAddress = null,
        ?string $idempotencyKey = null,
    ): void {
        $purchaseOrder = $purchaseOrderLine->purchaseOrder
            ?? PurchaseOrder::query()->findOrFail($purchaseOrderLine->purchase_order_id);

        $this->receivePurchaseOrder($user, $purchaseOrder, [
            [
                ...$payload,
                'purchase_order_line_id' => $purchaseOrderLine->id,
            ],
        ], $ipAddress, $idempotencyKey);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function batchReceive(User $user, array $lines, ?string $ipAddress = null): void
    {
        if ($lines === []) {
            return;
        }

        /** @var array<int, array{product: Product, on_hand_qty: int|null, delta: int}> $stockChanges */
        $stockChanges = DB::transaction(function () use ($user, $lines, $ipAddress): array {
            // Validate intra-batch asset tag and serial uniqueness before acquiring locks.
            $seenTags = [];
            $seenSerials = [];
            foreach ($lines as $lineIndex => $line) {
                foreach (array_values($line['tag_codes'] ?? []) as $tagIndex => $rawTag) {
                    $tag = trim((string) $rawTag);
                    if ($tag !== '') {
                        if (isset($seenTags[$tag])) {
                            throw ValidationException::withMessages([
                                "lines.{$lineIndex}.tag_codes.{$tagIndex}" => __('Duplicate asset tag code :tag in batch.', ['tag' => $tag]),
                            ]);
                        }
                        $seenTags[$tag] = true;
                    }
                }
                foreach (array_values($line['serial_numbers'] ?? []) as $serialIndex => $rawSerial) {
                    $serial = is_string($rawSerial) && trim($rawSerial) !== '' ? trim($rawSerial) : null;
                    if ($serial !== null) {
                        if (isset($seenSerials[$serial])) {
                            throw ValidationException::withMessages([
                                "lines.{$lineIndex}.serial_numbers.{$serialIndex}" => __('Duplicate serial number :serial in batch.', ['serial' => $serial]),
                            ]);
                        }
                        $seenSerials[$serial] = true;
                    }
                }
            }

            // Lock all products (and consumable stocks) in deterministic ascending product_id order to prevent deadlocks.
            $skus = collect($lines)->pluck('sku')->filter()->unique()->values()->all();
            $productIdsBySku = Product::query()
                ->whereIn('sku', $skus)
                ->pluck('id', 'sku');

            foreach ($skus as $sku) {
                if (! $productIdsBySku->has($sku)) {
                    throw (new ModelNotFoundException)->setModel(Product::class);
                }
            }

            $orderedProductIds = $productIdsBySku->values()->map(fn ($id): int => (int) $id)->sort()->values()->all();

            $lockedProducts = Product::query()
                ->whereIn('id', $orderedProductIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('sku');

            $consumableIds = $lockedProducts
                ->filter(fn (Product $product): bool => $product->type === ProductType::Consumable)
                ->pluck('id')
                ->sort()
                ->values()
                ->all();

            /** @var Collection<int, ProductStock> $lockedStocks */
            $lockedStocks = $consumableIds !== []
                ? ProductStock::query()
                    ->whereIn('product_id', $consumableIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id')
                : collect();

            $changes = [];

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $lockedProducts->get($line['sku'])
                    ?? throw (new ModelNotFoundException)->setModel(Product::class);

                $changes[] = $this->performReceipt(
                    user: $user,
                    product: $product,
                    payload: $line,
                    ipAddress: $ipAddress,
                    productLocked: true,
                    lockedStocksByProductId: $lockedStocks,
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
    ): Requisition {
        /** @var array{requisition: Requisition, stock_changes: array<int, array{product: Product, on_hand_qty: int, delta: int, requisition_id: int}>, low_stock_alerts: array<int, array{product: Product, on_hand_qty: int}>} $result */
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
                ->orderBy('product_id')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $submittedLines = collect($linePayloads ?? []);
            $submittedLineIds = $submittedLines
                ->map(function (array $line): int {
                    if (! array_key_exists('id', $line) || ! array_key_exists('qty_to_issue', $line)) {
                        throw new \RuntimeException('Each requisition line submission must include an id and issue quantity.');
                    }

                    if ((int) $line['qty_to_issue'] < 0) {
                        throw new \RuntimeException('Requisition issue quantities cannot be negative.');
                    }

                    return (int) $line['id'];
                });

            if ($submittedLineIds->duplicates()->isNotEmpty()) {
                throw new \RuntimeException('Duplicate requisition line submissions are not allowed.');
            }

            if ($submittedLineIds->diff($lines->modelKeys())->isNotEmpty()) {
                throw new \RuntimeException('A submitted requisition line does not belong to this requisition.');
            }

            /** @var array<int, int> $requestedQuantities */
            $requestedQuantities = $submittedLines
                ->mapWithKeys(fn (array $line): array => [(int) $line['id'] => (int) $line['qty_to_issue']])
                ->all();

            // Lock all affected Products and ProductStocks in ascending product_id order before validating/allocating lots.
            $orderedProductIds = $lines->pluck('product_id')->unique()->sort()->values()->all();

            /** @var Collection<int, Product> $lockedProducts */
            $lockedProducts = Product::query()
                ->whereIn('id', $orderedProductIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $activeProductIds = [];
            foreach ($lines as $line) {
                $product = $lockedProducts->get($line->product_id)
                    ?? throw (new ModelNotFoundException)->setModel(Product::class);

                if ($product->type !== ProductType::Consumable) {
                    throw new \RuntimeException('Only consumable requisition lines can be issued here.');
                }

                $qty = array_key_exists($line->id, $requestedQuantities)
                    ? $requestedQuantities[$line->id]
                    : $line->remainingQuantity();

                if ($qty > $line->remainingQuantity()) {
                    throw new \RuntimeException("Issue quantity exceeds the remaining quantity for SKU {$product->sku}.");
                }

                if ($qty > 0) {
                    $activeProductIds[] = (int) $product->id;
                }
            }

            $activeProductIds = collect($activeProductIds)->unique()->sort()->values()->all();

            /** @var Collection<int, ProductStock> $lockedStocks */
            $lockedStocks = $activeProductIds !== []
                ? ProductStock::query()
                    ->whereIn('product_id', $activeProductIds)
                    ->orderBy('product_id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('product_id')
                : collect();

            /** @var array<int, int> $committedByOthersByProduct */
            $committedByOthersByProduct = $activeProductIds !== []
                ? RequisitionLine::query()
                    ->join('requisitions', 'requisitions.id', '=', 'requisition_lines.requisition_id')
                    ->whereIn('requisition_lines.product_id', $activeProductIds)
                    ->where('requisition_lines.requisition_id', '!=', $requisition->id)
                    ->whereNull('requisitions.deleted_at')
                    ->whereIn('requisitions.status', [
                        RequisitionStatus::Approved->value,
                        RequisitionStatus::PartiallyIssued->value,
                    ])
                    ->select(
                        'requisition_lines.product_id',
                        DB::raw('COALESCE(SUM(CASE WHEN requisition_lines.qty_requested > requisition_lines.qty_issued THEN requisition_lines.qty_requested - requisition_lines.qty_issued ELSE 0 END), 0) as committed_qty'),
                    )
                    ->groupBy('requisition_lines.product_id')
                    ->pluck('committed_qty', 'requisition_lines.product_id')
                    ->map(static fn (mixed $value): int => (int) $value)
                    ->all()
                : [];

            $issuedAnyQuantity = false;

            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $lockedProducts->get($line->product_id);

                $qty = array_key_exists($line->id, $requestedQuantities)
                    ? $requestedQuantities[$line->id]
                    : $line->remainingQuantity();

                if ($qty === 0) {
                    continue;
                }

                /** @var ProductStock|null $stock */
                $stock = $lockedStocks->get($product->id);

                if (! $stock) {
                    throw (new ModelNotFoundException)->setModel(ProductStock::class);
                }

                $committedByOthers = (int) ($committedByOthersByProduct[$product->id] ?? 0);

                $availableOnHand = (int) $stock->on_hand_qty - $committedByOthers;

                if ($stock->on_hand_qty < $qty || $availableOnHand < $qty) {
                    throw new \RuntimeException("Insufficient stock for SKU {$product->sku}.");
                }

                $runningOnHand = (int) $stock->on_hand_qty;
                $remaining = $qty;

                $today = CarbonImmutable::now()->toDateString();

                /** @var Collection<int, StockLot> $lots */
                $lots = StockLot::query()
                    ->where('product_id', $product->id)
                    ->where('qty_remaining', '>', 0)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('expires_at')
                            ->orWhereDate('expires_at', '>=', $today);
                    })
                    ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('expires_at')
                    ->orderBy('received_at')
                    ->lockForUpdate()
                    ->get();

                $unexpiredAvailable = (int) $lots->sum('qty_remaining');

                if ($unexpiredAvailable < $qty) {
                    $totalLotRemaining = (int) StockLot::query()
                        ->where('product_id', $product->id)
                        ->where('qty_remaining', '>', 0)
                        ->sum('qty_remaining');

                    $hasExpiredLots = StockLot::query()
                        ->where('product_id', $product->id)
                        ->where('qty_remaining', '>', 0)
                        ->whereDate('expires_at', '<', $today)
                        ->exists();

                    if ($totalLotRemaining >= $qty && $hasExpiredLots) {
                        throw new \RuntimeException("Insufficient unexpired stock for SKU {$product->sku}. Expired lots cannot be issued.");
                    }

                    throw new \RuntimeException("Unable to allocate lots for SKU {$product->sku}.");
                }

                if (($unexpiredAvailable - $committedByOthers) < $qty) {
                    throw new \RuntimeException("Insufficient stock for SKU {$product->sku}. Remaining unexpired stock is committed to approved requisitions.");
                }

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

                $updatedOnHand = (int) $stock->on_hand_qty;
                $stockChanges[] = [
                    'product' => $product,
                    'on_hand_qty' => $updatedOnHand,
                    'delta' => -$qty,
                    'requisition_id' => $requisition->id,
                ];

                if (
                    $product->reorder_threshold !== null
                    && $updatedOnHand <= $product->reorder_threshold
                ) {
                    $lowStockAlerts[] = [
                        'product' => $product,
                        'on_hand_qty' => $updatedOnHand,
                    ];
                }
            }

            if (! $issuedAnyQuantity && ! $markAsBackordered) {
                throw new \RuntimeException('No requisition quantities were selected for issuance.');
            }

            $hasRemainingQuantities = $lines->contains(
                fn (RequisitionLine $line) => $line->remainingQuantity() > 0,
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
                'requisition' => $requisition,
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

        return $result['requisition'];
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
     * Apply a reviewed count variance while the caller owns the surrounding transaction.
     *
     * @return array{product: Product, on_hand_qty: int|null, delta: int, movement: StockMovement}
     */
    public function applyApprovedCountVariance(
        User $user,
        InventoryCountLine $line,
        ?string $ipAddress = null,
    ): array {
        if (DB::transactionLevel() < 1) {
            throw new \LogicException('Approved count variances must be applied inside a database transaction.');
        }

        if ($line->adjustment_movement_id !== null) {
            throw new \RuntimeException('This count discrepancy has already been posted.');
        }

        if (($line->stock_lot_id === null && $line->asset_id === null) || $line->variance_qty === null || $line->counted_qty === null) {
            throw new \RuntimeException('This count line does not contain a postable count variance.');
        }

        $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->firstOrFail();

        if ($line->asset_id !== null && $line->stock_lot_id === null) {
            if ($product->type !== ProductType::Asset) {
                throw new \RuntimeException('Only tagged asset count lines can update asset status.');
            }

            /** @var Asset $asset */
            $asset = Asset::query()
                ->whereKey($line->asset_id)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $qtyDelta = (int) $line->variance_qty;
            if ($qtyDelta === 0) {
                throw new \RuntimeException('This count line has no variance to apply.');
            }

            if ($qtyDelta < 0) {
                $oldAssetValues = $asset->toArray();
                $asset->update([
                    'status' => AssetStatus::Unserviceable,
                    'condition_status' => AssetConditionStatus::Damaged,
                ]);
                AuditLogService::logUpdated(
                    $asset,
                    $oldAssetValues,
                    "Asset {$asset->tag_code} marked unserviceable and damaged from approved physical count {$line->session->reference_no}.",
                );
            }

            $movement = StockMovement::create([
                'movement_type' => 'cycle_count',
                'reason_code' => StockMovementReasonCode::VarianceReview->value,
                'product_id' => $product->id,
                'stock_lot_id' => null,
                'asset_id' => $asset->id,
                'requisition_id' => null,
                'qty_delta' => 0,
                'qty_before' => 1,
                'qty_after' => (int) $line->counted_qty,
                'counted_qty' => (int) $line->counted_qty,
                'variance_qty' => $qtyDelta,
                'performed_by' => $user->id,
                'accountable_position_id' => $asset->position_id ?? $user->position_id,
                'ip_address' => $ipAddress,
                'performed_at' => CarbonImmutable::now(),
                'notes' => "Approved physical count {$line->session->reference_no}; asset {$asset->tag_code}.",
            ]);

            return [
                'product' => $product,
                'on_hand_qty' => null,
                'delta' => 0,
                'movement' => $movement,
            ];
        }

        if ($product->type !== ProductType::Consumable) {
            throw new \RuntimeException('Only consumable count lines can change stock quantities.');
        }

        $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
        $countedLot = StockLot::query()
            ->whereKey($line->stock_lot_id)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->firstOrFail();

        $qtyDelta = (int) $line->variance_qty;
        $qtyBefore = (int) $stock->on_hand_qty;
        $qtyAfter = $qtyBefore + $qtyDelta;

        if ($qtyAfter < 0) {
            throw new \RuntimeException("The approved variance would reduce SKU {$product->sku} below zero.");
        }

        $movementLot = $countedLot;
        if ($qtyDelta < 0) {
            if ((int) $countedLot->qty_remaining < abs($qtyDelta)) {
                throw new \RuntimeException("Lot {$countedLot->reference_no} no longer has enough stock for this variance. Request a recount.");
            }

            $countedLot->decrement('qty_remaining', abs($qtyDelta));
            $stock->decrement('on_hand_qty', abs($qtyDelta));
        } elseif ($qtyDelta > 0) {
            if (((int) $countedLot->qty_remaining + $qtyDelta) <= (int) $countedLot->qty_received) {
                $countedLot->increment('qty_remaining', $qtyDelta);
            } else {
                $movementLot = StockLot::create([
                    'product_id' => $product->id,
                    'reference_no' => 'COUNT-'.$line->session->reference_no.'-'.$line->id,
                    'received_at' => CarbonImmutable::now(),
                    'expires_at' => $countedLot->expires_at,
                    'qty_received' => $qtyDelta,
                    'qty_remaining' => $qtyDelta,
                ]);
            }

            $stock->increment('on_hand_qty', $qtyDelta);
        }

        $movement = StockMovement::create([
            'movement_type' => 'cycle_count',
            'reason_code' => StockMovementReasonCode::VarianceReview->value,
            'product_id' => $product->id,
            'stock_lot_id' => $movementLot->id,
            'asset_id' => null,
            'requisition_id' => null,
            'qty_delta' => $qtyDelta,
            'qty_before' => $qtyBefore,
            'qty_after' => $qtyAfter,
            'counted_qty' => $line->counted_qty,
            'variance_qty' => $qtyDelta,
            'performed_by' => $user->id,
            'accountable_position_id' => $user->position_id,
            'ip_address' => $ipAddress,
            'performed_at' => CarbonImmutable::now(),
            'notes' => "Approved physical count {$line->session->reference_no}; source lot {$countedLot->reference_no}.",
        ]);

        return [
            'product' => $product,
            'on_hand_qty' => $qtyAfter,
            'delta' => $qtyDelta,
            'movement' => $movement,
        ];
    }

    /**
     * @param  array{product: Product, on_hand_qty: int|null, delta: int}  $result
     */
    public function publishInventoryDelta(array $result): void
    {
        $this->dispatchInventoryDeltaRealtime($result);
    }

    /**
     * @param  LockedStockMap|null  $lockedStocksByProductId
     * @return array{product: Product, on_hand_qty: int|null, delta: int}
     */
    private function performReceipt(
        User $user,
        Product $product,
        array $payload,
        ?string $ipAddress = null,
        bool $productLocked = false,
        ?Collection $lockedStocksByProductId = null,
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
                productLocked: $productLocked,
                lockedStocksByProductId: $lockedStocksByProductId,
            );
        }

        /** @var array<int, string> $tagCodes */
        $tagCodes = $payload['tag_codes'] ?? [];

        return $this->performAssetReceipt(
            user: $user,
            product: $product,
            tagCodes: $tagCodes,
            serialNumbers: $payload['serial_numbers'] ?? [],
            receivedAt: $payload['received_at'] ?? null,
            notes: $payload['notes'] ?? null,
            ipAddress: $ipAddress,
            productLocked: $productLocked,
        );
    }

    /**
     * @param  LockedStockMap|null  $lockedStocksByProductId
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
        bool $productLocked = false,
        ?Collection $lockedStocksByProductId = null,
    ): array {
        if (! $productLocked) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        }

        $lot = StockLot::create([
            'product_id' => $product->id,
            'reference_no' => $referenceNo,
            'received_at' => ($receivedAt ?? CarbonImmutable::now()),
            'expires_at' => $expiresAt?->toDateString(),
            'qty_received' => $qty,
            'qty_remaining' => $qty,
        ]);

        /** @var ProductStock|null $stock */
        $stock = $lockedStocksByProductId?->get($product->id);

        if (! $stock) {
            $stock = ProductStock::query()->firstOrCreate(
                ['product_id' => $product->id],
                ['on_hand_qty' => 0],
            );
            $stock = ProductStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();

            if ($lockedStocksByProductId !== null) {
                $lockedStocksByProductId->put($product->id, $stock);
            }
        }

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
            'product' => $product,
            'on_hand_qty' => $qtyAfter,
            'delta' => $qty,
        ];
    }

    /**
     * @param  array<int, string>  $tagCodes
     * @param  list<string|null>  $serialNumbers
     * @return array{product: Product, on_hand_qty: int|null, delta: int}
     */
    private function performAssetReceipt(
        User $user,
        Product $product,
        array $tagCodes,
        array $serialNumbers = [],
        ?CarbonImmutable $receivedAt = null,
        ?string $notes = null,
        ?string $ipAddress = null,
        bool $productLocked = false,
    ): array {
        if (! $productLocked) {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
        }

        if ($serialNumbers !== [] && count($serialNumbers) !== count($tagCodes)) {
            throw ValidationException::withMessages([
                'serial_numbers' => __('Provide one optional serial-number entry for each asset tag.'),
            ]);
        }

        $normalizedTags = [];
        $normalizedSerials = [];
        foreach (array_values($tagCodes) as $index => $rawTag) {
            $tagCode = trim((string) $rawTag);
            if ($tagCode === '') {
                throw ValidationException::withMessages([
                    "tag_codes.{$index}" => __('Asset tag codes cannot be blank.'),
                ]);
            }
            if (in_array($tagCode, $normalizedTags, true)) {
                throw ValidationException::withMessages([
                    "tag_codes.{$index}" => __('Duplicate asset tag code :tag in submission.', ['tag' => $tagCode]),
                ]);
            }
            $normalizedTags[] = $tagCode;

            $rawSerial = $serialNumbers[$index] ?? null;
            $serialNumber = is_string($rawSerial) && trim($rawSerial) !== '' ? trim($rawSerial) : null;
            if ($serialNumber !== null && in_array($serialNumber, $normalizedSerials, true)) {
                throw ValidationException::withMessages([
                    "serial_numbers.{$index}" => __('Duplicate serial number :serial in submission.', ['serial' => $serialNumber]),
                ]);
            }
            $normalizedSerials[] = $serialNumber;
        }

        $existingTags = Asset::query()
            ->whereIn('tag_code', $normalizedTags)
            ->pluck('tag_code')
            ->all();

        if ($existingTags !== []) {
            throw ValidationException::withMessages([
                'tag_codes' => __('Duplicate asset tag code(s) already exist: :tags', ['tags' => implode(', ', $existingTags)]),
            ]);
        }

        $nonNullSerials = array_values(array_filter($normalizedSerials, static fn (?string $serial): bool => $serial !== null));
        if ($nonNullSerials !== []) {
            $existingSerials = Asset::query()
                ->whereIn('serial_number', $nonNullSerials)
                ->pluck('serial_number')
                ->all();

            if ($existingSerials !== []) {
                throw ValidationException::withMessages([
                    'serial_numbers' => __('Serial number(s) already exist: :serials', ['serials' => implode(', ', $existingSerials)]),
                ]);
            }
        }

        foreach ($normalizedTags as $index => $tagCode) {
            $serialNumber = $normalizedSerials[$index] ?? null;

            try {
                // Use a savepoint so a concurrent unique constraint conflict rolls back safely on PostgreSQL.
                $asset = DB::transaction(fn (): Asset => Asset::create([
                    'product_id' => $product->id,
                    'position_id' => null,
                    'tag_code' => $tagCode,
                    'serial_number' => $serialNumber,
                    'status' => AssetStatus::Available,
                    'acquisition_date' => ($receivedAt ?? CarbonImmutable::now())->toDateString(),
                    'acquisition_cost' => $product->unit_price,
                ]));
            } catch (QueryException $exception) {
                if (! $this->isUniqueConstraintViolation($exception)) {
                    throw $exception;
                }

                if ($serialNumber !== null && Asset::query()->where('serial_number', $serialNumber)->exists()) {
                    throw ValidationException::withMessages([
                        "serial_numbers.{$index}" => __('The serial number :serial has already been registered.', ['serial' => $serialNumber]),
                    ]);
                }

                throw ValidationException::withMessages([
                    "tag_codes.{$index}" => __('The asset tag code :tag has already been registered.', ['tag' => $tagCode]),
                ]);
            }

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
            'product' => $product,
            'on_hand_qty' => null,
            'delta' => count($normalizedTags),
        ];
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());
        if (in_array($sqlState, ['23505', '23000'], true)) {
            return true;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'unique constraint') || str_contains($message, 'duplicate key');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveReceivedQuantity(PurchaseOrderLine $purchaseOrderLine, array $payload, ?Product $product = null): int
    {
        $resolvedProduct = $product ?? $purchaseOrderLine->product;

        if ($resolvedProduct?->type === ProductType::Consumable) {
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
            'product' => $product,
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
