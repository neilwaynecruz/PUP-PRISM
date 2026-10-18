<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandoverLogResource;
use App\Models\HandoverLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandoverVerificationController extends Controller
{
    public function __invoke(Request $request, HandoverLog $handoverLog): Response|RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->id !== $handoverLog->to_user_id) {
            abort(403);
        }

        $sessionKey = $this->sessionKey($handoverLog->id);
        $queryToken = $request->string('token')->trim()->toString();

        if ($queryToken !== '') {
            if ($handoverLog->verified_at === null) {
                if (! $this->tokenMatches($handoverLog, $queryToken)) {
                    abort(403);
                }

                $request->session()->put($sessionKey, $queryToken);
            }

            return redirect()->route('inventory.handover.verify', $handoverLog);
        }

        if ($handoverLog->verified_at === null) {
            $sessionToken = (string) $request->session()->get($sessionKey, '');

            if (! $this->tokenMatches($handoverLog, $sessionToken)) {
                $request->session()->forget($sessionKey);

                abort(403);
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
