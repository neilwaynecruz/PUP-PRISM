<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandoverLogResource;
use App\Models\HandoverLog;
use App\Services\Inventory\HandoverReviewSession;
use App\Support\PreventClientCaching;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HandoverVerificationController extends Controller
{
    public function __construct(private readonly HandoverReviewSession $review) {}

    public function __invoke(Request $request, HandoverLog $handoverLog): Response|RedirectResponse|HttpResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->id !== $handoverLog->to_user_id) {
            $token = $request->string('token')->trim()->toString();
            $request->session()->put('handover_switch_destination', [
                'handover_id' => $handoverLog->id, 'token' => strlen($token) <= 128 ? $token : '',
                'expires_at' => now()->addMinutes(10)->getTimestamp(),
            ]);

            return $this->recovery($request, __('This handover belongs to another account'),
                __('Sign out and reopen the latest handover email as its intended recipient, or use Switch account below. Administrators and senders cannot accept it for the recipient.'), true);
        }

        $sessionKey = $this->sessionKey($handoverLog->id);

        if ($handoverLog->cancelled_at !== null) {
            $this->review->clear($request, $handoverLog);
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This handover was cancelled by the property office and no longer needs your verification.')]);

            return redirect()->route('dashboard');
        }

        $queryToken = $request->string('token')->trim()->toString();

        if ($queryToken !== '') {
            if ($handoverLog->verified_at === null) {
                if (! $this->tokenMatches($handoverLog, $queryToken)) {
                    $this->review->clear($request, $handoverLog);

                    return $this->recovery($request, __('This link is no longer valid'),
                        __('Open the latest handover email, or sign in and open My Pending Handovers to start a fresh review.'));
                }

                $request->session()->put($sessionKey, $queryToken);
            }

            return redirect()->route('inventory.handover.verify', $handoverLog);
        }

        if ($handoverLog->verified_at === null) {
            if (! $this->review->authorized($request, $handoverLog)) {
                $this->review->clear($request, $handoverLog);

                return $this->recovery($request, __('Review session expired or unavailable'),
                    __('Reopen the latest handover email, or open My Pending Handovers and choose Review and Accept. Reviews started there expire after 15 minutes; the handover itself remains pending.'));
            }
        }

        $handoverLog->load([
            'asset:id,tag_code,product_id,position_id',
            'asset.product:id,name',
            'fromUser:id,name,email',
            'toUser:id,name,email',
            'fromPosition:id,department_id,title,code',
            'toPosition:id,department_id,title,code',
            'fromPosition.department:id,name',
            'toPosition.department:id,name',
        ]);

        return Inertia::render('inventory/handover/Verify', [
            'handover' => (new HandoverLogResource($handoverLog))->resolve($request),
            'email_verified' => $user->hasVerifiedEmail(),
        ]);
    }

    private function recovery(Request $request, string $title, string $message, bool $canSwitch = false): HttpResponse
    {
        Inertia::resolveUrlUsing(fn (Request $request): string => $request->getPathInfo());
        $page = Inertia::render('inventory/handover/Recovery', [
            'title' => $title, 'message' => $message, 'can_switch' => $canSwitch,
        ]);
        Inertia::resolveUrlUsing();
        $response = $page->toResponse($request);
        $response->setStatusCode(403);
        PreventClientCaching::apply($response);

        return $response;
    }

    private function tokenMatches(HandoverLog $handoverLog, string $token): bool
    {
        return $token !== ''
            && $handoverLog->verification_token_hash !== null
            && hash_equals($handoverLog->verification_token_hash, hash('sha256', $token));
    }

    private function sessionKey(int $handoverLogId): string
    {
        return "handover_verify_token.{$handoverLogId}";
    }
}
