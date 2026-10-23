<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\User;
use App\Observers\BookingObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\RequisitionObserver;
use App\Observers\StockMovementObserver;
use App\Observers\UserObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        if (app()->environment('e2e')) {
            Vite::useHotFile(storage_path('framework/vite.e2e.hot'));
        }

        Booking::observe(BookingObserver::class);
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        Requisition::observe(RequisitionObserver::class);
        StockMovement::observe(StockMovementObserver::class);
        User::observe(UserObserver::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}
