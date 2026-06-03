<?php

namespace App\Services\Reports\Support;

use Carbon\CarbonImmutable;
use Traversable;

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
    public function rowsForPdf(): array
    {
        if (is_array($this->rows)) {
            return $this->rows;
        }

        if ($this->rows instanceof Traversable) {
            return iterator_to_array($this->rows, false);
        }

        return [...$this->rows];
    }
}
