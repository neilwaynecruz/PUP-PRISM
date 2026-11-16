<?php

namespace App\Services\Procurement;

use App\Enums\PurchaseOrderStatus;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\User;
use App\Notifications\PurchaseOrderSentNotification;
use App\Services\AuditLogService;
use App\Services\DashboardStatsCache;
use App\Services\Inventory\InventoryService;
use App\Services\InventoryRealtimeService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderNumberGenerator $numberGenerator,
        private readonly PurchaseOrderGenerator $generator,
        private readonly InventoryService $inventory,
        private readonly DashboardStatsCache $dashboardStatsCache,
        private readonly InventoryRealtimeService $realtime,
    ) {}

    /**
     * Create a draft purchase order with line items inside a transaction.
     *
     * @param  array<string, mixed>  $validated
     */
    public function createDraft(User $actor, array $validated): PurchaseOrder
    {
        $purchaseOrder = DB::transaction(function () use ($actor, $validated): PurchaseOrder {
            $purchaseOrder = new PurchaseOrder([
                'supplier_id' => $validated['supplier_id'],
                'tax' => round((float) ($validated['tax'] ?? 0), 2),
                'requested_by' => $actor->id,
                'expected_delivery_at' => isset($validated['expected_delivery_at'])
                    ? CarbonImmutable::parse((string) $validated['expected_delivery_at'])
                    : null,
                'notes' => $validated['notes'] ?? null,
            ]);
            $purchaseOrder->forceFill([
                'po_number' => $this->numberGenerator->nextNumber(),
                'status' => PurchaseOrderStatus::Draft,
                'subtotal' => 0,
                'total_amount' => 0,
                'approved_by' => null,
            ])->save();

            $productIds = collect($validated['lines'])
                ->pluck('product_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
            $productsById = Product::query()
                ->whereIn('id', $productIds)
                ->get(['id', 'unit_price'])
                ->keyBy('id');

            $now = now()->toDateTimeString();
            $lineRows = [];

            foreach ($validated['lines'] as $line) {
                $product = $productsById->get((int) $line['product_id'])
                    ?? Product::query()->findOrFail((int) $line['product_id']);
                $unitPrice = round((float) ($line['unit_price'] ?? $product->unit_price ?? 0), 2);
                $quantity = (int) $line['qty_ordered'];

                $lineRows[] = [
                    'purchase_order_id' => $purchaseOrder->id,
                    'product_id' => $product->id,
                    'qty_ordered' => $quantity,
                    'qty_received' => 0,
                    'unit_price' => $unitPrice,
                    'subtotal' => round($quantity * $unitPrice, 2),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            PurchaseOrderLine::query()->insert($lineRows);

            $purchaseOrder->recalculateTotals();

            return $purchaseOrder;
        });

        AuditLogService::logCreated($purchaseOrder);
        $this->dashboardStatsCache->invalidate();
        $this->realtime->purchaseOrderUpdated($purchaseOrder, 'created');

        return $purchaseOrder;
    }

    /**
     * Transition a draft purchase order to Sent and email the supplier if configured.
     */
    public function send(PurchaseOrder $purchaseOrder, User $actor, string $notes = ''): ?PurchaseOrder
    {
        /** @var PurchaseOrder|null $sentPo */
        $sentPo = DB::transaction(function () use ($purchaseOrder, $actor, $notes): ?PurchaseOrder {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::query()->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedPo->status !== PurchaseOrderStatus::Draft) {
                return null;
            }

            $lockedPo->forceFill([
                'status' => PurchaseOrderStatus::Sent,
                'sent_at' => now(),
                'approved_by' => $actor->id,
                'notes' => $this->mergeNotes($lockedPo->notes, $notes),
            ])->save();

            AuditLogService::logUpdated($lockedPo, [
                'status' => PurchaseOrderStatus::Draft->value,
                'sent_at' => null,
                'approved_by' => null,
            ]);

            return $lockedPo->load('supplier');
        });

        if (! $sentPo instanceof PurchaseOrder) {
            return null;
        }

        if (filled($sentPo->supplier?->email)) {
            Notification::route('mail', (string) $sentPo->supplier->email)
                ->notify(new PurchaseOrderSentNotification($sentPo));
        }

        $this->dashboardStatsCache->invalidate();
        $this->realtime->purchaseOrderUpdated($sentPo, 'sent');

        return $sentPo;
    }

    /**
     * Receive items against a sent or partially received purchase order.
     *
     * @param  array<int, array<string, mixed>>  $validatedLines
     */
    public function receive(
        PurchaseOrder $purchaseOrder,
        User $actor,
        array $validatedLines,
        ?string $ipAddress = null,
        ?string $idempotencyKey = null,
    ): PurchaseOrder {
        $updatedPo = $this->inventory->receivePurchaseOrder(
            $actor,
            $purchaseOrder,
            $validatedLines,
            ipAddress: $ipAddress,
            idempotencyKey: $idempotencyKey,
        );

        $this->dashboardStatsCache->invalidate();
        $this->realtime->purchaseOrderUpdated($updatedPo, 'received');

        return $updatedPo;
    }

    /**
     * Cancel an unreceived purchase order inside a row lock.
     *
     * @return array{status: 'warning'|'error'|'success', message: string}
     */
    public function cancel(PurchaseOrder $purchaseOrder, string $reason): array
    {
        /** @var array{status: 'warning'|'error'|'success', message: string} $outcome */
        $outcome = DB::transaction(function () use ($purchaseOrder, $reason): array {
            /** @var PurchaseOrder $lockedPo */
            $lockedPo = PurchaseOrder::query()->whereKey($purchaseOrder->id)->lockForUpdate()->firstOrFail();

            if ($lockedPo->status === PurchaseOrderStatus::Cancelled) {
                return [
                    'status' => 'warning',
                    'message' => __('This purchase order is already cancelled.'),
                ];
            }

            $hasReceivedLines = $lockedPo->lines()
                ->lockForUpdate()
                ->get(['id', 'qty_received'])
                ->contains(fn (PurchaseOrderLine $line): bool => (int) $line->qty_received > 0);

            if (
                in_array($lockedPo->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Partial], true)
                || $hasReceivedLines
            ) {
                return [
                    'status' => 'error',
                    'message' => __('Purchase orders with received stock cannot be cancelled.'),
                ];
            }

            $oldValues = $lockedPo->only(['status', 'notes']);

            $lockedPo->forceFill([
                'status' => PurchaseOrderStatus::Cancelled,
                'notes' => $this->mergeNotes(
                    $lockedPo->notes,
                    'Cancellation reason: '.trim($reason),
                ),
            ])->save();

            AuditLogService::logUpdated($lockedPo, $oldValues);

            return [
                'status' => 'success',
                'message' => __('Purchase order cancelled.'),
            ];
        });

        if ($outcome['status'] === 'success') {
            $this->dashboardStatsCache->invalidate();
            $this->realtime->purchaseOrderUpdated($purchaseOrder->fresh() ?? $purchaseOrder, 'cancelled');
        }

        return $outcome;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromAlerts(User $actor): Collection
    {
        $generated = $this->generator->generateFromAlerts($actor);

        if ($generated->isNotEmpty()) {
            $this->dashboardStatsCache->invalidate();
        }

        return $generated;
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public function generateFromForecasts(User $actor): Collection
    {
        $generated = $this->generator->generateFromForecastAlerts($actor);

        if ($generated->isNotEmpty()) {
            $this->dashboardStatsCache->invalidate();
        }

        return $generated;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function supplierOptions(bool $onlyActive = false): array
    {
        return Supplier::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'contact_person', 'email', 'phone', 'payment_terms', 'lead_time_days'])
            ->map(fn (Supplier $supplier) => [
                'id' => (int) $supplier->id,
                'name' => $supplier->name,
                'contact_person' => $supplier->contact_person,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'payment_terms' => $supplier->payment_terms,
                'lead_time_days' => $supplier->lead_time_days,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function productOptions(): array
    {
        return Product::query()
            ->with(['supplier:id,name', 'category:id,name', 'stock:id,product_id,on_hand_qty'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'sku', 'name', 'type', 'category_id', 'supplier_id', 'unit_price', 'reorder_threshold'])
            ->map(function (Product $product): array {
                /** @var mixed $productType */
                $productType = $product->type;
                $typeValue = $productType instanceof \BackedEnum
                    ? $productType->value
                    : (is_string($productType) ? $productType : null);

                return [
                    'id' => (int) $product->id,
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'type' => $typeValue,
                    'category' => $product->category?->name,
                    'supplier_id' => $product->supplier_id,
                    'supplier' => $product->supplier?->name,
                    'unit_price' => $product->unit_price,
                    'on_hand_qty' => (int) ($product->stock !== null ? $product->stock->on_hand_qty : 0),
                    'reorder_threshold' => (int) ($product->reorder_threshold ?? 0),
                ];
            })
            ->all();
    }

    private function mergeNotes(?string $existingNotes, ?string $nextNote): ?string
    {
        $segments = array_values(array_filter([
            $existingNotes ? trim($existingNotes) : null,
            $nextNote ? trim($nextNote) : null,
        ]));

        if ($segments === []) {
            return null;
        }

        return implode("\n\n", $segments);
    }
}
