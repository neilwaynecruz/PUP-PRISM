<?php

namespace App\Observers;

use App\Models\PurchaseOrder;
use App\Services\DashboardStatsCache;

class PurchaseOrderObserver
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
    ) {}

    public function saved(PurchaseOrder $purchaseOrder): void
    {
        $this->dashboardStatsCache->invalidate();
    }
}
