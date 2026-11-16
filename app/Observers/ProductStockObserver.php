<?php

namespace App\Observers;

use App\Models\ProductStock;
use App\Services\Optimization\InventoryOptimizationCache;

class ProductStockObserver
{
    public function __construct(
        private readonly InventoryOptimizationCache $inventoryOptimizationCache,
    ) {}

    public function saved(ProductStock $stock): void
    {
        if ($stock->wasRecentlyCreated || $stock->wasChanged('on_hand_qty')) {
            $this->inventoryOptimizationCache->invalidate();
        }
    }

    public function deleted(ProductStock $_stock): void
    {
        $this->inventoryOptimizationCache->invalidate();
    }
}
