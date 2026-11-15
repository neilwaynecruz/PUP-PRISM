<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\DashboardStatsCache;
use App\Services\Optimization\InventoryOptimizationCache;

class StockMovementObserver
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
        private readonly InventoryOptimizationCache $inventoryOptimizationCache,
    ) {}

    public function created(StockMovement $stockMovement): void
    {
        $this->dashboardStatsCache->invalidate();
        $this->inventoryOptimizationCache->invalidate();
    }

    public function updated(StockMovement $stockMovement): void
    {
        $this->dashboardStatsCache->invalidate();
        $this->inventoryOptimizationCache->invalidate();
    }

    public function deleted(StockMovement $stockMovement): void
    {
        $this->dashboardStatsCache->invalidate();
        $this->inventoryOptimizationCache->invalidate();
    }
}
