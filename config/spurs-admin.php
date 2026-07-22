<?php

// Accounts' overrides for the @spurs-cloud/admin-sdk. The package ships sane
// defaults; here we declare which admin-held keys overlay Laravel config so the
// admin control plane is the source of truth for them.
return [

    'url' => env('SPURS_ADMIN_URL', 'http://127.0.0.1:3300'),
    'app' => env('SPURS_ADMIN_APP', 'accounts'),
    'secret' => env('SPURS_ADMIN_SECRET'),
    'cache_ttl' => (int) env('SPURS_ADMIN_CACHE_TTL', 300),
    'fallback' => (bool) env('SPURS_ADMIN_FALLBACK', true),

    // Admin key => Laravel config path. When admin returns the key, it wins.
    'map' => [
        'SPURS_GEOIP_URL' => 'spurs.geoip_url',
    ],

];
