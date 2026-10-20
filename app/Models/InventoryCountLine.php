<?php

namespace App\Models;

use App\Enums\InventoryCountReviewStatus;
use Database\Factories\InventoryCountLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inventory_count_session_id', 'product_id', 'stock_lot_id', 'asset_id',
    'expected_qty_snapshot', 'counted_qty', 'expected_qty_at_count', 'variance_qty',
    'counted_by', 'counted_at', 'review_status', 'count_notes', 'review_notes',
    'reviewed_by', 'reviewed_at', 'adjustment_movement_id',
])]
class InventoryCountLine extends Model
{
    /** @use HasFactory<InventoryCountLineFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expected_qty_snapshot' => 'integer',
            'counted_qty' => 'integer',
            'expected_qty_at_count' => 'integer',
            'variance_qty' => 'integer',
            'counted_at' => 'datetime',
            'review_status' => InventoryCountReviewStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(InventoryCountSession::class, 'inventory_count_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockLot(): BelongsTo
    {
        return $this->belongsTo(StockLot::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function countedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function adjustmentMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class, 'adjustment_movement_id');
    }
}
