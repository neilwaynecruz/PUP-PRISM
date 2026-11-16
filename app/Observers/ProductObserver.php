<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Optimization\InventoryOptimizationCache;

class ProductObserver
{
    public function __construct(
        private readonly InventoryOptimizationCache $inventoryOptimizationCache,
    ) {}

    public function saved(Product $product): void
    {
        if (
            $product->wasRecentlyCreated
            || $product->wasChanged(['type', 'is_active', 'unit_price', 'name', 'sku', 'reorder_threshold', 'deleted_at'])
        ) {
            $this->inventoryOptimizationCache->invalidate();
        }
    }

    public function deleted(Product $_product): void
    {
        $this->inventoryOptimizationCache->invalidate();
    }

    public function restored(Product $_product): void
    {
        $this->inventoryOptimizationCache->invalidate();
    }
}
