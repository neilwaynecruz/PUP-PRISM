<?php

namespace App\Observers;

use App\Models\Requisition;
use App\Services\DashboardStatsCache;

class RequisitionObserver
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
    ) {}

    public function saved(Requisition $requisition): void
    {
        if ($requisition->wasRecentlyCreated || $requisition->wasChanged('status')) {
            $this->dashboardStatsCache->invalidate();
        }
    }
}
