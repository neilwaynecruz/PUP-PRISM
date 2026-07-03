<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Operational Data Retention
    |--------------------------------------------------------------------------
    |
    | Controls how long high-growth operational tables are kept before the
    | scheduled `app:prune-operational-data` command removes eligible rows.
    | Set a value to 0 to disable pruning for that dataset.
    |
    */

    'audit_logs_days' => (int) env('RETENTION_AUDIT_LOGS_DAYS', 365),

    'forecast_snapshots_days' => (int) env('RETENTION_FORECAST_SNAPSHOTS_DAYS', 180),

    'notifications_days' => (int) env('RETENTION_NOTIFICATIONS_DAYS', 90),

    'failed_jobs_days' => (int) env('RETENTION_FAILED_JOBS_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Pruning Chunk Size
    |--------------------------------------------------------------------------
    |
    | Large tables are deleted in chunks to avoid long locks and memory spikes.
    |
    */

    'chunk_size' => (int) env('RETENTION_CHUNK_SIZE', 1000),

];
