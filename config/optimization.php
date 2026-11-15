<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Inventory Optimization Cache
    |--------------------------------------------------------------------------
    |
    | Caches ABC classifications, EOQ metrics, and safety-stock calculations.
    | Versioned keys are invalidated automatically via model observers.
    |
    */

    'cache' => [
        'enabled' => env('OPTIMIZATION_CACHE_ENABLED', true),
        'ttl' => (int) env('OPTIMIZATION_CACHE_TTL', 300),
    ],

];
