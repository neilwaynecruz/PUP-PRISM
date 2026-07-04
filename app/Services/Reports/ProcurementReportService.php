<?php

namespace App\Services\Reports;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ProcurementReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $status = $request->string('status')->trim()->toString();
        $dateFrom = $request->string('date_from')->trim()->toString();
        $dateTo = $request->string('date_to')->trim()->toString();

        $query = PurchaseOrder::query()
            ->with(['supplier:id,name', 'lines.product:id,sku,name'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($dateFrom !== '', fn ($query) => $query->whereDate('created_at', '>=', $dateFrom))
            ->when($dateTo !== '', fn ($query) => $query->whereDate('created_at', '<=', $dateTo))
            ->orderByDesc('created_at');

        $rows = (function () use ($query) {
            foreach ($query->lazy(100) as $purchaseOrder) {
                $lines = $purchaseOrder->lines;

                if ($lines->isEmpty()) {
                    yield $this->mapRow($purchaseOrder, null);

                    continue;
                }

                foreach ($lines as $line) {
                    yield $this->mapRow($purchaseOrder, $line);
                }
            }
        })();

        return new TableReport(
            title: 'Procurement Report',
            filenameBase: 'procurement-report',
            filters: $this->normalizeFilters([
                'Status' => $status !== '' ? $status : 'All',
                'Date from' => $dateFrom !== '' ? $dateFrom : null,
                'Date to' => $dateTo !== '' ? $dateTo : null,
            ]),
            columns: [
                'reference_no' => 'PO reference',
                'status' => 'Status',
                'supplier' => 'Supplier',
                'created_at' => 'Created at',
                'received_at' => 'Received at',
                'product_sku' => 'Product SKU',
                'product_name' => 'Product name',
                'qty_ordered' => 'Qty ordered',
                'qty_received' => 'Qty received',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }

    /**
     * @return array<string, scalar|null>
     */
    private function mapRow(PurchaseOrder $purchaseOrder, ?object $line): array
    {
        return [
            'reference_no' => $purchaseOrder->reference_no ?? (string) $purchaseOrder->id,
            'status' => $purchaseOrder->status instanceof PurchaseOrderStatus
                ? $purchaseOrder->status->value
                : (string) $purchaseOrder->status,
            'supplier' => $purchaseOrder->supplier?->name ?? '',
            'created_at' => optional($purchaseOrder->created_at)?->format('Y-m-d H:i:s T') ?? '',
            'received_at' => $purchaseOrder->received_at !== null
                ? CarbonImmutable::parse((string) $purchaseOrder->received_at)->format('Y-m-d H:i:s T')
                : '',
            'product_sku' => $line?->product?->sku ?? '',
            'product_name' => $line?->product?->name ?? '',
            'qty_ordered' => $line?->qty_ordered ?? '',
            'qty_received' => $line?->qty_received ?? '',
        ];
    }
}
