<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use App\Enums\AssetTransferStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\ProductType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\HandoverCancelRequest;
use App\Http\Requests\Inventory\HandoverInitiateRequest;
use App\Http\Resources\HandoverLogResource;
use App\Models\Asset;
use App\Models\AssetMaintenance;
use App\Models\AssetTransfer;
use App\Models\Booking;
use App\Models\HandoverLog;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\HandoverVerificationNotification;
use App\Services\AuditLogService;
use App\Services\Inventory\AssetIntegrityService;
use App\Services\Inventory\HandoverSignatureValidator;
use App\Services\InventoryRealtimeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class HandoverController extends Controller
{
    /**
     * Minimum wait before a verification link can be sent again.
     */
    private const RESEND_COOLDOWN_SECONDS = 120;

    public function __construct(
        private readonly InventoryRealtimeService $realtime,
        private readonly HandoverSignatureValidator $signatureValidator,
        private readonly AssetIntegrityService $assetIntegrity,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HandoverLog::class);

        $filters = [
            'asset_search' => Str::limit($request->string('asset_search')->trim()->toString(), 120, ''),
            'asset' => Str::limit($request->string('asset')->trim()->toString(), 64, ''),
            'recipient_search' => Str::limit($request->string('recipient_search')->trim()->toString(), 120, ''),
            'history_status' => in_array($request->string('history_status')->toString(), [
                HandoverLog::STATUS_PENDING,
                HandoverLog::STATUS_VERIFIED,
                HandoverLog::STATUS_CANCELLED,
            ], true) ? $request->string('history_status')->toString() : '',
            'history_search' => Str::limit($request->string('history_search')->trim()->toString(), 120, ''),
        ];

        $selectedAsset = $filters['asset'] !== ''
            ? Asset::query()
                ->with([
                    'product:id,name,category_id',
                    'product.category:id,name',
                    'position:id,department_id,title,code',
                    'position.department:id,name',
                    'currentLocation:id,code,name',
                ])
                ->where('tag_code', $filters['asset'])
                ->first()
            : null;

        return Inertia::render('inventory/handover/Initiate', [
            'filters' => $filters,
            'assetOptions' => fn (): array => $this->assetOptions($filters['asset_search']),
            'selectedAsset' => fn (): ?array => $selectedAsset !== null ? $this->presentSelectedAsset($selectedAsset) : null,
            'selectedAssetMissing' => $filters['asset'] !== '' && $selectedAsset === null,
            'users' => fn (): array => $this->recipientOptions($filters['recipient_search'], $selectedAsset?->position_id),
            'recent' => fn (): array => $this->recentHandovers($request, $filters),
            'counts' => fn (): array => [
                'pending' => HandoverLog::query()->pending()->count(),
                'verified_this_month' => HandoverLog::query()
                    ->whereNotNull('verified_at')
                    ->where('verified_at', '>=', CarbonImmutable::now()->startOfMonth())
                    ->count(),
                'cancelled' => HandoverLog::query()->whereNotNull('cancelled_at')->count(),
            ],
        ]);
    }

    public function store(HandoverInitiateRequest $request): RedirectResponse
    {
        $this->authorize('create', HandoverLog::class);

        $validated = $request->validated();

        $asset = Asset::query()->where('tag_code', $validated['asset_tag_code'])->firstOrFail();

        $toUser = User::query()->whereKey($validated['to_user_id'])->firstOrFail(['id', 'position_id', 'name', 'email']);

        $rawToken = Str::random(64);

        $handover = DB::transaction(function () use ($request, $validated, $asset, $toUser, $rawToken): HandoverLog {
            $asset = Asset::query()->whereKey($asset->id)->lockForUpdate()->firstOrFail();
            $this->assetIntegrity->ensureHandOverable($asset);

            $fromPositionId = $asset->position_id ?? $request->user()->position_id;
            $now = CarbonImmutable::now();

            $handover = new HandoverLog([
                'asset_id' => $asset->id,
                'from_user_id' => $request->user()->id,
                'to_user_id' => $toUser->id,
                'from_position_id' => $fromPositionId,
                'to_position_id' => $toUser->position_id,
                'notes' => $validated['notes'] ?? null,
            ]);
            $handover->forceFill([
                'initiated_by' => $request->user()->id,
                'initiated_at' => $now,
                'last_sent_at' => $now,
                'verified_at' => null,
                'verified_by' => null,
                'verification_token_hash' => hash('sha256', $rawToken),
                'ip_address' => $request->ip(),
                'verified_ip_address' => null,
            ])->save();

            return $handover;
        });

        $toUser->notify(new HandoverVerificationNotification(
            handoverLogId: $handover->id,
            token: $rawToken,
        ));
        $this->realtime->handoverInitiated($handover);

        AuditLogService::logCustom(
            'handover_initiate',
            __('Handover initiated for asset :tag to :recipient.', [
                'tag' => $asset->tag_code,
                'recipient' => $toUser->name,
            ]),
            $handover,
            null,
            [
                'asset_id' => $asset->id,
                'asset_tag_code' => $asset->tag_code,
                'to_user_id' => $toUser->id,
                'recipient' => $toUser->name,
                'from_position_id' => $handover->from_position_id,
                'to_position_id' => $handover->to_position_id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Verification email queued for :recipient. Accountability changes only after they verify.', ['recipient' => $toUser->name])]);

        return to_route('inventory.handover.index');
    }

    public function cancel(HandoverCancelRequest $request, HandoverLog $handoverLog): RedirectResponse
    {
        $this->authorize('cancel', $handoverLog);

        $reason = $request->string('cancellation_reason')->trim()->toString();

        $wasCancelled = DB::transaction(function () use ($request, $handoverLog, $reason): bool {
            $handoverLog = HandoverLog::query()->whereKey($handoverLog->id)->lockForUpdate()->firstOrFail();

            if (! $handoverLog->isPending()) {
                return false;
            }

            $handoverLog->forceFill([
                'cancelled_at' => CarbonImmutable::now(),
                'cancelled_by' => $request->user()->id,
                'cancellation_reason' => $reason,
                // Invalidate the emailed link immediately.
                'verification_token_hash' => null,
            ])->save();

            return true;
        });

        if (! $wasCancelled) {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Only handovers waiting for verification can be cancelled.')]);

            return back();
        }

        AuditLogService::logCustom(
            'handover_cancel',
            __('Handover #:id cancelled before verification.', ['id' => $handoverLog->id]),
            $handoverLog,
            ['status' => HandoverLog::STATUS_PENDING],
            ['status' => HandoverLog::STATUS_CANCELLED, 'cancellation_reason' => $reason],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Handover cancelled. The verification link no longer works and the asset is available again.')]);

        return back();
    }

    public function resend(HandoverLog $handoverLog): RedirectResponse
    {
        $this->authorize('resend', $handoverLog);

        $rawToken = Str::random(64);

        $result = DB::transaction(function () use ($handoverLog, $rawToken): string {
            $handoverLog = HandoverLog::query()->whereKey($handoverLog->id)->lockForUpdate()->firstOrFail();

            if (! $handoverLog->isPending()) {
                return 'not_pending';
            }

            $lastSentAt = $handoverLog->last_sent_at ?? $handoverLog->initiated_at;

            if ($lastSentAt->diffInSeconds(CarbonImmutable::now()) < self::RESEND_COOLDOWN_SECONDS) {
                return 'cooldown';
            }

            // A new token replaces the old one, so earlier links stop working.
            $handoverLog->forceFill([
                'verification_token_hash' => hash('sha256', $rawToken),
                'last_sent_at' => CarbonImmutable::now(),
            ])->save();

            return 'sent';
        });

        if ($result === 'not_pending') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This handover is no longer waiting for verification.')]);

            return back();
        }

        if ($result === 'cooldown') {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('A link was sent moments ago. Please wait a couple of minutes before sending another.')]);

            return back();
        }

        $handoverLog->loadMissing('toUser');
        $handoverLog->toUser?->notify(new HandoverVerificationNotification(
            handoverLogId: $handoverLog->id,
            token: $rawToken,
        ));

        AuditLogService::logCustom(
            'handover_resend',
            __('New verification email for handover #:id queued.', ['id' => $handoverLog->id]),
            $handoverLog,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('A new verification link was sent. Earlier links no longer work.')]);

        return back();
    }

    public function verify(Request $request, HandoverLog $handoverLog): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        $this->authorize('verify', $handoverLog);

        if ($handoverLog->verified_at !== null) {
            $request->session()->forget("handover_verify_token.{$handoverLog->id}");
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Already verified.')]);

            return $this->postVerifyRedirect($user);
        }

        if ($handoverLog->cancelled_at !== null) {
            $request->session()->forget("handover_verify_token.{$handoverLog->id}");
            Inertia::flash('toast', ['type' => 'error', 'message' => __('This handover was cancelled and can no longer be verified.')]);

            return redirect()->route('dashboard');
        }

        $sessionKey = "handover_verify_token.{$handoverLog->id}";
        $token = (string) $request->session()->get($sessionKey, '');

        if (
            $token === ''
            || $handoverLog->verification_token_hash === null
            || ! hash_equals($handoverLog->verification_token_hash, hash('sha256', $token))
        ) {
            $request->session()->forget($sessionKey);

            abort(403, __('The verification session is missing or has been replaced. Reopen the latest handover email before signing. If the handover was resent, earlier links no longer work.'));
        }

        $request->validate([
            'signature_png' => ['required', 'string'],
        ]);

        $signaturePng = $this->signatureValidator->validate(
            $request->string('signature_png')->toString(),
        );

        $wasVerified = DB::transaction(function () use ($request, $handoverLog, $user, $signaturePng, $token): bool {
            $handoverLog = HandoverLog::query()->whereKey($handoverLog->id)->lockForUpdate()->firstOrFail();

            if ($handoverLog->to_user_id !== $user->id) {
                abort(403);
            }

            if ($handoverLog->verified_at !== null) {
                return false;
            }

            if (
                $handoverLog->cancelled_at !== null
                || $handoverLog->verification_token_hash === null
                || ! hash_equals($handoverLog->verification_token_hash, hash('sha256', $token))
            ) {
                abort(403);
            }

            $asset = Asset::query()->whereKey($handoverLog->asset_id)->lockForUpdate()->firstOrFail();
            $this->assetIntegrity->ensureHandOverable(
                asset: $asset,
                errorKey: 'token',
                ignoreHandoverLogId: $handoverLog->id,
            );

            $handoverLog->forceFill([
                'verified_at' => CarbonImmutable::now(),
                'verified_by' => $user->id,
                'verified_ip_address' => $request->ip(),
                'verification_token_hash' => null,
                'signature_png' => $signaturePng,
            ])->save();

            $asset->update([
                'position_id' => $handoverLog->to_position_id,
                'status' => AssetStatus::CheckedOut,
            ]);

            StockMovement::create([
                'movement_type' => 'transfer',
                'product_id' => $asset->product_id,
                'stock_lot_id' => null,
                'asset_id' => $asset->id,
                'requisition_id' => null,
                'qty_delta' => null,
                'qty_before' => null,
                'qty_after' => null,
                'performed_by' => $user->id,
                'accountable_position_id' => $handoverLog->to_position_id,
                'ip_address' => $request->ip(),
                'performed_at' => CarbonImmutable::now(),
                'notes' => $handoverLog->notes,
            ]);

            return true;
        });

        $request->session()->forget($sessionKey);

        if (! $wasVerified) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Already verified.')]);

            return $this->postVerifyRedirect($user);
        }

        $this->realtime->handoverVerified($handoverLog->fresh() ?? $handoverLog);

        AuditLogService::logCustom(
            'handover_verify',
            __('Handover #:id verified by recipient.', ['id' => $handoverLog->id]),
            $handoverLog->fresh() ?? $handoverLog,
            ['position_id' => $handoverLog->from_position_id],
            [
                'asset_id' => $handoverLog->asset_id,
                'verified_by' => $user->id,
                'position_id' => $handoverLog->to_position_id,
            ],
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Handover verified.')]);

        return $this->postVerifyRedirect($user);
    }

    private function postVerifyRedirect(User $user): RedirectResponse
    {
        return $user->hasAnyRole(['Admin', 'Property Custodian'])
            ? redirect()->route('inventory.handover.index')
            : redirect()->route('dashboard');
    }

    /**
     * Assets a custodian can hand over, flagged with why any of them are unavailable.
     *
     * @return list<array<string, mixed>>
     */
    private function assetOptions(string $search): array
    {
        $assets = Asset::query()
            ->with([
                'product:id,name',
                'position:id,department_id,title,code',
                'position.department:id,name',
                'currentLocation:id,code,name',
            ])
            ->whereHas('product', fn (Builder $query) => $query->where('type', ProductType::Asset))
            ->where('lifecycle_status', AssetLifecycleStatus::Active)
            ->whereNotIn('status', [AssetStatus::Condemned, AssetStatus::Unserviceable])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->whereLike('tag_code', "%{$search}%")
                        ->orWhereLike('serial_number', "%{$search}%")
                        ->orWhereHas('product', fn (Builder $product) => $product->whereLike('name', "%{$search}%"))
                        ->orWhereHas('position', fn (Builder $position) => $position->whereLike('title', "%{$search}%"))
                        ->orWhereHas('currentLocation', fn (Builder $location) => $location->whereLike('name', "%{$search}%"));
                });
            })
            ->orderBy('tag_code')
            ->limit(25)
            ->get(['id', 'product_id', 'position_id', 'current_location_id', 'tag_code', 'status', 'lifecycle_status']);

        $unavailable = $this->batchUnavailableReasons($assets);

        return $assets
            ->map(fn ($asset): array => [
                'id' => (int) $asset->id,
                'tag_code' => $asset->tag_code,
                'name' => $asset->product?->name,
                'status' => $asset->status->value,
                'position' => $asset->position ? [
                    'title' => $asset->position->title,
                    'department' => $asset->position->department?->name,
                ] : null,
                'location' => $asset->currentLocation?->name,
                'unavailable_reason' => $unavailable[$asset->id] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * Three set-based queries instead of four checks per asset.
     *
     * @param  Collection<int, Asset>  $assets
     * @return array<int, string>
     */
    private function batchUnavailableReasons(Collection $assets): array
    {
        $ids = $assets->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $reasons = [];

        $bookedIds = Booking::query()
            ->blocking()
            ->whereIn('asset_id', $ids)
            ->where('end_at', '>', CarbonImmutable::now())
            ->distinct()
            ->pluck('asset_id');

        foreach ($bookedIds as $id) {
            $reasons[(int) $id] = __('Reserved by an approved booking');
        }

        $maintenanceIds = AssetMaintenance::query()
            ->whereIn('asset_id', $ids)
            ->whereIn('status', [
                MaintenanceStatus::Reported->value,
                MaintenanceStatus::Scheduled->value,
                MaintenanceStatus::InProgress->value,
            ])
            ->distinct()
            ->pluck('asset_id');

        foreach ($maintenanceIds as $id) {
            $reasons[(int) $id] = __('Under maintenance');
        }

        $pendingIds = HandoverLog::query()
            ->pending()
            ->whereIn('asset_id', $ids)
            ->distinct()
            ->pluck('asset_id');

        foreach ($pendingIds as $id) {
            $reasons[(int) $id] = __('Handover awaiting verification');
        }

        $transferIds = AssetTransfer::query()
            ->whereIn('asset_id', $ids)
            ->where('status', AssetTransferStatus::Pending->value)
            ->distinct()
            ->pluck('asset_id');

        foreach ($transferIds as $id) {
            $reasons[(int) $id] = __('Location transfer awaiting approval');
        }

        return $reasons;
    }

    /**
     * @return array<string, mixed>
     */
    private function presentSelectedAsset(Asset $asset): array
    {
        $blockers = $this->assetIntegrity->handoverBlockers($asset);

        $lastVerified = HandoverLog::query()
            ->with('toUser:id,name,email')
            ->where('asset_id', $asset->id)
            ->whereNotNull('verified_at')
            ->latest('verified_at')
            ->first(['id', 'asset_id', 'to_user_id', 'verified_at']);

        $pending = HandoverLog::query()
            ->with('toUser:id,name,email')
            ->where('asset_id', $asset->id)
            ->pending()
            ->latest('initiated_at')
            ->first(['id', 'asset_id', 'to_user_id', 'initiated_at']);

        $positionHolders = $asset->position_id !== null
            ? User::query()
                ->where('position_id', $asset->position_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(5)
                ->pluck('name')
                ->all()
            : [];

        return [
            'id' => (int) $asset->id,
            'tag_code' => $asset->tag_code,
            'name' => $asset->product?->name,
            'category' => $asset->product?->category?->name,
            'status' => $asset->status->value,
            'condition' => $asset->condition_status->value,
            'lifecycle' => $asset->lifecycle_status->value,
            'location' => $asset->currentLocation ? [
                'code' => $asset->currentLocation->code,
                'name' => $asset->currentLocation->name,
            ] : null,
            'position' => $asset->position ? [
                'id' => (int) $asset->position->id,
                'title' => $asset->position->title,
                'code' => $asset->position->code,
                'department' => $asset->position->department?->name,
            ] : null,
            'accountable_person' => $lastVerified?->toUser?->name,
            'accountable_since' => $lastVerified?->verified_at?->toIso8601String(),
            'position_holders' => $positionHolders,
            'pending_handover' => $pending ? [
                'id' => (int) $pending->id,
                'to' => $pending->toUser?->name,
                'initiated_at' => $pending->initiated_at->toIso8601String(),
            ] : null,
            'eligible' => $blockers === [],
            'blockers' => $blockers,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recipientOptions(string $search, ?int $assetPositionId): array
    {
        return User::query()
            ->with(['position:id,department_id,title,code,is_active', 'position.department:id,name'])
            ->where('is_active', true)
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->whereLike('name', "%{$search}%")
                        ->orWhereLike('email', "%{$search}%")
                        ->orWhereHas('position', function (Builder $position) use ($search): void {
                            $position->whereLike('title', "%{$search}%")
                                ->orWhereLike('code', "%{$search}%")
                                ->orWhereHas('department', fn (Builder $department) => $department->whereLike('name', "%{$search}%"));
                        });
                });
            })
            ->orderByRaw('position_id IS NULL')
            ->orderBy('name')
            ->limit(25)
            ->get(['id', 'name', 'email', 'position_id'])
            ->map(function ($user) use ($assetPositionId): array {
                $reason = match (true) {
                    $user->position === null => __('No position assigned'),
                    ! $user->position->is_active => __('Position is inactive'),
                    $assetPositionId !== null && (int) $user->position_id === $assetPositionId => __('Already accountable for this asset'),
                    default => null,
                };

                return [
                    'id' => (int) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'position' => $user->position ? [
                        'id' => (int) $user->position->id,
                        'title' => $user->position->title,
                        'code' => $user->position->code,
                        'department' => $user->position->department?->name,
                    ] : null,
                    'unavailable_reason' => $reason,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, mixed>
     */
    private function recentHandovers(Request $request, array $filters): array
    {
        $search = $filters['history_search'];

        $paginator = HandoverLog::query()
            ->with([
                'asset:id,product_id,position_id,tag_code',
                'asset.product:id,name',
                'toUser:id,name,email',
                'initiatedBy:id,name',
                'cancelledBy:id,name',
                'fromPosition:id,department_id,title,code',
                'toPosition:id,department_id,title,code',
                'fromPosition.department:id,name',
                'toPosition.department:id,name',
            ])
            ->when($filters['history_status'] === HandoverLog::STATUS_PENDING, fn (Builder $query) => $query->pending())
            ->when($filters['history_status'] === HandoverLog::STATUS_VERIFIED, fn (Builder $query) => $query->whereNotNull('verified_at'))
            ->when($filters['history_status'] === HandoverLog::STATUS_CANCELLED, fn (Builder $query) => $query->whereNotNull('cancelled_at'))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->whereHas('asset', function (Builder $asset) use ($search): void {
                        $asset->whereLike('tag_code', "%{$search}%")
                            ->orWhereHas('product', fn (Builder $product) => $product->whereLike('name', "%{$search}%"));
                    })->orWhereHas('toUser', fn (Builder $user) => $user->whereLike('name', "%{$search}%")->orWhereLike('email', "%{$search}%"));
                });
            })
            ->orderByDesc('initiated_at')
            ->orderByDesc('id')
            ->paginate(10, pageName: 'history_page')
            ->withQueryString();

        return [
            'data' => HandoverLogResource::collectionForInertia($paginator->items(), $request),
            'links' => $paginator->linkCollection()->toArray(),
            'total' => $paginator->total(),
        ];
    }
}
