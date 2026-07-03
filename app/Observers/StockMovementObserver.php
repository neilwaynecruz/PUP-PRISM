<?php

namespace App\Observers;

use App\Models\StockMovement;
use App\Services\DashboardStatsCache;

class StockMovementObserver
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
    ) {}

    public function created(StockMovement $stockMovement): void
    {
        $this->dashboardStatsCache->invalidate();
    }
}
