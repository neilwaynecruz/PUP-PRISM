<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionTemplate;
use App\Models\StockMovement;
use App\Policies\AssetPolicy;
use App\Policies\BookingPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RequisitionPolicy;
use App\Policies\RequisitionTemplatePolicy;
use App\Policies\StockMovementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Requisition::class, RequisitionPolicy::class);
        Gate::policy(RequisitionTemplate::class, RequisitionTemplatePolicy::class);
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(StockMovement::class, StockMovementPolicy::class);
    }
}
