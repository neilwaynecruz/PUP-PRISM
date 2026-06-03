<?php

namespace App\Services\Reports;

use App\Models\Requisition;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class RequisitionHistoryReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $query = Requisition::query()
            ->with([
                'requester:id,name,email',
                'requesterPosition:id,department_id,title',
                'requesterPosition.department:id,name',
                'approver:id,name,email',
                'approverPosition:id,department_id,title',
                'approverPosition.department:id,name',
                'issuer:id,name,email',
                'issuedPosition:id,department_id,title',
                'issuedPosition.department:id,name',
                'lines.product:id,sku,name',
            ])
            ->orderByDesc('created_at');

        $rows = (function () use ($query) {
            foreach ($query->lazy(100) as $requisition) {
                $lines = $requisition->lines;

                if ($lines->isEmpty()) {
                    yield $this->mapRow($requisition, null);

                    continue;
                }

                foreach ($lines as $line) {
                    yield $this->mapRow($requisition, $line);
                }
            }
        })();

        return new TableReport(
            title: 'Requisition History Report',
            filenameBase: 'requisition-history-report',
            filters: $this->normalizeFilters([
                'Status' => 'All',
            ]),
            columns: [
                'requisition_id' => 'Requisition ID',
                'status' => 'Status',
                'requested_at' => 'Requested at',
                'requester' => 'Requester',
                'requester_position' => 'Requester position',
                'approver' => 'Approver',
                'issuer' => 'Issued by',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product name',
                'qty_requested' => 'Qty requested',
                'qty_issued' => 'Qty issued',
                'notes' => 'Notes',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function mapRow(Requisition $requisition, ?object $line): array
    {
        return [
            'requisition_id' => $requisition->id,
            'status' => $requisition->status->value,
            'requested_at' => optional($requisition->created_at)?->format('Y-m-d H:i:s T') ?? '',
            'requester' => $requisition->requester?->name ?? $requisition->requester?->email ?? '',
            'requester_position' => $this->formatPosition(
                $requisition->requesterPosition?->title,
                $requisition->requesterPosition?->department?->name,
            ),
            'approver' => $requisition->approver?->name ?? $requisition->approver?->email ?? '',
            'issuer' => $requisition->issuer?->name ?? $requisition->issuer?->email ?? '',
            'product_sku' => $line?->product?->sku ?? '',
            'product_name' => $line?->product?->name ?? '',
            'qty_requested' => $line?->qty_requested ?? '',
            'qty_issued' => $line?->qty_issued ?? '',
            'notes' => $requisition->notes ?? '',
        ];
    }
}
