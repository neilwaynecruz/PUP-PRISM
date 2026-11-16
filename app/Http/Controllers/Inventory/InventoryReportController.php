<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\PdfRowLimitExceededException;
use App\Http\Controllers\Controller;
use App\Jobs\GeneratePdfReportJob;
use App\Models\Booking;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\User;
use App\Services\Reports\AssetConditionReportService;
use App\Services\Reports\BookingScheduleReportService;
use App\Services\Reports\CsvTableExporter;
use App\Services\Reports\ExpirationReportService;
use App\Services\Reports\ForecastSummaryReportService;
use App\Services\Reports\InventoryOptimizationReportService;
use App\Services\Reports\PdfTableExporter;
use App\Services\Reports\ProcurementReportService;
use App\Services\Reports\ProductInventoryReportService;
use App\Services\Reports\RequisitionHistoryReportService;
use App\Services\Reports\SlowMovingStockReportService;
use App\Services\Reports\StockMovementAuditReportService;
use App\Services\Reports\Support\TableReport;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class InventoryReportController extends Controller
{
    /**
     * @var array<string, class-string>
     */
    public const REPORT_SERVICES = [
        'products' => ProductInventoryReportService::class,
        'movements' => StockMovementAuditReportService::class,
        'asset-conditions' => AssetConditionReportService::class,
        'bookings' => BookingScheduleReportService::class,
        'requisitions' => RequisitionHistoryReportService::class,
        'procurement' => ProcurementReportService::class,
        'forecasting' => ForecastSummaryReportService::class,
        'slow-moving' => SlowMovingStockReportService::class,
        'expirations' => ExpirationReportService::class,
        'optimization' => InventoryOptimizationReportService::class,
    ];

    public function __construct(
        private readonly CsvTableExporter $csvExporter,
        private readonly PdfTableExporter $pdfExporter,
        private readonly ProductInventoryReportService $productInventoryReports,
        private readonly StockMovementAuditReportService $stockMovementReports,
        private readonly AssetConditionReportService $assetConditionReports,
        private readonly BookingScheduleReportService $bookingScheduleReports,
        private readonly RequisitionHistoryReportService $requisitionHistoryReports,
        private readonly ProcurementReportService $procurementReports,
        private readonly ForecastSummaryReportService $forecastSummaryReports,
        private readonly SlowMovingStockReportService $slowMovingStockReports,
        private readonly ExpirationReportService $expirationReports,
        private readonly InventoryOptimizationReportService $optimizationReports,
    ) {}

    public static function userCanAccessReport(User $user, string $reportKey): bool
    {
        return match ($reportKey) {
            'products' => Gate::forUser($user)->allows('viewAny', Product::class),
            'bookings' => Gate::forUser($user)->allows('viewAny', Booking::class),
            'requisitions' => Gate::forUser($user)->allows('viewAny', Requisition::class),
            'movements', 'asset-conditions' => $user->hasRole('Admin'),
            'procurement', 'forecasting', 'slow-moving', 'expirations', 'optimization' => $user->hasAnyRole(['Admin', 'Supply Head']),
            default => false,
        };
    }

    public function products(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Product::class);

        return $this->download($request, 'products', $format, $this->productInventoryReports->build($request));
    }

    public function stockMovements(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        return $this->download($request, 'movements', $format, $this->stockMovementReports->build($request));
    }

    public function assetConditions(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasRole('Admin'), 403);

        return $this->download($request, 'asset-conditions', $format, $this->assetConditionReports->build($request));
    }

    public function bookings(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Booking::class);

        return $this->download($request, 'bookings', $format, $this->bookingScheduleReports->build($request));
    }

    public function requisitions(Request $request, string $format): Response
    {
        $this->authorize('viewAny', Requisition::class);

        return $this->download($request, 'requisitions', $format, $this->requisitionHistoryReports->build($request));
    }

    public function procurement(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        return $this->download($request, 'procurement', $format, $this->procurementReports->build($request));
    }

    public function forecasting(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        return $this->download($request, 'forecasting', $format, $this->forecastSummaryReports->build($request));
    }

    public function slowMovingStock(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        return $this->download($request, 'slow-moving', $format, $this->slowMovingStockReports->build($request));
    }

    public function expirations(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        return $this->download($request, 'expirations', $format, $this->expirationReports->build($request));
    }

    public function optimization(Request $request, string $format): Response
    {
        abort_unless($request->user()?->hasAnyRole(['Admin', 'Supply Head']), 403);

        return $this->download($request, 'optimization', $format, $this->optimizationReports->build($request));
    }

    public function downloadGenerated(Request $request, int $user, string $file): Response
    {
        $currentUser = $request->user();
        abort_unless($currentUser instanceof User && (int) $currentUser->id === $user, 403);
        abort_unless(preg_match('/^[a-zA-Z0-9._-]+\.pdf$/', $file) === 1, 404);

        $diskName = (string) config('reports.queued_pdf_disk', 'local');
        $path = "reports/generated/{$user}/{$file}";
        $disk = $this->downloadableDisk(Storage::disk($diskName));

        abort_unless($disk->exists($path), 404);

        $downloadName = preg_replace('/^[0-9a-f-]{36}-/i', '', $file) ?? $file;

        return $disk->download($path, $downloadName, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function downloadableDisk(Filesystem $disk): FilesystemAdapter
    {
        if (! $disk instanceof FilesystemAdapter) {
            throw new \LogicException('The generated report disk must support streamed downloads.');
        }

        return $disk;
    }

    private function download(Request $request, string $reportKey, string $format, TableReport $report): Response
    {
        if ($format === 'csv') {
            return $this->csvExporter->download($report);
        }

        if ($format !== 'pdf') {
            abort(404);
        }

        try {
            return $this->pdfExporter->download($report);
        } catch (PdfRowLimitExceededException $exception) {
            $user = $request->user();
            abort_unless($user instanceof User, 403);

            GeneratePdfReportJob::dispatch(
                reportKey: $reportKey,
                queryParams: $request->query(),
                userId: (int) $user->id,
            );

            $message = __(
                'This report exceeds the :limit-row limit for synchronous PDF export and has been queued. You will receive a notification when it is ready to download.',
                ['limit' => number_format($exception->maxRows)],
            );

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'queued' => true,
                    'max_pdf_rows' => $exception->maxRows,
                ], 202);
            }

            Inertia::flash('toast', [
                'type' => 'info',
                'message' => $message,
            ]);

            return redirect()->back(303);
        }
    }
}
