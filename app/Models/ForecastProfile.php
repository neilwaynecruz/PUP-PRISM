<?php

namespace App\Models;

use Database\Factories\ForecastProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_id',
    'method',
    'lookback_days',
    'forecast_horizon_days',
    'lead_time_days',
    'safety_stock_days',
    'smoothing_factor',
    'trend_factor',
    'is_active',
])]
class ForecastProfile extends Model
{
    /** @use HasFactory<ForecastProfileFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lookback_days' => 'integer',
            'forecast_horizon_days' => 'integer',
            'lead_time_days' => 'integer',
            'safety_stock_days' => 'integer',
            'smoothing_factor' => 'float',
            'trend_factor' => 'float',
            'is_active' => 'boolean',
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
