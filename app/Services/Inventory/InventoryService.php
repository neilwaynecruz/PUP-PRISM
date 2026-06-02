<?php

namespace App\Services\Inventory;

use App\Models\Asset;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function receive(User $user, Product $product, array $payload, ?string $ipAddress = null): void
    {
        if ($product->type === 'consumable') {
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
        DB::transaction(function () use ($user, $product, $qty, $referenceNo, $receivedAt, $expiresAt, $notes, $ipAddress): void {
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

            $stock->increment('on_hand_qty', $qty);

            StockMovement::create([
                'movement_type' => 'receive',
                'product_id' => $product->id,
                'stock_lot_id' => $lot->id,
                'asset_id' => null,
                'requisition_id' => null,
                'qty_delta' => $qty,
                'performed_by' => $user->id,
                'accountable_position_id' => null,
                'ip_address' => $ipAddress,
                'performed_at' => CarbonImmutable::now(),
                'notes' => $notes,
            ]);
        });
    }

    /**
     * @param  array<int, string>  $tagCodes
     */
    public function receiveAssets(User $user, Product $product, array $tagCodes, ?string $notes = null, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($user, $product, $tagCodes, $notes, $ipAddress): void {
            $product = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            foreach ($tagCodes as $tagCode) {
                $asset = Asset::create([
                    'product_id' => $product->id,
                    'position_id' => null,
                    'tag_code' => $tagCode,
                    'status' => 'Available',
                ]);

                StockMovement::create([
                    'movement_type' => 'receive',
                    'product_id' => $product->id,
                    'stock_lot_id' => null,
                    'asset_id' => $asset->id,
                    'requisition_id' => null,
                    'qty_delta' => null,
                    'performed_by' => $user->id,
                    'accountable_position_id' => null,
                    'ip_address' => $ipAddress,
                    'performed_at' => CarbonImmutable::now(),
                    'notes' => $notes,
                ]);
            }
        });
    }

    public function issueRequisition(User $user, Requisition $requisition, ?string $notes = null, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($user, $requisition, $notes, $ipAddress): void {
            $requisition = Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();

            if ($requisition->status !== 'Approved') {
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

                if ($product->type !== 'consumable') {
                    throw new \RuntimeException('Only consumable requisition lines can be issued here.');
                }

                $qty = (int) $line->qty_requested;

                $stock = ProductStock::query()->where('product_id', $product->id)->lockForUpdate()->firstOrFail();

                if ($stock->on_hand_qty < $qty) {
                    throw new \RuntimeException("Insufficient stock for SKU {$product->sku}.");
                }

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

                    StockMovement::create([
                        'movement_type' => 'issue',
                        'product_id' => $product->id,
                        'stock_lot_id' => $lot->id,
                        'asset_id' => null,
                        'requisition_id' => $requisition->id,
                        'qty_delta' => -$consume,
                        'performed_by' => $user->id,
                        'accountable_position_id' => $requisition->requester_position_id,
                        'ip_address' => $ipAddress,
                        'performed_at' => CarbonImmutable::now(),
                        'notes' => $notes,
                    ]);

                    $remaining -= $consume;
                }

                if ($remaining !== 0) {
                    throw new \RuntimeException("Unable to allocate lots for SKU {$product->sku}.");
                }

                $stock->decrement('on_hand_qty', $qty);

                $line->update([
                    'qty_issued' => $qty,
                ]);
            }

            $requisition->update([
                'status' => 'Issued',
                'issued_by' => $user->id,
                'issued_position_id' => $user->position_id,
                'issued_ip_address' => $ipAddress,
                'issued_at' => CarbonImmutable::now(),
                'notes' => $notes ?? $requisition->notes,
            ]);
        });
    }
}
