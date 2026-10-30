<?php

namespace App\Models;

use Database\Factories\HandoverLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'asset_id',
    'from_user_id',
    'to_user_id',
    'from_position_id',
    'to_position_id',
    'initiated_by',
    'initiated_at',
    'verified_at',
    'verified_by',
    'cancelled_at',
    'cancelled_by',
    'cancellation_reason',
    'last_sent_at',
    'verification_token_hash',
    'ip_address',
    'verified_ip_address',
    'signature_png',
    'notes',
])]
#[Hidden(['verification_token_hash', 'signature_png'])]
class HandoverLog extends Model
{
    /** @use HasFactory<HandoverLogFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Handovers still waiting for the recipient to verify. Cancelled handovers no longer block the asset.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('verified_at')->whereNull('cancelled_at');
    }

    public function isPending(): bool
    {
        return $this->verified_at === null && $this->cancelled_at === null;
    }

    public function status(): string
    {
        return match (true) {
            $this->verified_at !== null => self::STATUS_VERIFIED,
            $this->cancelled_at !== null => self::STATUS_CANCELLED,
            default => self::STATUS_PENDING,
        };
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function fromPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'from_position_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function toPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'to_position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initiated_at' => 'datetime',
            'verified_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'last_sent_at' => 'datetime',
        ];
    }
}
