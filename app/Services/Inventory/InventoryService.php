<?php

namespace App\Services\Inventory;

use App\Enums\AssetStatus;
use App\Enums\ProductType;
use App\Enums\RequisitionStatus;
use App\Models\Asset;
use App\Models\Product;
use App\Models\ProductStock;
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
        if ($product->type === ProductType::Consumable) {
            $this->receiveConsumable(
                user: $user,
                product: $product,
                qty: (int) $payload['qty'],
                referenceNo: $payload['reference_no'] ?? null,
                receivedAt: $payload['received_at'] ?? null,
                expiresAt: $payload['expires_at'] ?? null,
                notes: $payload['notes'] ?? null,
                ipAddress: $ipAddress,
            );

            return;
        }

        $this->receiveAssets(
            user: $user,
            product: $product,
            tagCodes: $payload['tag_codes'],
            notes: $payload['notes'] ?? null,
            ipAddress: $ipAddress,
        );
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
        $result = DB::transaction(fn (): array => $this->performConsumableReceipt(
            user: $user,
            product: $product,
            qty: $qty,
            referenceNo: $referenceNo,
            receivedAt: $receivedAt,
            expiresAt: $expiresAt,
            notes: $notes,
            ipAddress: $ipAddress,
        ));

        $this->realtime->stockReceived($result['product'], $result['on_hand_qty'], $qty);
    }

    /**
     * @param  array<int, string>  $tagCodes
     */
    public function receiveAssets(User $user, Product $product, array $tagCodes, ?string $notes = null, ?string $ipAddress = null): void
    {
        $result = DB::transaction(fn (): array => $this->performAssetReceipt(
            user: $user,
            product: $product,
            tagCodes: $tagCodes,
            notes: $notes,
            ipAddress: $ipAddress,
        ));

        $this->realtime->stockReceived($result['product'], null, $result['delta']);
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

                if ($line['tag_codes'] !== null) {
                    $result = $this->performAssetReceipt(
                        user: $user,
                        product: $product,
                        tagCodes: $line['tag_codes'],
                        notes: $line['notes'] ?? null,
                        ipAddress: $ipAddress,
                    );

                    $changes[] = $result;
                } else {
                    $result = $this->performConsumableReceipt(
                        user: $user,
                        product: $product,
                        qty: $line['qty'],
                        referenceNo: $line['reference_no'] ?? null,
                        receivedAt: $line['received_at'] ?? null,
                        expiresAt: $line['expires_at'] ?? null,
                        notes: $line['notes'] ?? null,
                        ipAddress: $ipAddress,
                    );

                    $changes[] = $result;
                }
            }

            return $changes;
        });

        foreach ($stockChanges as $change) {
            $this->realtime->stockReceived($change['product'], $change['on_hand_qty'], $change['delta']);
        }
    }

    public function issueRequisition(User $user, Requisition $requisition, ?string $notes = null, ?string $ipAddress = null): void
    {
        /** @var array{stock_changes: array<int, array{product: Product, on_hand_qty: int, delta: int, requisition_id: int}>, low_stock_alerts: array<int, array{product: Product, on_hand_qty: int}>} $result */
        $result = DB::transaction(function () use ($user, $requisition, $notes, $ipAddress): array {
            $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            $stockChanges = [];
            $lowStockAlerts = [];

            if ($requisition->status !== RequisitionStatus::Approved) {
                throw new \RuntimeException('Only approved requisitions can be issued.');
            }

            /** @var Collection<int, RequisitionLine> $lines */
            $lines = RequisitionLine::query()
                ->where('requisition_id', $requisition->id)
                ->with('product:id,sku,type')
                ->lockForUpdate()
                ->get();

            foreach ($lines as $line) {
                $product = Product::query()->whereKey($line->product_id)->lockForUpdate()->firstOrFail();

                if ($product->type !== ProductType::Consumable) {
                    throw new \RuntimeException('Only consumable requisition lines can be issued here.');
                }

                $qty = (int) $line->qty_requested;

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
                    'qty_issued' => $qty,
                ]);

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

            $requisition->update([
                'status' => RequisitionStatus::Issued,
                'issued_by' => $user->id,
                'issued_position_id' => $user->position_id,
                'issued_ip_address' => $ipAddress,
                'issued_at' => CarbonImmutable::now(),
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
            'performed_at' => CarbonImmutable::now(),
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
                'performed_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);
        }

        return [
            'product' => $product->fresh() ?? $product,
            'on_hand_qty' => null,
            'delta' => count($tagCodes),
        ];
    }
}
