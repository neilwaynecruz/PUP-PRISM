<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Cache;

class DashboardStatsCache
{
    private const VERSION_KEY = 'dashboard_stats_version';

    public function isEnabled(): bool
    {
        return (bool) config('dashboard.cache.enabled', true);
    }

    public function ttl(): int
    {
        return max(1, (int) config('dashboard.cache.ttl', 90));
    }

    /**
     * @param  array{from: string|null, to: string|null}  $range
     */
    public function remember(string $role, array $range, Closure $callback): mixed
    {
        if (! $this->isEnabled()) {
            return $callback();
        }

        return Cache::remember(
            $this->key($role, $range),
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

    /**
     * @param  array{from: string|null, to: string|null}  $range
     */
    public function key(string $role, array $range): string
    {
        $from = $range['from'] ?? '';
        $to = $range['to'] ?? '';
        $version = (int) Cache::get(self::VERSION_KEY, 1);
        $rangeHash = hash('xxh128', $from.'|'.$to.'|'.$version);

        return "dashboard:{$role}:{$rangeHash}";
    }
}
