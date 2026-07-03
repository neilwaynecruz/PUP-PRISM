<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dashboard Stats Cache
    |--------------------------------------------------------------------------
    |
    | Caches aggregate dashboard statistics to reduce database load. User-specific
    | notifications in shared Inertia props are not cached here.
    |
    */

    'cache' => [
        'enabled' => env('DASHBOARD_CACHE_ENABLED', true),
        'ttl' => (int) env('DASHBOARD_CACHE_TTL', 90),
    ],

];
