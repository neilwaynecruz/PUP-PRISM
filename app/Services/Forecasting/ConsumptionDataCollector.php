<?php

namespace App\Services\Forecasting;

use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ConsumptionDataCollector
{
    /**
     * Collect daily issue consumption across observed product history within the lookback window.
     *
     * Avoids zero-padding days prior to product creation or earliest stock movement
     * so brand-new products are not misclassified as having full historical depth.
     *
     * @return list<array{date: string, qty: int}>
     */
    public function getDailyConsumption(
        Product $product,
        int $lookbackDays = 90,
        ?CarbonImmutable $asOf = null,
    ): array {
        $batch = $this->getDailyConsumptionBatch([$product], $lookbackDays, $asOf);

        return $batch[$product->id] ?? [];
    }

    /**
     * Collect daily issue consumption for multiple products in two batched queries.
     *
     * @param  iterable<Product>  $products
     * @param  int|array<int, int>  $lookbackDays
     * @return array<int, list<array{date: string, qty: int}>>
     */
    public function getDailyConsumptionBatch(
        iterable $products,
        int|array $lookbackDays = 90,
        ?CarbonImmutable $asOf = null,
    ): array {
        $productList = [];

        foreach ($products as $product) {
            $productList[$product->id] = $product;
        }

        if ($productList === []) {
            return [];
        }

        $timezone = (string) config('app.timezone', 'UTC');
        $asOfLocal = ($asOf ?? CarbonImmutable::now($timezone))->timezone($timezone)->endOfDay();
        $productIds = array_keys($productList);

        $earliestMovements = StockMovement::query()
            ->whereIn('product_id', $productIds)
            ->where('performed_at', '<=', $asOfLocal->toDateTimeString())
            ->select('product_id', DB::raw('MIN(performed_at) as earliest_performed_at'))
            ->groupBy('product_id')
            ->pluck('earliest_performed_at', 'product_id');

        /** @var array<int, CarbonImmutable> $windowStarts */
        $windowStarts = [];
        $globalWindowStart = null;

        foreach ($productList as $productId => $product) {
            $productLookback = max(1, is_array($lookbackDays) ? (int) ($lookbackDays[$productId] ?? 90) : $lookbackDays);
            $requestedStart = $asOfLocal->subDays($productLookback - 1)->startOfDay();

            $earliestRaw = $earliestMovements->get($productId);
            $earliestMovementAt = $earliestRaw !== null
                ? CarbonImmutable::parse((string) $earliestRaw, $timezone)->timezone($timezone)
                : null;

            $productCreatedAt = $product->created_at !== null
                ? CarbonImmutable::parse((string) $product->created_at, $timezone)->timezone($timezone)
                : null;

            $observationCandidates = array_values(array_filter([
                $earliestMovementAt,
                $productCreatedAt,
            ]));

            if ($observationCandidates === []) {
                continue;
            }

            usort(
                $observationCandidates,
                static fn (CarbonImmutable $a, CarbonImmutable $b): int => $a->getTimestamp() <=> $b->getTimestamp(),
            );

            $observationStart = $observationCandidates[0]->startOfDay();
            $windowStart = $requestedStart->greaterThan($observationStart)
                ? $requestedStart
                : $observationStart;

            if ($windowStart->greaterThan($asOfLocal)) {
                continue;
            }

            $windowStarts[$productId] = $windowStart;

            if ($globalWindowStart === null || $windowStart->lessThan($globalWindowStart)) {
                $globalWindowStart = $windowStart;
            }
        }

        /** @var array<int, array<string, int>> $dailyTotalsByProduct */
        $dailyTotalsByProduct = [];

        if ($globalWindowStart !== null) {
            $aggregatedRows = StockMovement::query()
                ->whereIn('product_id', array_keys($windowStarts))
                ->where('movement_type', 'issue')
                ->whereBetween('performed_at', [
                    $globalWindowStart->toDateTimeString(),
                    $asOfLocal->toDateTimeString(),
                ])
                ->select(
                    'product_id',
                    DB::raw('DATE(performed_at) as movement_date'),
                    DB::raw('SUM(ABS(qty_delta)) as total_qty'),
                )
                ->groupBy('product_id', DB::raw('DATE(performed_at)'))
                ->toBase()
                ->get();

            foreach ($aggregatedRows as $row) {
                $pid = (int) $row->product_id;
                $dateKey = (string) $row->movement_date;
                $dailyTotalsByProduct[$pid][$dateKey] = (int) $row->total_qty;
            }
        }

        $results = [];
        $endDay = $asOfLocal->startOfDay();

        foreach ($productList as $productId => $product) {
            if (! isset($windowStarts[$productId])) {
                $results[$productId] = [];

                continue;
            }

            $productDailyTotals = $dailyTotalsByProduct[$productId] ?? [];
            $series = [];
            $cursor = $windowStarts[$productId];

            while ($cursor <= $endDay) {
                $dateKey = $cursor->toDateString();

                $series[] = [
                    'date' => $dateKey,
                    'qty' => (int) ($productDailyTotals[$dateKey] ?? 0),
                ];

                $cursor = $cursor->addDay();
            }

            $results[$productId] = $series;
        }

        return $results;
    }
}
