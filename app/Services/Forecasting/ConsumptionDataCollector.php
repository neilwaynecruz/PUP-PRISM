<?php

namespace App\Services\Forecasting;

use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;

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
        $timezone = (string) config('app.timezone', 'UTC');
        $asOfLocal = ($asOf ?? CarbonImmutable::now($timezone))->timezone($timezone)->endOfDay();
        $lookbackDays = max(1, $lookbackDays);
        $requestedStart = $asOfLocal->subDays($lookbackDays - 1)->startOfDay();

        $earliestMovementRaw = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('performed_at', '<=', $asOfLocal->toDateTimeString())
            ->min('performed_at');

        $earliestMovementAt = $earliestMovementRaw !== null
            ? CarbonImmutable::parse((string) $earliestMovementRaw, $timezone)->timezone($timezone)
            : null;

        $productCreatedAt = $product->created_at !== null
            ? CarbonImmutable::parse((string) $product->created_at, $timezone)->timezone($timezone)
            : null;

        $observationCandidates = array_values(array_filter([
            $earliestMovementAt,
            $productCreatedAt,
        ]));

        if ($observationCandidates === []) {
            return [];
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
            return [];
        }

        $dailyTotals = [];
        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [
                $windowStart->toDateTimeString(),
                $asOfLocal->toDateTimeString(),
            ])
            ->toBase()
            ->get(['performed_at', 'qty_delta']);

        foreach ($movements as $movement) {
            $dateKey = CarbonImmutable::parse((string) $movement->performed_at, $timezone)
                ->timezone($timezone)
                ->toDateString();

            $dailyTotals[$dateKey] = ($dailyTotals[$dateKey] ?? 0) + abs((int) $movement->qty_delta);
        }

        $dailyConsumption = [];
        $cursor = $windowStart;
        $endDay = $asOfLocal->startOfDay();

        while ($cursor <= $endDay) {
            $dateKey = $cursor->toDateString();

            $dailyConsumption[] = [
                'date' => $dateKey,
                'qty' => (int) ($dailyTotals[$dateKey] ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $dailyConsumption;
    }
}
