<?php

namespace App\Services\Reports;

use App\Models\StockMovement;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class StockMovementAuditReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $validated = $this->validateReportFilters($request, ['date_from', 'date_to', 'performed_by']);

        $type = $request->string('type')->trim()->toString();
        $search = $request->string('search')->trim()->toString();
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;
        $performedBy = $validated['performed_by'] ?? null;

        $query = StockMovement::query()
            ->with([
                'product:id,sku,name',
                'stockLot:id,product_id,reference_no,received_at,expires_at',
                'asset:id,product_id,position_id,tag_code,status',
                'performedBy:id,name,email',
                'accountablePosition:id,department_id,title,code',
                'accountablePosition.department:id,name',
            ])
            ->when($type !== '', fn ($builder) => $builder->where('movement_type', $type))
            ->when($dateFrom !== null, fn ($builder) => $builder->whereDate('performed_at', '>=', $dateFrom))
            ->when($dateTo !== null, fn ($builder) => $builder->whereDate('performed_at', '<=', $dateTo))
            ->when($performedBy !== null, fn ($builder) => $builder->where('performed_by', $performedBy))
            ->when($search !== '', function ($builder) use ($search) {
                $builder->where(function ($builder) use ($search) {
                    $builder->whereHas('product', function ($builder) use ($search) {
                        $builder->whereLike('sku', "%{$search}%")
                            ->orWhereLike('name', "%{$search}%");
                    })
                        ->orWhereHas('asset', function ($builder) use ($search) {
                            $builder->whereLike('tag_code', "%{$search}%");
                        })
                        ->orWhereHas('stockLot', function ($builder) use ($search) {
                            $builder->whereLike('reference_no', "%{$search}%");
                        })
                        ->orWhereHas('performedBy', function ($builder) use ($search) {
                            $builder->whereLike('name', "%{$search}%");
                        })
                        ->orWhereLike('notes', "%{$search}%");
                });
            })
            ->orderByDesc('performed_at')
            ->orderByDesc('id');

        $rows = (function () use ($query) {
            foreach ($query->lazy(200) as $movement) {
                yield [
                    'performed_at' => optional($movement->performed_at)?->format('Y-m-d H:i:s T') ?? '',
                    'movement_type' => $movement->movement_type,
                    'product' => trim(sprintf(
                        '%s %s',
                        $movement->product->sku ?? '',
                        $movement->product?->name ? "({$movement->product->name})" : '',
                    )),
                    'qty_delta' => $movement->qty_delta ?? '',
                    'asset_tag' => $movement->asset->tag_code ?? '',
                    'asset_status' => $movement->asset?->status->value ?? '',
                    'reference_no' => $movement->stockLot->reference_no ?? '',
                    'performed_by' => $movement->performedBy->name ?? $movement->performedBy->email ?? '',
                    'accountable_position' => $this->formatPosition(
                        $movement->accountablePosition?->title,
                        $movement->accountablePosition?->department?->name,
                    ),
                    'ip_address' => $movement->ip_address ?? '',
                    'notes' => $movement->notes ?? '',
                ];
            }
        })();

        return new TableReport(
            title: 'Stock Movement Audit Log',
            filenameBase: 'stock-movement-audit-log',
            filters: $this->normalizeFilters([
                'Movement type' => $type,
                'Search' => $search,
                'Date from' => $dateFrom,
                'Date to' => $dateTo,
                'Performed by' => $performedBy !== null ? (string) $performedBy : null,
            ]),
            columns: [
                'performed_at' => 'Performed at',
                'movement_type' => 'Movement type',
                'product' => 'Product',
                'qty_delta' => 'Qty delta',
                'asset_tag' => 'Asset tag',
                'asset_status' => 'Asset status',
                'reference_no' => 'Reference no.',
                'performed_by' => 'Performed by',
                'accountable_position' => 'Accountable position',
                'ip_address' => 'IP address',
                'notes' => 'Notes',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
