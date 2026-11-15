<?php

namespace App\Services\Reports;

use App\Services\Reports\Support\TableReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Symfony\Component\HttpFoundation\Response;

class PdfTableExporter
{
    public function filename(TableReport $report): string
    {
        return sprintf(
            '%s-%s.pdf',
            $report->filenameBase,
            $report->generatedAt->format('Y-m-d-His'),
        );
    }

    public function download(TableReport $report): Response
    {
        return $this->buildPdf($report, enforceRowLimit: true)
            ->download($this->filename($report));
    }

    public function render(TableReport $report, bool $enforceRowLimit = true): string
    {
        return $this->buildPdf($report, enforceRowLimit: $enforceRowLimit)->output();
    }

    private function buildPdf(TableReport $report, bool $enforceRowLimit): DomPdfInstance
    {
        return Pdf::loadView('reports.table', [
            'title' => $report->title,
            'generatedAt' => $report->generatedAt,
            'generatedBy' => $report->generatedBy,
            'filters' => $report->filters,
            'columns' => $report->columns,
            'rows' => $report->rowsForPdf(enforceLimit: $enforceRowLimit),
        ])->setPaper('a4', $report->landscape ? 'landscape' : 'portrait');
    }
}
