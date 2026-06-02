<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\HandoverInitiateRequest;
use App\Models\Asset;
use App\Models\HandoverLog;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    public function index(Request $request): Response
    {
        $recipientSearch = $request->string('recipient_search')->trim()->toString();

        return Inertia::render('inventory/handover/Initiate', [
            'filters' => [
                'recipient_search' => $recipientSearch,
            ],
            'users' => User::query()
                ->with(['position:id,department_id,title,code', 'position.department:id,name'])
                ->when($recipientSearch !== '', function ($query) use ($recipientSearch) {
                    $query->where(function ($query) use ($recipientSearch) {
                        $query->where('name', 'like', "%{$recipientSearch}%")
                            ->orWhere('email', 'like', "%{$recipientSearch}%");
                    });
                })
                ->orderBy('name')
                ->limit(25)
                ->get(['id', 'name', 'email', 'position_id'])
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position ? [
                        'id' => $user->position->id,
                        'title' => $user->position->title,
                        'code' => $user->position->code,
                        'department' => $user->position->department?->name,
                    ] : null,
                ]),
            'recent' => HandoverLog::query()
                ->with([
                    'asset:id,product_id,position_id,tag_code',
                    'asset.product:id,name',
                    'toUser:id,name,email',
                    'fromPosition:id,department_id,title,code',
                    'toPosition:id,department_id,title,code',
                    'fromPosition.department:id,name',
                    'toPosition.department:id,name',
                ])
                ->orderByDesc('initiated_at')
                ->limit(20)
                ->get()
                ->map(fn (HandoverLog $h) => [
                    'id' => $h->id,
                    'tag_code' => $h->asset?->tag_code,
                    'asset_name' => $h->asset?->product?->name,
                    'to' => $h->toUser?->only(['id', 'name', 'email']),
                    'from_position' => $h->fromPosition ? [
                        'title' => $h->fromPosition->title,
                        'department' => $h->fromPosition->department?->name,
                    ] : null,
                    'to_position' => $h->toPosition ? [
                        'title' => $h->toPosition->title,
                        'department' => $h->toPosition->department?->name,
                    ] : null,
                    'initiated_at' => $h->initiated_at?->toIso8601String(),
                    'verified_at' => $h->verified_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(HandoverInitiateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $asset = Asset::query()->where('tag_code', $validated['asset_tag_code'])->firstOrFail();

        $toUser = User::query()->whereKey($validated['to_user_id'])->firstOrFail(['id', 'position_id', 'name', 'email']);

        $rawToken = Str::random(64);

        $handover = DB::transaction(function () use ($request, $validated, $asset, $toUser, $rawToken): HandoverLog {
            $fromPositionId = $asset->position_id ?? $request->user()->position_id;

            return HandoverLog::create([
                'asset_id' => $asset->id,
                'from_user_id' => $request->user()->id,
                'to_user_id' => $toUser->id,
                'from_position_id' => $fromPositionId,
                'to_position_id' => $toUser->position_id,
                'initiated_by' => $request->user()->id,
                'initiated_at' => CarbonImmutable::now(),
                'verified_at' => null,
                'verified_by' => null,
                'verification_token_hash' => hash('sha256', $rawToken),
                'ip_address' => $request->ip(),
                'verified_ip_address' => null,
                'notes' => $validated['notes'] ?? null,
            ]);
        });

        $toUser->notify(new HandoverVerificationNotification(
            handoverLogId: $handover->id,
            token: $rawToken,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification link sent to recipient.')]);

        return back();
    }

    public function verify(Request $request, HandoverLog $handoverLog): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->id !== $handoverLog->to_user_id) {
            abort(403);
        }

        if ($handoverLog->verified_at !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Already verified.')]);

            return redirect()->route('inventory.handover.index');
        }

        $request->validate([
            'signature_png' => ['required', 'string', 'max:300000'],
        ]);

        $token = $request->string('token')->toString();

        if (! hash_equals((string) $handoverLog->verification_token_hash, hash('sha256', $token))) {
            abort(403);
        }

        DB::transaction(function () use ($request, $handoverLog, $user): void {
            $handoverLog = HandoverLog::query()->whereKey($handoverLog->id)->lockForUpdate()->firstOrFail();

            if ($handoverLog->verified_at !== null) {
                return;
            }

            $asset = Asset::query()->whereKey($handoverLog->asset_id)->lockForUpdate()->firstOrFail();

            $handoverLog->update([
                'verified_at' => CarbonImmutable::now(),
                'verified_by' => $user->id,
                'verified_ip_address' => $request->ip(),
                'verification_token_hash' => null,
                'signature_png' => $request->string('signature_png')->toString() ?: null,
            ]);

            $asset->update([
                'position_id' => $handoverLog->to_position_id,
                'status' => 'Checked_Out',
            ]);

            StockMovement::create([
                'movement_type' => 'transfer',
                'product_id' => $asset->product_id,
                'stock_lot_id' => null,
                'asset_id' => $asset->id,
                'requisition_id' => null,
                'qty_delta' => null,
                'performed_by' => $user->id,
                'accountable_position_id' => $handoverLog->to_position_id,
                'ip_address' => $request->ip(),
                'performed_at' => CarbonImmutable::now(),
                'notes' => $handoverLog->notes,
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Handover verified.')]);

        return redirect()->route('inventory.handover.index');
    }
}
