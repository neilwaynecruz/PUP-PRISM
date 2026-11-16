<?php

namespace App\Models;

use App\Enums\AssetConditionStatus;
use App\Enums\AssetLifecycleStatus;
use App\Enums\AssetStatus;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'product_id',
    'position_id',
    'current_location_id',
    'tag_code',
    'serial_number',
    'status',
    'lifecycle_status',
    'condition_status',
    'acquisition_date',
    'acquisition_cost',
    'warranty_expires_at',
    'installed_at',
    'maintenance_interval_days',
])]
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'lifecycle_status' => AssetLifecycleStatus::class,
            'condition_status' => AssetConditionStatus::class,
            'acquisition_date' => 'date',
            'acquisition_cost' => 'float',
            'warranty_expires_at' => 'date',
            'installed_at' => 'date',
            'maintenance_interval_days' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Position, $this>
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    /** @return HasMany<AssetMaintenance, $this> */
    public function maintenances(): HasMany
    {
        return $this->hasMany(AssetMaintenance::class);
    }

    /** @return HasMany<AssetTransfer, $this> */
    public function transfers(): HasMany
    {
        return $this->hasMany(AssetTransfer::class);
    }

    /** @return HasMany<AssetLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(AssetLifecycleEvent::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
