<?php

namespace App\Services\Reports;

use App\Models\ForecastSnapshot;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ForecastSummaryReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $snapshots = ForecastSnapshot::query()
            ->with('product:id,sku,name')
            ->orderByDesc('forecast_date')
            ->orderByDesc('generated_at')
            ->get();

        $latestForecastDate = $snapshots->first()?->forecast_date;

        $latestSnapshots = $latestForecastDate === null
            ? collect()
            : $snapshots->filter(
                fn (ForecastSnapshot $snapshot) => (
                    CarbonImmutable::parse((string) $snapshot->forecast_date)->toDateString()
                ) === CarbonImmutable::parse((string) $latestForecastDate)->toDateString(),
            );

        $rows = $latestSnapshots
            ->sortBy(fn (ForecastSnapshot $snapshot) => $snapshot->predicted_days_until_stockout ?? PHP_INT_MAX)
            ->map(fn (ForecastSnapshot $snapshot) => [
                'forecast_date' => CarbonImmutable::parse((string) $snapshot->forecast_date)->toDateString(),
                'sku' => $snapshot->product?->sku ?? '',
                'product_name' => $snapshot->product?->name ?? '',
                'current_on_hand_qty' => $snapshot->current_on_hand_qty,
                'reorder_point_qty' => $snapshot->reorder_point_qty,
                'predicted_daily_consumption' => round($snapshot->predicted_daily_consumption, 2),
                'predicted_days_until_stockout' => $snapshot->predicted_days_until_stockout ?? '',
                'recommended_reorder_qty' => $snapshot->recommended_reorder_qty,
                'confidence_score' => $snapshot->confidence_score !== null
                    ? round($snapshot->confidence_score, 2)
                    : '',
            ])
            ->values()
            ->all();

        return new TableReport(
            title: 'Forecast Summary Report',
            filenameBase: 'forecast-summary-report',
            filters: $this->normalizeFilters([
                'Forecast date' => $latestForecastDate !== null
                    ? CarbonImmutable::parse((string) $latestForecastDate)->toDateString()
                    : 'None',
            ]),
            columns: [
                'forecast_date' => 'Forecast date',
                'sku' => 'SKU',
                'product_name' => 'Product name',
                'current_on_hand_qty' => 'On hand',
                'reorder_point_qty' => 'Reorder point',
                'predicted_daily_consumption' => 'Daily consumption',
                'predicted_days_until_stockout' => 'Days until stockout',
                'recommended_reorder_qty' => 'Recommended reorder qty',
                'confidence_score' => 'Confidence',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
