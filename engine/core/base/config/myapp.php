<?php

return [
    'engine' => env('Folder_Engine', 'engine'),
    'core' => env('Folder_Core', 'core'),
    'modules' => env('Folder_Modules', 'modules'),
    'plugins' => env('Folder_Plugins', 'plugins'),
    'themes' => env('Folder_Themes', 'themes'),
    'widgets' => env('Folder_Widgets', 'widgets'),
    'force_ssl' => env('FORCE_SCHEMA', false),
    'temporary_url' => [
        'ttl' => env('TEMP_URL_TTL', 60), // นาที (URL อายุจริง)
        'cache_ttl' => env('TEMP_URL_CACHE_TTL', 55), // cache (ต้องน้อยกว่า)
    ],
    'installed' => env('APP_INSTALLED', false),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    | ใช้โดย Core\Base\Http\RateLimiting\RateLimitConfigurator
    |
    | window : "second" | "minute" | "hour" | "day"
    | decay  : custom window size — decay=15 + window=minute = per 15 นาที
    | burst  : max req/second (เฉพาะ throttle:service)
    */
    'rate_limits' => [
        // ── Standard ──────────────────────────────────────────────────────────
        'api' => ['user' => 120, 'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'web' => ['user' => 120, 'guest' => 30,  'window' => 'minute', 'decay' => 1],
        'uploads' => ['user' => 100, 'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'resource' => ['read' => 60,  'write' => 20,  'window' => 'minute', 'decay' => 1],
        // ── Auth & Identity ────────────────────────────────────────────────────
        'oauth' => ['ip' => 20,   'client' => 10,  'window' => 'minute', 'decay' => 1],
        'login' => ['ip' => 20,   'fingerprint' => 10, 'email' => 5, 'window' => 'minute', 'decay' => 1],
        'register' => ['ip' => 5,    'window' => 'hour',   'decay' => 1],
        'otp' => ['limit' => 5, 'window' => 'minute', 'decay' => 15],  // 5 ครั้ง / 15 นาที
        'password_reset' => ['ip' => 5,   'email' => 3,    'window' => 'hour',   'decay' => 1],
        'sensitive' => ['limit' => 5, 'window' => 'hour',   'decay' => 1],
        // ── Cross-Host & Service ───────────────────────────────────────────────
        'service' => ['limit' => 600, 'burst' => 30, 'window' => 'minute', 'decay' => 1],
        'webhook' => ['ip' => 60,   'origin' => 120, 'window' => 'minute', 'decay' => 1],
        'public' => ['key' => 60,  'ip' => 10,      'window' => 'minute', 'decay' => 1],
    ],
];
