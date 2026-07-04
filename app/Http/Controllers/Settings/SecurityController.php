<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Http\Requests\Settings\TwoFactorAuthenticationRequest;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class SecurityController extends Controller
{
    public function __construct()
    {
        if (Features::canManageTwoFactorAuthentication()
            && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword')) {
            $this->middleware('password.confirm')->only('edit', 'setupData');
        }
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(TwoFactorAuthenticationRequest $request): Response
    {
        $props = [
            'canManageTwoFactor' => Features::canManageTwoFactorAuthentication(),
        ];

        if (Features::canManageTwoFactorAuthentication()) {
            $props['twoFactorEnabled'] = $request->user()->hasEnabledTwoFactorAuthentication();
            $props['twoFactorConfirmed'] = $request->user()->hasConfirmedTwoFactorAuthentication();
            $props['twoFactorSetupPending'] = $request->user()->hasPendingTwoFactorAuthenticationSetup();
            $props['requiresConfirmation'] = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
            $props['requiresPrivilegedTwoFactor'] = $request->user()->requiresPrivilegedTwoFactorConfirmation();
        }

        return Inertia::render('settings/Security', $props);
    }

    /**
     * Fetch the authenticated user's pending two-factor setup data.
     */
    public function setupData(TwoFactorAuthenticationRequest $request): JsonResponse
    {
        abort_unless(Features::canManageTwoFactorAuthentication(), 404);
        abort_if(
            $request->user()->two_factor_secret === null,
            404,
            'Two factor authentication has not been enabled.',
        );

        return response()->json([
            'svg' => $request->user()->twoFactorQrCodeSvg(),
            'url' => $request->user()->twoFactorQrCodeUrl(),
            'secretKey' => Fortify::currentEncrypter()->decrypt($request->user()->two_factor_secret),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        AuditLogService::logCustom(
            'password_change',
            __('User password updated.'),
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
