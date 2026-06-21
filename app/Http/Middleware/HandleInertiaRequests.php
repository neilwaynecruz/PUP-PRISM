<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\StockMovement;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Handle the incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($this->shouldDisableClientCaching($request, $response)) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                'roles' => $user?->getRoleNames() ?? [],
                'permissions' => $this->sharePermissions($user),
            ],
            'session' => [
                'lifetimeMinutes' => (int) config('session.lifetime', 120),
                'warningMinutes' => max(1, min(5, ((int) config('session.lifetime', 120)) - 1)),
                'keepAliveUrl' => route('session.keep-alive', absolute: false),
                'loginUrl' => route('login', absolute: false),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function sharePermissions(?User $user): array
    {
        if (! $user instanceof User) {
            return [
                'viewProducts' => false,
                'createProducts' => false,
                'viewHandover' => false,
                'viewBookings' => false,
                'viewRequisitions' => false,
                'viewReceiving' => false,
                'viewMovements' => false,
                'viewAuditLogs' => false,
            ];
        }

        return [
            'viewProducts' => $user->can('viewAny', Product::class),
            'createProducts' => $user->can('create', Product::class),
            'viewHandover' => $user->hasAnyRole(['Admin', 'Property Custodian']),
            'viewBookings' => $user->can('viewAny', Booking::class),
            'viewRequisitions' => $user->can('viewAny', Requisition::class),
            'viewReceiving' => $user->hasAnyRole(['Admin', 'Supply Head']),
            'viewMovements' => $user->can('viewAny', StockMovement::class),
            'viewAuditLogs' => $user->can('viewAny', AuditLog::class),
        ];
    }

    private function shouldDisableClientCaching(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        return $request->user() instanceof User;
    }
}
