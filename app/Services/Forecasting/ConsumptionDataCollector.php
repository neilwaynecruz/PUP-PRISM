<?php

namespace App\Services\Forecasting;

use App\Models\Product;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ConsumptionDataCollector
{
    /**
     * @return list<array{date: string, qty: int}>
     */
    public function getDailyConsumption(
        Product $product,
        int $lookbackDays = 90,
        ?CarbonImmutable $asOf = null,
    ): array {
        $asOf ??= CarbonImmutable::now()->endOfDay();
        $lookbackDays = max(1, $lookbackDays);
        $windowStart = $asOf->subDays($lookbackDays - 1)->startOfDay();

        $movements = StockMovement::query()
            ->where('product_id', $product->id)
            ->where('movement_type', 'issue')
            ->whereBetween('performed_at', [$windowStart, $asOf])
            ->select(
                DB::raw('DATE(performed_at) as date'),
                DB::raw('CAST(SUM(ABS(qty_delta)) AS INTEGER) as total_issued'),
            )
            ->groupBy(DB::raw('DATE(performed_at)'))
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $dailyConsumption = [];
        $cursor = $windowStart;

        while ($cursor <= $asOf->startOfDay()) {
            $dateKey = $cursor->toDateString();

            $dailyConsumption[] = [
                'date' => $dateKey,
                'qty' => (int) ($movements[$dateKey]->total_issued ?? 0),
            ];

            $cursor = $cursor->addDay();
        }

        return $dailyConsumption;
    }
}
