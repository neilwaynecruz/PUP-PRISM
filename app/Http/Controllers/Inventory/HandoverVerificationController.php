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
        $sessionKey = $this->sessionKey($handoverLog->id);
        $queryToken = $request->string('token')->trim()->toString();

        if ($queryToken !== '') {
            $request->session()->put($sessionKey, $queryToken);

            return redirect()->route('inventory.handover.verify', $handoverLog);
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
            'handover' => [
                ...(new HandoverLogResource($handoverLog))->resolve($request),
                'token' => (string) $request->session()->get($sessionKey, ''),
            ],
            'email_verified' => $request->user()?->hasVerifiedEmail() ?? false,
        ]);
    }

    private function sessionKey(int $handoverLogId): string
    {
        return "handover_verify_token.{$handoverLogId}";
    }
}
