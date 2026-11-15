<?php

namespace App\Services\Optimization;

use Closure;
use Illuminate\Support\Facades\Cache;

class InventoryOptimizationCache
{
    private const VERSION_KEY = 'inventory_optimization_version';

    public function isEnabled(): bool
    {
        return (bool) config('optimization.cache.enabled', true);
    }

    public function ttl(): int
    {
        return max(1, (int) config('optimization.cache.ttl', 300));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberAbc(string $asOfDate, int $lookbackDays, Closure $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $version = $this->version();

        return Cache::remember(
            "opt:abc:{$asOfDate}:{$lookbackDays}:{$version}",
            $this->ttl(),
            $callback,
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function rememberProductOptimization(
        int $productId,
        string $asOfDate,
        float $serviceLevelZ,
        float $orderingCost,
        float $holdingCostRate,
        Closure $callback,
    ): mixed {
        if (! $this->isEnabled()) {
            return $callback();
        }

        $version = $this->version();
        $paramsHash = hash('xxh128', "{$asOfDate}|{$serviceLevelZ}|{$orderingCost}|{$holdingCostRate}|{$version}");

        return Cache::remember(
            "opt:product:{$productId}:{$paramsHash}",
            $this->ttl(),
            $callback,
        );
    }

    public function invalidate(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::put(self::VERSION_KEY, 1, now()->addYear());
        }

        Cache::increment(self::VERSION_KEY);
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
