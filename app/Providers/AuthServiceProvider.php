<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Policies\BookingPolicy;
use App\Policies\ProductPolicy;
use App\Policies\RequisitionPolicy;
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
    }
}
