<?php

namespace App\Console\Commands;

use App\Enums\ProductType;
use App\Models\InventoryAlert;
use App\Models\Product;
use App\Models\StockLot;
use App\Services\NotificationService;
use App\Support\SchedulerHeartbeat;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:inventory-generate-alerts')]
#[Description('Generate low stock, near-expiry, and expired lot inventory alerts')]
class InventoryGenerateAlerts extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $now = CarbonImmutable::now();
        $todayDate = $now->toDateString();

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
            $onHand = $product->stock->on_hand_qty ?? 0;
            $activeLowStockProductIds[] = $product->id;

            $alert = InventoryAlert::upsertUnresolved(
                [
                    'type' => 'low_stock',
                    'product_id' => $product->id,
                    'stock_lot_id' => null,
                ],
                [
                    'message' => "Low stock: {$product->name} ({$product->sku}) has {$onHand} on hand (threshold {$product->reorder_threshold}).",
                    'detected_at' => $now,
                ],
            );

            if ($alert->wasRecentlyCreated) {
                $notificationService->lowStockAlert($product, $onHand);
            }
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
            ->whereDate('expires_at', '>=', $todayDate)
            ->whereDate('expires_at', '<=', $now->addDays(7)->toDateString())
            ->with(['product:id,sku,name'])
            ->get();

        $activeExpiringLotIds = [];

        foreach ($expiringLots as $lot) {
            $activeExpiringLotIds[] = $lot->id;
            $expiresAt = CarbonImmutable::parse((string) $lot->expires_at)->startOfDay();
            $daysLeft = (int) $now->startOfDay()->diffInDays($expiresAt, false);

            $alert = InventoryAlert::upsertUnresolved(
                [
                    'type' => 'expiring',
                    'product_id' => $lot->product_id,
                    'stock_lot_id' => $lot->id,
                ],
                [
                    'message' => "Expiring soon: {$lot->product->name} ({$lot->product->sku}) lot #{$lot->id} expires {$lot->expires_at}.",
                    'detected_at' => $now,
                ],
            );

            if ($alert->wasRecentlyCreated) {
                $notificationService->expiringStockAlert($lot, $daysLeft);
            }
        }

        $staleExpiringAlerts = InventoryAlert::query()
            ->where('type', 'expiring')
            ->whereNull('resolved_at');

        if ($activeExpiringLotIds !== []) {
            $staleExpiringAlerts->whereNotIn('stock_lot_id', $activeExpiringLotIds);
        }

        $staleExpiringAlerts->update(['resolved_at' => $now]);

        $expiredLots = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', $todayDate)
            ->with(['product:id,sku,name'])
            ->get();

        $activeExpiredLotIds = [];

        foreach ($expiredLots as $lot) {
            $activeExpiredLotIds[] = $lot->id;

            InventoryAlert::upsertUnresolved(
                [
                    'type' => 'expired',
                    'product_id' => $lot->product_id,
                    'stock_lot_id' => $lot->id,
                ],
                [
                    'message' => "Expired lot: {$lot->product->name} ({$lot->product->sku}) lot #{$lot->id} expired {$lot->expires_at}.",
                    'detected_at' => $now,
                ],
            );
        }

        $staleExpiredAlerts = InventoryAlert::query()
            ->where('type', 'expired')
            ->whereNull('resolved_at');

        if ($activeExpiredLotIds !== []) {
            $staleExpiredAlerts->whereNotIn('stock_lot_id', $activeExpiredLotIds);
        }

        $staleExpiredAlerts->update(['resolved_at' => $now]);

        $this->info('Inventory alerts generated.');

        SchedulerHeartbeat::record(SchedulerHeartbeat::COMMAND_INVENTORY_GENERATE_ALERTS);

        return self::SUCCESS;
    }
}
