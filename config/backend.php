<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Backend API Configuration
    |--------------------------------------------------------------------------
    |
    | การตั้งค่าสำหรับเชื่อมต่อกับ Backend API (pppportal)
    | ที่อยู่คนละเครื่อง/คนละ network
    |
    */

    // URL ของ Backend API (pppportal)
    'api_url' => env('BACKEND_API_URL', 'http://localhost:8880'),

    // Timeout สำหรับ HTTP request (วินาที)
    'timeout' => (int) env('BACKEND_TIMEOUT', 30),

    // จำนวนครั้งที่จะ retry เมื่อเกิด server error (5xx)
    'retry_times' => (int) env('BACKEND_RETRY_TIMES', 2),

    // ระยะเวลารอระหว่าง retry (milliseconds)
    'retry_delay' => (int) env('BACKEND_RETRY_DELAY', 500),

    // Threshold สำหรับ slow query logging (milliseconds)
    'slow_query_threshold_ms' => (int) env('SLOW_QUERY_THRESHOLD_MS', 100),

    /*
    |--------------------------------------------------------------------------
    | Proxy Allowed Endpoints
    |--------------------------------------------------------------------------
    |
    | รายการ endpoint prefix ที่อนุญาตให้ BFF proxy ส่งต่อไปยัง backend
    | ระบุเป็น prefix: 'users' จะอนุญาต users, users/123, users/list
    | endpoint ที่ไม่อยู่ในรายการจะถูกปฏิเสธด้วย 403
    |
    */
    'proxy_allowed_endpoints' => [
        'users',
        'profile',
        'settings',
        'dashboard',
    ],
];
