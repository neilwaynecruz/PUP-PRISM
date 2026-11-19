<?php

namespace App\Services\Inventory;

use App\Models\HandoverLog;
use Illuminate\Http\Request;

class HandoverReviewSession
{
    public function begin(Request $request, HandoverLog $handover): void
    {
        $this->clear($request, $handover);
        $request->session()->put("handover_review.{$handover->id}", [
            'user_id' => $request->user()?->id,
            'token_hash' => $handover->verification_token_hash,
            'expires_at' => now()->addMinutes(15)->getTimestamp(),
        ]);
    }

    public function authorized(Request $request, HandoverLog $handover): bool
    {
        if ($request->user()?->id !== $handover->to_user_id || ! $handover->isPending()
            || blank($handover->verification_token_hash)) {
            return false;
        }

        $token = (string) $request->session()->get("handover_verify_token.{$handover->id}", '');
        if ($token !== '' && hash_equals($handover->verification_token_hash, hash('sha256', $token))) {
            return true;
        }

        $proof = $request->session()->get("handover_review.{$handover->id}");

        return is_array($proof) && ($proof['user_id'] ?? null) === $request->user()->id
            && is_int($proof['expires_at'] ?? null) && $proof['expires_at'] > now()->getTimestamp()
            && is_string($proof['token_hash'] ?? null)
            && hash_equals($handover->verification_token_hash, $proof['token_hash']);
    }

    public function clear(Request $request, HandoverLog $handover): void
    {
        $request->session()->forget(["handover_verify_token.{$handover->id}", "handover_review.{$handover->id}"]);
    }
}
