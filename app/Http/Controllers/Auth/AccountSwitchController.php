<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\PreventClientCaching;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AccountSwitchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): Response
    {
        $destination = route('inventory.handover.mine');
        $resume = $request->session()->get('handover_switch_destination');
        if ($request->boolean('resume_handover') && is_array($resume)
            && is_int($resume['handover_id'] ?? null) && is_string($resume['token'] ?? null)
            && is_int($resume['expires_at'] ?? null) && $resume['expires_at'] > now()->getTimestamp()) {
            $destination = route('inventory.handover.verify', ['handoverLog' => $resume['handover_id'], 'token' => $resume['token']]);
        }

        Auth::guard(config('fortify.guard'))->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('url.intended', $destination);

        return PreventClientCaching::apply($request->header('X-Inertia')
            ? Inertia::location(route('login'))
            : redirect()->route('login'));
    }
}
