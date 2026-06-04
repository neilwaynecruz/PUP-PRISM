<?php

namespace App\Models;

use App\Enums\BookingStatus;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'asset_id',
    'requester_id',
    'requester_position_id',
    'approver_id',
    'approver_position_id',
    'requested_ip_address',
    'approved_ip_address',
    'start_at',
    'end_at',
    'status',
    'purpose',
])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory, SoftDeletes;

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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function requesterPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'requester_position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function approverPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'approver_position_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('status', self::blockingStatuses());
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForAssetWindow(
        Builder $query,
        int $assetId,
        \DateTimeInterface $startAt,
        \DateTimeInterface $endAt,
        ?int $ignoreBookingId = null,
    ): Builder {
        return $query
            ->where('asset_id', $assetId)
            ->when(
                $ignoreBookingId !== null,
                fn (Builder $builder) => $builder->whereKeyNot($ignoreBookingId),
            )
            ->where('start_at', '<', $endAt)
            ->where('end_at', '>', $startAt);
    }

    /**
     * @return array<int, string>
     */
    public static function blockingStatuses(): array
    {
        return [
            BookingStatus::Approved->value,
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'status' => BookingStatus::class,
        ];
    }
}
