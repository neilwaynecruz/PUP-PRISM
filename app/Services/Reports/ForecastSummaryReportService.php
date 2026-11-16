<?php

namespace App\Services\Reports;

use App\Models\ForecastSnapshot;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ForecastSummaryReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $rankedSnapshots = ForecastSnapshot::query()
            ->select([
                'id',
                'product_id',
                'forecast_date',
                'generated_at',
                'current_on_hand_qty',
                'reorder_point_qty',
                'predicted_daily_consumption',
                'predicted_days_until_stockout',
                'recommended_reorder_qty',
                'confidence_score',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY forecast_date DESC, generated_at DESC, id DESC) as row_num'),
            ]);

        $latestSnapshots = ForecastSnapshot::query()
            ->fromSub($rankedSnapshots, 'forecast_snapshots')
            ->select([
                'id',
                'product_id',
                'forecast_date',
                'generated_at',
                'current_on_hand_qty',
                'reorder_point_qty',
                'predicted_daily_consumption',
                'predicted_days_until_stockout',
                'recommended_reorder_qty',
                'confidence_score',
            ])
            ->with('product:id,sku,name')
            ->where('row_num', 1)
            ->orderByRaw('CASE WHEN predicted_days_until_stockout IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('predicted_days_until_stockout')
            ->orderBy('id')
            ->get();

        $latestForecastDate = $latestSnapshots->max(
            static fn (ForecastSnapshot $snapshot): string => $snapshot->forecast_date->toDateString()
        );

        $rows = $latestSnapshots
            ->map(static fn (ForecastSnapshot $snapshot): array => [
                'forecast_date' => CarbonImmutable::parse((string) $snapshot->forecast_date)->toDateString(),
                'sku' => $snapshot->product->sku ?? '',
                'product_name' => $snapshot->product->name ?? '',
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
                'Coverage' => 'Latest available snapshot for each product',
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
