<?php

namespace App\Models;

use Database\Factories\ForecastSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'forecast_date',
    'forecast_method',
    'current_on_hand_qty',
    'reorder_point_qty',
    'predicted_daily_consumption',
    'predicted_days_until_stockout',
    'predicted_stockout_date',
    'recommended_reorder_qty',
    'confidence_score',
    'raw_data',
    'generated_at',
])]
class ForecastSnapshot extends Model
{
    /** @use HasFactory<ForecastSnapshotFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'predicted_stockout_date' => 'date',
            'current_on_hand_qty' => 'integer',
            'reorder_point_qty' => 'integer',
            'predicted_daily_consumption' => 'float',
            'predicted_days_until_stockout' => 'integer',
            'recommended_reorder_qty' => 'integer',
            'confidence_score' => 'float',
            'raw_data' => 'array',
            'generated_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
