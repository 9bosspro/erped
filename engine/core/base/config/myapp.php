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
        /*
        | bypass_ips: รายการ IP/CIDR ที่ข้าม rate limit ทุก limiter
        | ใช้สำหรับ: monitoring (Pingdom, UptimeRobot), internal services,
        |          health-check probes, office IP สำหรับ admin
        |
        | ⚠️  IP ที่ใส่นี้จะข้าม rate limit "ทุก" limiter — รวมถึง login/oauth
        |     ห้ามใส่ public IP ของ residential ISP โดยตรง (เสี่ยง shared IP)
        | รับทั้ง single IP (1.2.3.4) และ CIDR range (10.0.0.0/8)
        |
        | Override ผ่าน env: RATE_LIMIT_BYPASS_IPS=1.2.3.4,10.0.0.0/8
        */
        'bypass_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('RATE_LIMIT_BYPASS_IPS', '')),
        ))),

        // ── Standard ──────────────────────────────────────────────────────────
        'api' => ['user' => 120, 'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'web' => ['user' => 120, 'guest' => 30,  'window' => 'minute', 'decay' => 1],
        'uploads' => ['user' => 100, 'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'resource' => ['read' => 60,  'write' => 20,  'window' => 'minute', 'decay' => 1],
        // ── Auth & Identity ────────────────────────────────────────────────────
        'oauth' => ['ip' => 20,   'client' => 10,  'window' => 'minute', 'decay' => 1],
        // login — per-identifier ใช้ multi-tier progressive backoff
        // attacker ผ่าน tier แรกได้ก็ยังเจอ tier ที่ window ยาวกว่าบล็อก (เช่น 50/วัน)
        'login' => [
            'ip' => 20,
            'fingerprint' => 10,
            'window' => 'minute',
            'decay' => 1,
            // ใส่ list ตามลำดับจาก window สั้น → ยาว (Laravel จะ apply ทั้งหมดเป็น array of Limit)
            'identifier_tiers' => [
                ['limit' => 5,  'window' => 'minute', 'decay' => 1],   // burst    — 5/นาที
                ['limit' => 20, 'window' => 'hour',   'decay' => 1],   // brute    — 20/ชั่วโมง
                ['limit' => 50, 'window' => 'day',    'decay' => 1],   // distrib  — 50/วัน
            ],
        ],
        'register' => ['ip' => 5,    'window' => 'hour',   'decay' => 1],
        'otp' => ['limit' => 5, 'window' => 'minute', 'decay' => 15],  // 5 ครั้ง / 15 นาที
        'password_reset' => ['ip' => 5,   'email' => 3,    'window' => 'hour',   'decay' => 1],
        'sensitive' => ['limit' => 5, 'window' => 'hour',   'decay' => 1],
        // ── Cross-Host & Service ───────────────────────────────────────────────
        'service' => ['limit' => 1200, 'burst' => 60, 'window' => 'minute', 'decay' => 1],
        'webhook' => ['ip' => 60,   'origin' => 120, 'window' => 'minute', 'decay' => 1],
        'public' => ['key' => 60,  'ip' => 10,      'window' => 'minute', 'decay' => 1],
    ],
];
