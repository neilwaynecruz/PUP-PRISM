<?php

namespace App\Exceptions;

use RuntimeException;

class PdfRowLimitExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $reportTitle,
        public readonly int $maxRows,
    ) {
        parent::__construct(sprintf(
            'Report "%s" exceeds the synchronous PDF row limit of %d rows.',
            $reportTitle,
            $maxRows,
        ));
    }
}
