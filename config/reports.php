<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Rows for Synchronous PDF Export
    |--------------------------------------------------------------------------
    |
    | DomPDF materializes all rows and HTML DOM nodes in PHP memory during
    | synchronous HTTP exports. Requests whose row count exceeds this cap are
    | offloaded to a queued background job and delivered via notification.
    |
    */

    'max_pdf_rows' => (int) env('REPORT_MAX_PDF_ROWS', 1500),

    /*
    |--------------------------------------------------------------------------
    | Queued PDF Export Storage & Link Lifetime
    |--------------------------------------------------------------------------
    */

    'queued_pdf_disk' => env('REPORT_QUEUED_PDF_DISK', 'local'),

    'queued_pdf_ttl_hours' => (int) env('REPORT_QUEUED_PDF_TTL_HOURS', 24),

];
