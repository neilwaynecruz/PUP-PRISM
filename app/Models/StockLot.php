<?php

namespace App\Models;

use Database\Factories\StockLotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'reference_no',
    'received_at',
    'expires_at',
    'qty_received',
    'qty_remaining',
])]
class StockLot extends Model
{
    /** @use HasFactory<StockLotFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }
}
