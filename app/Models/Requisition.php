<?php

namespace App\Models;

use Database\Factories\RequisitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'requester_id',
    'requester_position_id',
    'approver_id',
    'approver_position_id',
    'requested_ip_address',
    'approved_ip_address',
    'approved_at',
    'issued_by',
    'issued_position_id',
    'issued_ip_address',
    'issued_at',
    'status',
    'notes',
])]
class Requisition extends Model
{
    /** @use HasFactory<RequisitionFactory> */
    use HasFactory;

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }
}
