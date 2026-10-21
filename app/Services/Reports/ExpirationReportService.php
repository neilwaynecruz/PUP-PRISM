<?php

namespace App\Services\Reports;

use App\Models\StockLot;
use App\Services\Reports\Support\TableReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class ExpirationReportService extends AbstractTableReportService
{
    public function build(Request $request): TableReport
    {
        $today = CarbonImmutable::now()->startOfDay();
        $days = $request->filled('days') ? max(1, min(365, $request->integer('days'))) : null;
        $status = $request->string('status')->trim()->toString();

        $query = StockLot::query()
            ->where('qty_remaining', '>', 0)
            ->whereNotNull('expires_at')
            ->with(['product:id,sku,name']);

        if ($days !== null) {
            $cutoff = $today->addDays($days)->endOfDay();
            $query->whereDate('expires_at', '<=', $cutoff);
        }

        $lots = $query->orderBy('expires_at')->orderBy('received_at')->get();

        $rows = $lots
            ->map(function (StockLot $lot) use ($today): array {
                $expiresAt = CarbonImmutable::parse((string) $lot->expires_at)->startOfDay();
                $diffDays = (int) $today->diffInDays($expiresAt, false);

                $statusLabel = match (true) {
                    $diffDays < 0 => 'EXPIRED',
                    $diffDays <= 15 => 'CRITICAL',
                    $diffDays <= 30 => 'WARNING',
                    default => 'HEALTHY',
                };

                return [
                    'reference_no' => $lot->reference_no ?? "LOT-{$lot->id}",
                    'sku' => $lot->product?->sku ?? '',
                    'product_name' => $lot->product?->name ?? '',
                    'qty_remaining' => $lot->qty_remaining,
                    'received_at' => CarbonImmutable::parse((string) $lot->received_at)->toDateString(),
                    'expires_at' => $expiresAt->toDateString(),
                    'days_until_expiry' => $diffDays < 0 ? 'Expired ('.abs($diffDays).'d ago)' : "{$diffDays}d",
                    'raw_days' => $diffDays,
                    'expiry_status' => $statusLabel,
                ];
            })
            ->when($status !== '', function ($collection) use ($status) {
                return $collection->filter(fn (array $item) => strtolower($item['expiry_status']) === strtolower($status));
            })
            ->map(fn (array $item) => [
                'reference_no' => $item['reference_no'],
                'sku' => $item['sku'],
                'product_name' => $item['product_name'],
                'qty_remaining' => $item['qty_remaining'],
                'received_at' => $item['received_at'],
                'expires_at' => $item['expires_at'],
                'days_until_expiry' => $item['days_until_expiry'],
                'expiry_status' => $item['expiry_status'],
            ])
            ->values()
            ->all();

        return new TableReport(
            title: 'Stock Expiration & FEFO Report',
            filenameBase: 'stock-expiration-report',
            filters: $this->normalizeFilters([
                'Days window' => $days !== null ? "Next {$days} days" : 'All expiring lots',
                'Status filter' => $status !== '' ? strtoupper($status) : 'All',
                'Criteria' => 'Active stock lots with remaining quantity and expiration dates',
            ]),
            columns: [
                'reference_no' => 'Lot / Batch Ref',
                'sku' => 'SKU',
                'product_name' => 'Product name',
                'qty_remaining' => 'Remaining qty',
                'received_at' => 'Received date',
                'expires_at' => 'Expiration date',
                'days_until_expiry' => 'Days until expiry',
                'expiry_status' => 'Status',
            ],
            rows: $rows,
            generatedBy: $this->generatedBy($request),
            generatedAt: CarbonImmutable::now(),
        );
    }
}
