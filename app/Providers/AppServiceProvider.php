<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\ForecastProfile;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\PurchaseOrder;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\User;
use App\Observers\BookingObserver;
use App\Observers\ForecastProfileObserver;
use App\Observers\ProductObserver;
use App\Observers\ProductStockObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\RequisitionObserver;
use App\Observers\StockMovementObserver;
use App\Observers\UserObserver;
use App\Services\Operations\EmailDeliveryException;
use App\Services\Operations\EmailDeliveryService;
use App\Services\Operations\ReliableMailChannel;
use App\Services\Operations\ResendRequestClient;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Resend\Client as ResendClient;
use Resend\Transporters\HttpTransporter;
use Resend\ValueObjects\ApiKey;
use Resend\ValueObjects\Transporter\BaseUri;
use Resend\ValueObjects\Transporter\Headers;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmailDeliveryService::class);
        $this->app->bind(MailChannel::class, ReliableMailChannel::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        Event::listen(JobQueued::class, fn (JobQueued $event) => app(EmailDeliveryService::class)->queued($event));
        Mail::extend('resend', function (array $config): ResendTransport {
            $key = $config['key'] ?? config('services.resend.key');
            if (! is_string($key) || $key === '') {
                app(EmailDeliveryService::class)->permanentFailure = true;
                app(EmailDeliveryService::class)->failureCategory = 'provider_configuration';
                throw new EmailDeliveryException('Resend is not configured.');
            }
            $http = new ResendRequestClient(
                $this->app->make(Client::class, ['config' => ['timeout' => 15, 'connect_timeout' => 5]]),
                app(EmailDeliveryService::class),
            );

            return new ResendTransport(new ResendClient(new HttpTransporter(
                $http,
                BaseUri::from('api.resend.com'),
                Headers::withAuthorization(ApiKey::from($key)),
            )));
        });

        if (app()->environment('e2e')) {
            Vite::useHotFile(storage_path('framework/vite.e2e.hot'));
        }

        Booking::observe(BookingObserver::class);
        ForecastProfile::observe(ForecastProfileObserver::class);
        Product::observe(ProductObserver::class);
        ProductStock::observe(ProductStockObserver::class);
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

        Model::preventLazyLoading(! app()->isProduction());

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
            return Limit::perMinute(60)->by($request->user('sanctum')?->id ?: $request->ip());
        });
    }
}
