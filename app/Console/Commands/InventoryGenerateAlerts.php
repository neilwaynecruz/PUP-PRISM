<?php

namespace App\Console\Commands;

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
    public function handle()
    {
        $now = CarbonImmutable::now();

        InventoryAlert::query()->delete();

        $lowStockProducts = Product::query()
            ->where('type', 'consumable')
            ->where('is_active', true)
            ->whereHas('stock', function ($query) {
                $query->whereColumn('on_hand_qty', '<=', 'products.reorder_threshold');
            })
            ->with(['stock:id,product_id,on_hand_qty'])
            ->get();

        foreach ($lowStockProducts as $product) {
            $onHand = $product->stock?->on_hand_qty ?? 0;

            InventoryAlert::create([
                'type' => 'low_stock',
                'product_id' => $product->id,
                'stock_lot_id' => null,
                'message' => "Low stock: {$product->name} ({$product->sku}) has {$onHand} on hand (threshold {$product->reorder_threshold}).",
                'detected_at' => $now,
                'resolved_at' => null,
            ]);
        }

        $expiringLots = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $now->addDays(7)->toDateString())
            ->with(['product:id,sku,name'])
            ->get();

        foreach ($expiringLots as $lot) {
            InventoryAlert::create([
                'type' => 'expiring',
                'product_id' => $lot->product_id,
                'stock_lot_id' => $lot->id,
                'message' => "Expiring soon: {$lot->product->name} ({$lot->product->sku}) lot #{$lot->id} expires {$lot->expires_at}.",
                'detected_at' => $now,
                'resolved_at' => null,
            ]);
        }

        $this->info('Inventory alerts generated.');
    }
}
