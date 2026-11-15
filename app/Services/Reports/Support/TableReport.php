<?php

namespace App\Services\Reports\Support;

use App\Exceptions\PdfRowLimitExceededException;
use Carbon\CarbonImmutable;

final readonly class TableReport
{
    /**
     * @param  array<string, string>  $filters
     * @param  array<string, string>  $columns
     * @param  iterable<int, array<string, scalar|null>>  $rows
     */
    public function __construct(
        public string $title,
        public string $filenameBase,
        public array $filters,
        public array $columns,
        public iterable $rows,
        public string $generatedBy,
        public CarbonImmutable $generatedAt,
        public bool $landscape = true,
    ) {}

    /**
     * @return array<int, array<string, scalar|null>>
     */
    public function rowsForPdf(?int $maxRows = null, bool $enforceLimit = true): array
    {
        $limit = $enforceLimit
            ? max(1, $maxRows ?? (int) config('reports.max_pdf_rows', 1500))
            : null;

        if (is_array($this->rows)) {
            if ($limit !== null && count($this->rows) > $limit) {
                throw new PdfRowLimitExceededException($this->title, $limit);
            }

            return array_values($this->rows);
        }

        $collected = [];
        $count = 0;

        foreach ($this->rows as $row) {
            $count++;

            if ($limit !== null && $count > $limit) {
                throw new PdfRowLimitExceededException($this->title, $limit);
            }

            $collected[] = $row;
        }

        return $collected;
    }
}
