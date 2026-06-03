<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Resources\HandoverLogResource;
use App\Models\HandoverLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HandoverVerificationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, HandoverLog $handoverLog): Response
    {
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
                'token' => $request->string('token')->toString(),
            ],
            'email_verified' => $request->user()?->hasVerifiedEmail() ?? false,
        ]);
    }
}
