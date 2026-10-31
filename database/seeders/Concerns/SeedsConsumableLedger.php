<?php

namespace Database\Seeders\Concerns;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Rebuilds a consumable's lot ledger in date order: receipts open lots, issues draw lots
 * first-expiring-first, and every movement stores a chained qty_before/qty_after snapshot.
 * The final running balance must equal the product's stock, so seeded history reconciles.
 */
trait SeedsConsumableLedger
{
    /**
     * @param  array<int, array{
     *     performed_at: CarbonImmutable,
     *     qty_delta: int,
     *     movement_type: string,
     *     performed_by: User,
     *     accountable_position_id: int|null,
     *     notes: string,
     *     requisition_id?: int|null
     * }>  $events
     */
    protected function seedConsumableLedger(Product $product, array $events): void
    {
        $expectedFinalQty = (int) ($product->stock?->on_hand_qty ?? 0);
        $lots = $product->lots()
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();

        $timeline = [];
        foreach ($lots as $lot) {
            $timeline[] = [
                'kind' => 'receive',
                'performed_at' => CarbonImmutable::instance($lot->received_at),
                'lot' => $lot,
            ];
        }

        foreach ($events as $event) {
            $timeline[] = ['kind' => 'usage', ...$event];
        }

        usort($timeline, function (array $left, array $right): int {
            $dateComparison = $left['performed_at'] <=> $right['performed_at'];

            if ($dateComparison !== 0) {
                return $dateComparison;
            }

            return $left['kind'] === 'receive' ? -1 : 1;
        });

        $runningQty = 0;
        $lotBalances = [];

        foreach ($timeline as $entry) {
            if ($entry['kind'] === 'receive') {
                $lot = $entry['lot'];
                $receivedQty = (int) $lot->qty_received;

                StockMovement::query()
                    ->where('movement_type', 'receive')
                    ->where('product_id', $product->id)
                    ->where('stock_lot_id', $lot->id)
                    ->update([
                        'qty_delta' => $receivedQty,
                        'qty_before' => $runningQty,
                        'qty_after' => $runningQty + $receivedQty,
                        'performed_at' => $entry['performed_at'],
                    ]);

                $lotBalances[$lot->id] = $receivedQty;
                $runningQty += $receivedQty;

                continue;
            }

            $event = $entry;
            $remainingQty = abs((int) $event['qty_delta']);
            $movementIds = [];

            if ($event['qty_delta'] < 0) {
                foreach ($lots as $lot) {
                    if ($remainingQty === 0 || $lot->received_at->isAfter($event['performed_at'])) {
                        continue;
                    }

                    if ($lot->expires_at !== null && $lot->expires_at->isBefore($event['performed_at']->toDateString())) {
                        continue;
                    }

                    $availableQty = $lotBalances[$lot->id] ?? 0;
                    $issuedQty = min($availableQty, $remainingQty);

                    if ($issuedQty === 0) {
                        continue;
                    }

                    $movement = $this->syncLotMovement($product, $lot->id, $event, -$issuedQty, $runningQty, count($movementIds));
                    $movementIds[] = $movement->id;
                    $lotBalances[$lot->id] -= $issuedQty;
                    $runningQty -= $issuedQty;
                    $remainingQty -= $issuedQty;
                }
            } else {
                foreach ($lots as $lot) {
                    if ($remainingQty === 0 || $lot->received_at->isAfter($event['performed_at'])) {
                        continue;
                    }

                    $availableCapacity = (int) $lot->qty_received - ($lotBalances[$lot->id] ?? 0);
                    $returnedQty = min($availableCapacity, $remainingQty);

                    if ($returnedQty === 0) {
                        continue;
                    }

                    $movement = $this->syncLotMovement($product, $lot->id, $event, $returnedQty, $runningQty, count($movementIds));
                    $movementIds[] = $movement->id;
                    $lotBalances[$lot->id] += $returnedQty;
                    $runningQty += $returnedQty;
                    $remainingQty -= $returnedQty;
                }
            }

            if ($remainingQty !== 0) {
                throw new \RuntimeException("Seed ledger for {$product->sku} could not allocate {$remainingQty} units.");
            }

            StockMovement::query()
                ->where('movement_type', $event['movement_type'])
                ->where('product_id', $product->id)
                ->where('notes', $event['notes'])
                ->whereNotIn('id', $movementIds)
                ->delete();
        }

        foreach ($lots as $lot) {
            $lot->forceFill(['qty_remaining' => $lotBalances[$lot->id] ?? 0])->save();
        }

        if ($runningQty !== $expectedFinalQty) {
            throw new \RuntimeException("Seed ledger for {$product->sku} ended at {$runningQty}, expected {$expectedFinalQty}.");
        }
    }

    /**
     * @param  array{
     *     performed_at: CarbonImmutable,
     *     qty_delta: int,
     *     movement_type: string,
     *     performed_by: User,
     *     accountable_position_id: int|null,
     *     notes: string,
     *     requisition_id?: int|null
     * }  $event
     */
    protected function syncLotMovement(Product $product, int $stockLotId, array $event, int $qtyDelta, int $qtyBefore, int $splitIndex = 0): StockMovement
    {
        // When one event draws from several lots, each further part is stamped a second later
        // so the running balance reads in order by time, independent of row ids.
        $performedAt = $event['performed_at']->addSeconds($splitIndex);

        return StockMovement::query()->updateOrCreate(
            [
                'movement_type' => $event['movement_type'],
                'product_id' => $product->id,
                'stock_lot_id' => $stockLotId,
                'notes' => $event['notes'],
            ],
            [
                'asset_id' => null,
                'requisition_id' => $event['requisition_id'] ?? null,
                'qty_delta' => $qtyDelta,
                'qty_before' => $qtyBefore,
                'qty_after' => $qtyBefore + $qtyDelta,
                'performed_by' => $event['performed_by']->id,
                'accountable_position_id' => $event['accountable_position_id'],
                'ip_address' => '10.250.8.21',
                'performed_at' => $performedAt,
            ],
        );
    }
}
