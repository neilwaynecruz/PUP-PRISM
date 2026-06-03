<?php

namespace App\Services\Reports;

use App\Services\Reports\Support\TableReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfTableExporter
{
    public function download(TableReport $report): Response
    {
        $filename = sprintf(
            '%s-%s.pdf',
            $report->filenameBase,
            $report->generatedAt->format('Y-m-d-His'),
        );

        return Pdf::loadView('reports.table', [
            'title' => $report->title,
            'generatedAt' => $report->generatedAt,
            'generatedBy' => $report->generatedBy,
            'filters' => $report->filters,
            'columns' => $report->columns,
            'rows' => $report->rowsForPdf(),
        ])->setPaper('a4', $report->landscape ? 'landscape' : 'portrait')
            ->download($filename);
    }
}
