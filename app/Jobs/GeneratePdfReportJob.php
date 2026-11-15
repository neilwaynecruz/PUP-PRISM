<?php

namespace App\Jobs;

use App\Http\Controllers\Inventory\InventoryReportController;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Services\Reports\PdfTableExporter;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

class GeneratePdfReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param  array<string, mixed>  $queryParams
     */
    public function __construct(
        public readonly string $reportKey,
        public readonly array $queryParams,
        public readonly int $userId,
    ) {
        $this->onQueue('reports');
    }

    public function handle(PdfTableExporter $pdfExporter): void
    {
        $user = User::query()->find($this->userId);

        if (! $user instanceof User || ! $user->is_active) {
            return;
        }

        if (! InventoryReportController::userCanAccessReport($user, $this->reportKey)) {
            return;
        }

        $serviceClass = InventoryReportController::REPORT_SERVICES[$this->reportKey] ?? null;

        if ($serviceClass === null) {
            return;
        }

        $syntheticRequest = Request::create(
            sprintf('/inventory/reports/%s/pdf', $this->reportKey),
            'GET',
            $this->queryParams,
        );
        $syntheticRequest->setUserResolver(static fn (): User => $user);

        $report = app($serviceClass)->build($syntheticRequest);
        $pdfBinary = $pdfExporter->render($report, enforceRowLimit: false);
        $filename = $pdfExporter->filename($report);

        $diskName = (string) config('reports.queued_pdf_disk', 'local');
        $ttlHours = max(1, (int) config('reports.queued_pdf_ttl_hours', 24));
        $directory = "reports/generated/{$user->id}";
        $storedFilename = Str::uuid()->toString().'-'.$filename;
        $storagePath = "{$directory}/{$storedFilename}";

        $disk = Storage::disk($diskName);
        $this->pruneExpiredFiles($disk, $directory, $ttlHours);
        $disk->put($storagePath, $pdfBinary);

        $expiresAt = CarbonImmutable::now()->addHours($ttlHours);
        $downloadUrl = URL::temporarySignedRoute(
            'inventory.reports.generated.download',
            $expiresAt,
            [
                'user' => $user->id,
                'file' => $storedFilename,
            ],
            absolute: false,
        );

        $user->notify(new ReportReadyNotification(
            reportTitle: $report->title,
            filename: $filename,
            downloadUrl: $downloadUrl,
            expiresAt: $expiresAt,
        ));
    }

    private function pruneExpiredFiles(mixed $disk, string $directory, int $ttlHours): void
    {
        $cutoffTimestamp = CarbonImmutable::now()->subHours($ttlHours)->getTimestamp();

        try {
            foreach ($disk->files($directory) as $existingFile) {
                if ($disk->lastModified($existingFile) < $cutoffTimestamp) {
                    $disk->delete($existingFile);
                }
            }
        } catch (Throwable) {
            // Directory may not exist yet or remote driver may not support directory listing.
        }
    }
}
