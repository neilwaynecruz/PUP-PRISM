<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->is_active) {
            return $next($request);
        }

        if ($this->isApiRequest($request, $user)) {
            $accessToken = $user->currentAccessToken();

            if ($accessToken instanceof PersonalAccessToken) {
                $accessToken->delete();
            }

            return response()->json([
                'message' => __('Your account has been deactivated. Please contact an administrator.'),
            ], 403);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Inertia::flash('toast', [
            'type' => 'error',
            'message' => __('Your account has been deactivated. Please contact an administrator.'),
        ]);

        return redirect()->route('login');
    }

    private function isApiRequest(Request $request, User $user): bool
    {
        return $request->is('api/*') || $user->currentAccessToken() !== null;
    }
}
