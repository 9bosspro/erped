<?php

return [

    'force_ssl' => env('FORCE_HTTPS', false),

    'cache' => [
        'user_ttl' => env('CACHE_USER_TTL', 300), // 5 minutes
    ],
];
