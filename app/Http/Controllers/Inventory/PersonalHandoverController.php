<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\HandoverLog;
use App\Services\Inventory\HandoverReviewSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PersonalHandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $view = $request->query('view') === 'all' ? 'all' : 'pending';
        $handovers = HandoverLog::query()->where('to_user_id', $request->user()->id)
            ->with(['asset:id,tag_code,product_id', 'asset.product:id,name', 'fromUser:id,name'])
            ->when($view === 'pending', fn ($query) => $query->pending())
            ->latest('initiated_at')->orderByDesc('id')->paginate(15)->withQueryString()
            ->through(fn (HandoverLog $handover): array => [
                'id' => $handover->id,
                'asset_name' => $handover->asset?->product?->name,
                'tag_code' => $handover->asset?->tag_code,
                'from_name' => $handover->fromUser?->name,
                'initiated_at' => $handover->initiated_at->toIso8601String(),
                'status' => $handover->status(),
            ]);

        return Inertia::render('inventory/handover/Mine', [
            'handovers' => $handovers, 'view' => $view,
            'can_manage' => $request->user()->can('viewAny', HandoverLog::class),
        ]);
    }

    public function review(Request $request, HandoverLog $handoverLog, HandoverReviewSession $review): RedirectResponse
    {
        $this->authorize('verify', $handoverLog);
        if (! $handoverLog->isPending()) {
            return redirect()->route('inventory.handover.mine', ['view' => 'all']);
        }
        abort_if(blank($handoverLog->verification_token_hash), 409, __('Ask the property custodian to refresh this handover before reviewing it.'));
        $review->begin($request, $handoverLog);

        return redirect()->route('inventory.handover.verify', $handoverLog);
    }
}
