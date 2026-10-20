<?php

namespace App\Models;

use App\Enums\ApprovalStatus;
use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleEventType;
use App\Enums\AssetLifecycleStatus;
use Database\Factories\AssetLifecycleEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'asset_id', 'event_type', 'status', 'from_lifecycle_status', 'to_lifecycle_status',
    'from_condition_status', 'to_condition_status', 'reason', 'method',
    'supporting_reference', 'proposed_for', 'occurred_at', 'initiated_by',
    'approved_by', 'approved_at', 'notes',
])]
class AssetLifecycleEvent extends Model
{
    /** @use HasFactory<AssetLifecycleEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'event_type' => AssetLifecycleEventType::class,
            'status' => ApprovalStatus::class,
            'from_lifecycle_status' => AssetLifecycleStatus::class,
            'to_lifecycle_status' => AssetLifecycleStatus::class,
            'from_condition_status' => AssetConditionStatus::class,
            'to_condition_status' => AssetConditionStatus::class,
            'proposed_for' => 'date',
            'occurred_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
