<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'supplier_id',
    'po_number',
    'status',
    'subtotal',
    'tax',
    'total_amount',
    'requested_by',
    'approved_by',
    'expected_delivery_at',
    'sent_at',
    'received_at',
    'notes',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'subtotal' => 'float',
            'tax' => 'float',
            'total_amount' => 'float',
            'expected_delivery_at' => 'datetime',
            'sent_at' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function progressPercent(): float
    {
        $ordered = max(0, (int) $this->lines->sum('qty_ordered'));

        if ($ordered === 0) {
            return 0;
        }

        return round(min(100, ($this->lines->sum('qty_received') / $ordered) * 100), 2);
    }

    public function recalculateTotals(): void
    {
        $subtotal = round((float) $this->lines()->sum('subtotal'), 2);
        $tax = round((float) ($this->tax ?? 0), 2);

        $this->forceFill([
            'subtotal' => $subtotal,
            'total_amount' => round($subtotal + $tax, 2),
        ])->save();
    }

    public function refreshReceiptStatus(): void
    {
        $ordered = (int) $this->lines()->sum('qty_ordered');
        $received = (int) $this->lines()->sum('qty_received');

        if ($ordered === 0 || $this->status === PurchaseOrderStatus::Cancelled) {
            return;
        }

        if ($received >= $ordered) {
            $this->forceFill([
                'status' => PurchaseOrderStatus::Received,
                'received_at' => $this->received_at ?? now(),
            ])->save();

            return;
        }

        if ($received > 0) {
            $this->forceFill([
                'status' => PurchaseOrderStatus::Partial,
                'received_at' => null,
            ])->save();
        }
    }

    public function canReceive(): bool
    {
        return in_array($this->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial], true);
    }
}
