<?php

namespace App\Services\Reports;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class AssetConditionReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $query = Asset::query()
            ->whereIn('status', [AssetStatus::Unserviceable, AssetStatus::Condemned])
            ->with([
                'product:id,name,sku',
                'position:id,department_id,title,code',
                'position.department:id,name',
            ])
            ->orderBy('status')
            ->orderBy('tag_code');

        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $asset) {
                yield [
                    'tag_code' => $asset->tag_code,
                    'product_sku' => $asset->product?->sku ?? '',
                    'product_name' => $asset->product?->name ?? '',
                    'status' => $asset->status->value,
                    'accountable_position' => $this->formatPosition(
                        $asset->position?->title,
                        $asset->position?->department?->name,
                    ),
                    'updated_at' => optional($asset->updated_at)?->format('Y-m-d H:i:s T') ?? '',
                ];
            }
        })();

        return new TableReport(
            title: 'Unserviceable and Condemned Asset Report',
            filenameBase: 'asset-condition-report',
            filters: $this->normalizeFilters([
                'Statuses' => implode(', ', [
                    AssetStatus::Unserviceable->value,
                    AssetStatus::Condemned->value,
                ]),
            ]),
            columns: [
                'tag_code' => 'Tag code',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product name',
                'status' => 'Status',
                'accountable_position' => 'Accountable position',
                'updated_at' => 'Updated at',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
