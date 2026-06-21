<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\StockLot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:inventory-generate-alerts')]
#[Description('Generate low stock and expiring lot inventory alerts')]
class InventoryGenerateAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $now = CarbonImmutable::now();

        $lowStockProducts = Product::query()
            ->where('type', ProductType::Consumable)
            ->where('is_active', true)
            ->whereHas('stock', function ($query) {
                $query->whereColumn('on_hand_qty', '<=', 'products.reorder_threshold');
            })
            ->with(['stock:id,product_id,on_hand_qty'])
            ->get();

        $activeLowStockProductIds = [];

        foreach ($lowStockProducts as $product) {
            $onHand = $product->stock?->on_hand_qty ?? 0;
            $activeLowStockProductIds[] = $product->id;

            InventoryAlert::query()->updateOrCreate(
                [
                    'type' => 'low_stock',
                    'product_id' => $product->id,
                    'stock_lot_id' => null,
                    'resolved_at' => null,
                ],
                [
                    'message' => "Low stock: {$product->name} ({$product->sku}) has {$onHand} on hand (threshold {$product->reorder_threshold}).",
                    'detected_at' => $now,
                ],
            );
        }

        $staleLowStockAlerts = InventoryAlert::query()
            ->where('type', 'low_stock')
            ->whereNull('resolved_at');

        if ($activeLowStockProductIds !== []) {
            $staleLowStockAlerts->whereNotIn('product_id', $activeLowStockProductIds);
        }

        $staleLowStockAlerts->update(['resolved_at' => $now]);

        $expiringLots = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $now->addDays(7)->toDateString())
            ->with(['product:id,sku,name'])
            ->get();

        $activeExpiringLotIds = [];

        foreach ($expiringLots as $lot) {
            $activeExpiringLotIds[] = $lot->id;

            InventoryAlert::query()->updateOrCreate(
                [
                    'type' => 'expiring',
                    'product_id' => $lot->product_id,
                    'stock_lot_id' => $lot->id,
                    'resolved_at' => null,
                ],
                [
                    'message' => "Expiring soon: {$lot->product->name} ({$lot->product->sku}) lot #{$lot->id} expires {$lot->expires_at}.",
                    'detected_at' => $now,
                ],
            );
        }

        $staleExpiringAlerts = InventoryAlert::query()
            ->where('type', 'expiring')
            ->whereNull('resolved_at');

        if ($activeExpiringLotIds !== []) {
            $staleExpiringAlerts->whereNotIn('stock_lot_id', $activeExpiringLotIds);
        }

        $staleExpiringAlerts->update(['resolved_at' => $now]);

        $this->info('Inventory alerts generated.');

        return self::SUCCESS;
    }
}
