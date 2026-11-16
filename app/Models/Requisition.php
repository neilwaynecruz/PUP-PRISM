<?php

namespace App\Models;

use App\Enums\RequisitionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property CarbonImmutable|null $deleted_at
 * @property string|null $deletion_reason
 * @property RequisitionStatus $status
 * @property CarbonImmutable|null $needed_at
 * @property CarbonImmutable|null $approved_at
 * @property CarbonImmutable|null $issued_at
 */
#[Fillable([
    'requester_id',
    'requester_position_id',
    'notes',
])]
class Requisition extends Model
{
    /** @use HasFactory<RequisitionFactory> */
    use HasFactory, SoftDeletes;

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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function issuedPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'issued_position_id');
    }

    /**
     * @return HasMany<RequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RequisitionLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function hasRemainingQuantities(): bool
    {
        return $this->lines->contains(fn (RequisitionLine $line) => $line->remainingQuantity() > 0);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'status' => RequisitionStatus::class,
        ];
    }
}
