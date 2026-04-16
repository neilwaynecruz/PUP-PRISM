<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\HandoverLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandoverReceiptController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, HandoverLog $handoverLog): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! $user->hasAnyRole(['Admin', 'Property Custodian']) && $user->id !== $handoverLog->to_user_id) {
            abort(403);
        }

        $handoverLog->load([
            'asset:id,tag_code,product_id,position_id',
            'asset.product:id,name,sku',
            'fromUser:id,name,email',
            'toUser:id,name,email',
            'fromPosition:id,department_id,title,code',
            'toPosition:id,department_id,title,code',
            'fromPosition.department:id,name',
            'toPosition.department:id,name',
            'verifiedBy:id,name,email',
        ]);

        if ($handoverLog->verified_at === null) {
            abort(403);
        }

        $pdf = Pdf::loadView('inventory.handover_receipt', [
            'handover' => $handoverLog,
        ])->setPaper('a4');

        return $pdf->download("internal-property-accountability-receipt-{$handoverLog->id}.pdf");
    }
}
