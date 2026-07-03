<?php

namespace App\Observers;

use App\Models\Booking;
use App\Services\DashboardStatsCache;

class BookingObserver
{
    public function __construct(
        private readonly DashboardStatsCache $dashboardStatsCache,
    ) {}

    public function saved(Booking $booking): void
    {
        if ($booking->wasRecentlyCreated || $booking->wasChanged('status')) {
            $this->dashboardStatsCache->invalidate();
        }
    }
}
