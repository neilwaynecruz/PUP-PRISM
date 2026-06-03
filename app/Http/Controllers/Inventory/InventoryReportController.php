<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Services\Reports\AssetConditionReportService;
use App\Services\Reports\BookingScheduleReportService;
use App\Services\Reports\CsvTableExporter;
use App\Services\Reports\PdfTableExporter;
use App\Services\Reports\ProductInventoryReportService;
use App\Services\Reports\RequisitionHistoryReportService;
use App\Services\Reports\StockMovementAuditReportService;
use App\Services\Reports\Support\TableReport;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InventoryReportController extends Controller
{
    public function __construct(
        private readonly CsvTableExporter $csvExporter,
        private readonly PdfTableExporter $pdfExporter,
        private readonly ProductInventoryReportService $productInventoryReports,
        private readonly StockMovementAuditReportService $stockMovementReports,
        private readonly AssetConditionReportService $assetConditionReports,
        private readonly BookingScheduleReportService $bookingScheduleReports,
        private readonly RequisitionHistoryReportService $requisitionHistoryReports,
    ) {}

    public function products(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Product::class);

        return $this->download($format, $this->productInventoryReports->build($request));
    }

    public function stockMovements(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        return $this->download($format, $this->stockMovementReports->build($request));
    }

    public function assetConditions(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        return $this->download($format, $this->assetConditionReports->build($request));
    }

    public function bookings(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Booking::class);

        return $this->download($format, $this->bookingScheduleReports->build($request));
    }

    public function requisitions(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Requisition::class);

        return $this->download($format, $this->requisitionHistoryReports->build($request));
    }

    private function download(string $format, TableReport $report): Response
    {
        return match ($format) {
            'csv' => $this->csvExporter->download($report),
            'pdf' => $this->pdfExporter->download($report),
            default => abort(404),
        };
    }
}
