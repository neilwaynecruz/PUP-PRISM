<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePrivilegedTwoFactorIsConfirmed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || ! $user->requiresPrivilegedTwoFactorConfirmation()
            || $user->hasConfirmedTwoFactorAuthentication()
            || $this->isExemptRoute($request)
        ) {
            return $next($request);
        }

        if ($this->isApiRequest($request, $user)) {
            return response()->json([
                'message' => __('Two-factor authentication is required for your role before accessing this resource.'),
            ], 403);
        }

        Inertia::flash('toast', [
            'type' => 'warning',
            'message' => __('Two-factor authentication is required for Admin and Supply Head accounts before accessing protected areas.'),
        ]);

        return redirect()->route('security.edit');
    }

    private function isApiRequest(Request $request, User $user): bool
    {
        return $request->is('api/*') || $user->currentAccessToken() !== null;
    }

    private function isExemptRoute(Request $request): bool
    {
        return $request->is(
            'logout',
            'session/keep-alive',
            'settings/security',
            'settings/security/two-factor/setup-data',
            'settings/password',
            'user/confirm-password',
            'user/confirm-password/*',
            'user/confirmed-password-status',
            'user/two-factor-authentication',
            'user/confirmed-two-factor-authentication',
            'user/two-factor-qr-code',
            'user/two-factor-secret-key',
            'user/two-factor-recovery-codes',
        );
    }
}
