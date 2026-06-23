<?php

namespace App\Models;

use App\Enums\ProductType;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'sku',
    'name',
    'category_id',
    'origin_id',
    'supplier_id',
    'type',
    'reorder_threshold',
    'lead_time_days',
    'unit_price',
    'is_active',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'reorder_threshold' => 'integer',
            'lead_time_days' => 'integer',
            'unit_price' => 'float',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Origin, $this>
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasOne<ProductStock, $this>
     */
    public function stock(): HasOne
    {
        return $this->hasOne(ProductStock::class);
    }

    /**
     * @return HasOne<ForecastProfile, $this>
     */
    public function forecastProfile(): HasOne
    {
        return $this->hasOne(ForecastProfile::class);
    }

    /**
     * @return HasMany<StockLot, $this>
     */
    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    /**
     * @return HasMany<ForecastSnapshot, $this>
     */
    public function forecastSnapshots(): HasMany
    {
        return $this->hasMany(ForecastSnapshot::class);
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function purchaseOrderLines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
