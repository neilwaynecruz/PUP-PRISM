<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'type',
    'product_id',
    'stock_lot_id',
    'message',
    'detected_at',
    'acknowledged_at',
    'acknowledged_by',
    'assigned_to',
    'resolved_at',
    'resolved_by',
    'resolution_notes',
])]
class InventoryAlert extends Model
{
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->resolved_at === null;
    }

    /**
     * Urgency derived from the alert's current stock facts (critical, high, or medium).
     *
     * Uses eager-loaded product stock and lot data when available and falls back to "medium".
     */
    public function severity(): string
    {
        if ($this->type === 'expiring' && $this->relationLoaded('stockLot') && $this->stockLot?->expires_at !== null) {
            $daysLeft = (int) CarbonImmutable::today()->diffInDays($this->stockLot->expires_at, false);

            return match (true) {
                $daysLeft < 0 => 'critical',
                $daysLeft <= 3 => 'high',
                default => 'medium',
            };
        }

        if ($this->type === 'low_stock' && $this->relationLoaded('product') && $this->product?->relationLoaded('stock')) {
            return ((int) ($this->product->stock?->on_hand_qty ?? 0)) <= 0 ? 'critical' : 'high';
        }

        return 'medium';
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<StockLot, $this>
     */
    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
