<?php

namespace App\Services\Inventory;

use App\Enums\AssetLifecycleStatus;
use App\Enums\InventoryCountReviewStatus;
use App\Enums\InventoryCountStatus;
use App\Enums\ProductType;
use App\Models\Asset;
use App\Models\InventoryCountLine;
use App\Models\InventoryCountSession;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PhysicalCountService
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createSession(User $user, array $payload): InventoryCountSession
    {
        return DB::transaction(function () use ($user, $payload): InventoryCountSession {
            $productIds = collect($payload['product_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->sort()->values();
            $assetIds = collect($payload['asset_ids'] ?? [])->map(fn ($id): int => (int) $id)->unique()->sort()->values();

            $assignedUser = User::query()->whereKey($payload['assigned_to'])->lockForUpdate()->firstOrFail();
            if (! $assignedUser->is_active || ! $assignedUser->hasAnyRole(['Admin', 'Supply Head', 'Property Custodian'])) {
                throw new \RuntimeException('Choose an active inventory staff member as the assigned counter.');
            }

            $products = Product::query()
                ->whereIn('id', $productIds)
                ->where('type', ProductType::Consumable)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($products->count() !== $productIds->count()) {
                throw new \RuntimeException('One or more selected products cannot be included in a physical count.');
            }

            $lots = StockLot::query()
                ->whereIn('product_id', $productIds)
                ->where('qty_remaining', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $assets = Asset::query()
                ->whereIn('id', $assetIds)
                ->where('lifecycle_status', AssetLifecycleStatus::Active)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($assets->count() !== $assetIds->count()) {
                throw new \RuntimeException('One or more selected assets cannot be included in a physical count.');
            }

            $locationId = isset($payload['location_id']) ? (int) $payload['location_id'] : null;
            if ($locationId !== null) {
                $location = Location::query()->whereKey($locationId)->lockForUpdate()->firstOrFail();
                if (! $location->is_active) {
                    throw new \RuntimeException('Choose an active physical location for this count.');
                }
            }

            if ($locationId !== null && $assets->contains(
                fn (Asset $asset): bool => $asset->current_location_id !== $locationId,
            )) {
                throw new \RuntimeException('A selected asset has a different or unrecorded location.');
            }

            if ($lots->isEmpty() && $assets->isEmpty()) {
                throw new \RuntimeException('The selected scope has no stock lots or active assets to count.');
            }

            $snapshotAt = CarbonImmutable::now();
            $session = InventoryCountSession::create([
                'reference_no' => 'COUNT-'.$snapshotAt->format('Ymd').'-'.Str::upper(Str::random(8)),
                'location_id' => $locationId,
                'created_by' => $user->id,
                'assigned_to' => (int) $payload['assigned_to'],
                'counting_date' => $payload['counting_date'],
                'scope' => $payload['scope'],
                'status' => InventoryCountStatus::Draft,
                'snapshot_at' => $snapshotAt,
                'movement_cutoff_id' => (int) (StockMovement::query()->max('id') ?? 0),
                'remarks' => $payload['remarks'] ?? null,
            ]);

            foreach ($lots as $lot) {
                $session->lines()->create([
                    'product_id' => $lot->product_id,
                    'stock_lot_id' => $lot->id,
                    'asset_id' => null,
                    'expected_qty_snapshot' => $lot->qty_remaining,
                    'review_status' => InventoryCountReviewStatus::Pending,
                ]);
            }

            foreach ($assets as $asset) {
                $session->lines()->create([
                    'product_id' => $asset->product_id,
                    'stock_lot_id' => null,
                    'asset_id' => $asset->id,
                    'expected_qty_snapshot' => 1,
                    'review_status' => InventoryCountReviewStatus::Pending,
                ]);
            }

            AuditLogService::logCreated($session, "Physical count {$session->reference_no} created.");

            return $session;
        });
    }

    public function recordLine(
        User $user,
        InventoryCountSession $session,
        InventoryCountLine $line,
        int $countedQuantity,
        ?string $notes,
    ): InventoryCountLine {
        return DB::transaction(function () use ($user, $session, $line, $countedQuantity, $notes): InventoryCountLine {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $line = InventoryCountLine::query()
                ->whereKey($line->id)
                ->where('inventory_count_session_id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($session->status, [InventoryCountStatus::Draft, InventoryCountStatus::InProgress], true)) {
                throw new \RuntimeException('Counts can only be entered while the session is a draft or in progress.');
            }

            if ($line->asset_id !== null && $countedQuantity > 1) {
                throw new \RuntimeException('A tagged asset count must be 0 for missing or 1 for present.');
            }

            if ($line->stock_lot_id !== null) {
                StockLot::query()
                    ->whereKey($line->stock_lot_id)
                    ->where('product_id', $line->product_id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            if ($line->asset_id !== null) {
                $asset = Asset::query()->whereKey($line->asset_id)->lockForUpdate()->firstOrFail();
                if ($asset->lifecycle_status !== AssetLifecycleStatus::Active) {
                    throw new \RuntimeException('This asset is no longer active. Review the count session before continuing.');
                }
                if ($session->location_id !== null && $asset->current_location_id !== $session->location_id) {
                    throw new \RuntimeException('This asset moved after the count session was created. Review its location before counting.');
                }
            }

            $countedAt = CarbonImmutable::now();
            $movementDelta = $line->stock_lot_id === null
                ? 0
                : (int) StockMovement::query()
                    ->where('stock_lot_id', $line->stock_lot_id)
                    ->where('id', '>', $session->movement_cutoff_id)
                    ->where('created_at', '<=', $countedAt)
                    ->sum('qty_delta');
            $expectedAtCount = $line->expected_qty_snapshot + $movementDelta;

            if ($expectedAtCount < 0) {
                throw new \RuntimeException('The movement history for this line is inconsistent. Review the stock lot before counting.');
            }

            $oldValues = $line->toArray();
            $line->update([
                'counted_qty' => $countedQuantity,
                'expected_qty_at_count' => $expectedAtCount,
                'variance_qty' => $countedQuantity - $expectedAtCount,
                'counted_by' => $user->id,
                'counted_at' => $countedAt,
                'review_status' => InventoryCountReviewStatus::Pending,
                'count_notes' => $notes,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]);

            if ($session->status === InventoryCountStatus::Draft) {
                $session->update(['status' => InventoryCountStatus::InProgress]);
            }

            AuditLogService::logUpdated($line, $oldValues, "Count line #{$line->id} recorded for {$session->reference_no}.");

            return $line->fresh() ?? $line;
        });
    }

    public function submit(User $user, InventoryCountSession $session): InventoryCountSession
    {
        return DB::transaction(function () use ($session): InventoryCountSession {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($session->status, [InventoryCountStatus::Draft, InventoryCountStatus::InProgress], true)) {
                throw new \RuntimeException('Only draft or in-progress count sessions can be submitted.');
            }

            $lines = $session->lines()->lockForUpdate()->get();
            if ($lines->isEmpty() || $lines->contains(fn (InventoryCountLine $line): bool => $line->counted_qty === null)) {
                throw new \RuntimeException('Enter a counted quantity for every line before submitting the session.');
            }

            foreach ($lines->where('variance_qty', 0) as $line) {
                $line->update([
                    'review_status' => InventoryCountReviewStatus::Approved,
                    'review_notes' => 'No discrepancy found.',
                ]);
            }

            $session->update([
                'status' => InventoryCountStatus::Submitted,
                'submitted_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('submit', "Physical count {$session->reference_no} submitted for review.", $session);

            return $session->fresh() ?? $session;
        });
    }

    public function reviewLine(
        User $reviewer,
        InventoryCountSession $session,
        InventoryCountLine $line,
        InventoryCountReviewStatus $status,
        ?string $notes,
    ): InventoryCountLine {
        return DB::transaction(function () use ($reviewer, $session, $line, $status, $notes): InventoryCountLine {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            $line = InventoryCountLine::query()
                ->whereKey($line->id)
                ->where('inventory_count_session_id', $session->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($session->status !== InventoryCountStatus::Submitted) {
                throw new \RuntimeException('Count lines can only be reviewed after the session is submitted.');
            }

            if ($line->counted_by === $reviewer->id) {
                throw new \RuntimeException('A count must be reviewed by someone other than the person who recorded it.');
            }

            if ($status === InventoryCountReviewStatus::Pending) {
                throw new \RuntimeException('Choose approve, request recount, or reject.');
            }

            if ($status === InventoryCountReviewStatus::RecountRequested) {
                $line->update([
                    'counted_qty' => null,
                    'expected_qty_at_count' => null,
                    'variance_qty' => null,
                    'counted_by' => null,
                    'counted_at' => null,
                    'review_status' => $status,
                    'review_notes' => $notes,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => CarbonImmutable::now(),
                ]);
                $session->update(['status' => InventoryCountStatus::InProgress, 'submitted_at' => null]);
            } else {
                $line->update([
                    'review_status' => $status,
                    'review_notes' => $notes,
                    'reviewed_by' => $reviewer->id,
                    'reviewed_at' => CarbonImmutable::now(),
                ]);
            }

            AuditLogService::logCustom('review', "Count line #{$line->id} reviewed for {$session->reference_no}.", $line);

            return $line->fresh() ?? $line;
        });
    }

    public function approve(User $approver, InventoryCountSession $session, ?string $ipAddress = null): InventoryCountSession
    {
        /** @var array{session: InventoryCountSession, changes: array<int, array{product: Product, on_hand_qty: int, delta: int}>} $result */
        $result = DB::transaction(function () use ($approver, $session, $ipAddress): array {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== InventoryCountStatus::Submitted) {
                throw new \RuntimeException('Only submitted count sessions can be approved.');
            }

            $lines = $session->lines()->with('session')->orderBy('id')->lockForUpdate()->get();
            if ($lines->contains(fn (InventoryCountLine $line): bool => in_array(
                $line->review_status,
                [InventoryCountReviewStatus::Pending, InventoryCountReviewStatus::RecountRequested],
                true,
            ))) {
                throw new \RuntimeException('Review every discrepancy before approving this session.');
            }

            if ($lines->contains(fn (InventoryCountLine $line): bool => $line->variance_qty !== 0 && $line->counted_by === $approver->id)) {
                throw new \RuntimeException('The session must be approved by someone who did not record its discrepancies.');
            }

            $changes = [];
            foreach ($lines as $line) {
                if ($line->review_status !== InventoryCountReviewStatus::Approved
                    || $line->stock_lot_id === null
                    || $line->variance_qty === 0) {
                    continue;
                }

                $change = $this->inventory->applyApprovedCountVariance($approver, $line, $ipAddress);
                $line->update(['adjustment_movement_id' => $change['movement']->id]);
                AuditLogService::logCreated(
                    $change['movement'],
                    "Approved variance posted for count {$session->reference_no}, line #{$line->id}.",
                );
                $changes[] = $change;
            }

            $session->update([
                'status' => InventoryCountStatus::Approved,
                'approved_by' => $approver->id,
                'approved_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('approve', "Physical count {$session->reference_no} approved.", $session);

            return ['session' => $session->fresh() ?? $session, 'changes' => $changes];
        });

        foreach ($result['changes'] as $change) {
            $this->inventory->publishInventoryDelta($change);
        }

        return $result['session'];
    }

    public function complete(InventoryCountSession $session): InventoryCountSession
    {
        return DB::transaction(function () use ($session): InventoryCountSession {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if ($session->status !== InventoryCountStatus::Approved) {
                throw new \RuntimeException('Only approved count sessions can be completed.');
            }

            $session->update([
                'status' => InventoryCountStatus::Completed,
                'completed_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('complete', "Physical count {$session->reference_no} completed.", $session);

            return $session->fresh() ?? $session;
        });
    }

    public function cancel(InventoryCountSession $session): InventoryCountSession
    {
        return DB::transaction(function () use ($session): InventoryCountSession {
            $session = InventoryCountSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
            if (! in_array($session->status, [InventoryCountStatus::Draft, InventoryCountStatus::InProgress, InventoryCountStatus::Submitted], true)) {
                throw new \RuntimeException('This count session can no longer be cancelled.');
            }

            $session->update([
                'status' => InventoryCountStatus::Cancelled,
                'cancelled_at' => CarbonImmutable::now(),
            ]);
            AuditLogService::logCustom('cancel', "Physical count {$session->reference_no} cancelled.", $session);

            return $session->fresh() ?? $session;
        });
    }
}
