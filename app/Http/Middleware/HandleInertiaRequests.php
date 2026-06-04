<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    /**
     * @var array<int, string>
     */
    private const CACHEABLE_ROUTE_NAMES = [
        'dashboard',
        'inventory.bookings.index',
        'inventory.handover.index',
        'inventory.movements.index',
        'inventory.products.index',
        'inventory.requisitions.index',
    ];

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

        if ($this->shouldCacheResponse($request, $response)) {
            $response->headers->set('Cache-Control', 'private, max-age=30');
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
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'roles' => $request->user()?->getRoleNames() ?? [],
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

    private function shouldCacheResponse(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if (! $response->isSuccessful()) {
            return false;
        }

        return $request->routeIs(self::CACHEABLE_ROUTE_NAMES);
    }
}
