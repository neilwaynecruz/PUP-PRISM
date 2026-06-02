<?php

namespace App\Models;

use Database\Factories\OriginFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name'])]
class Origin extends Model
{
    /** @use HasFactory<OriginFactory> */
    use HasFactory;

    public const OPTIONS_CACHE_KEY = 'inventory.origins.options';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::OPTIONS_CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::OPTIONS_CACHE_KEY));
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
