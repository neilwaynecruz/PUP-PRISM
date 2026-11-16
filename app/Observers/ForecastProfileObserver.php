<?php

namespace App\Observers;

use App\Models\ForecastProfile;
use App\Services\Optimization\InventoryOptimizationCache;

class ForecastProfileObserver
{
    public function __construct(
        private readonly InventoryOptimizationCache $inventoryOptimizationCache,
    ) {}

    public function saved(ForecastProfile $_profile): void
    {
        $this->inventoryOptimizationCache->invalidate();
    }

    public function deleted(ForecastProfile $_profile): void
    {
        $this->inventoryOptimizationCache->invalidate();
    }
}
