<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
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
                'id' => $handoverLog->id,
                'token' => $request->string('token')->toString(),
                'tag_code' => $handoverLog->asset?->tag_code,
                'asset_name' => $handoverLog->asset?->product?->name,
                'from_user' => $handoverLog->fromUser?->only(['id', 'name', 'email']),
                'to_user' => $handoverLog->toUser?->only(['id', 'name', 'email']),
                'from_position' => $handoverLog->fromPosition ? [
                    'title' => $handoverLog->fromPosition->title,
                    'code' => $handoverLog->fromPosition->code,
                    'department' => $handoverLog->fromPosition->department?->name,
                ] : null,
                'to_position' => $handoverLog->toPosition ? [
                    'title' => $handoverLog->toPosition->title,
                    'code' => $handoverLog->toPosition->code,
                    'department' => $handoverLog->toPosition->department?->name,
                ] : null,
                'verified_at' => $handoverLog->verified_at?->toIso8601String(),
            ],
            'email_verified' => $request->user()?->hasVerifiedEmail() ?? false,
        ]);
    }
}
