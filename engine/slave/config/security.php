<?php

declare(strict_types=1);

/**
 * Slave Security Configuration
 *
 * ควบคุม Content Security Policy และ Security Headers
 * แยกออกจาก Middleware เพื่อให้ปรับแต่งได้โดยไม่แก้ code
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy — Script Sources
    |--------------------------------------------------------------------------
    | แหล่งที่อนุญาตให้โหลด JavaScript
    | 'self' และ nonce จะถูกเพิ่มอัตโนมัติ ไม่ต้องระบุที่นี่
    */
    'csp' => [

        'script_src' => array_filter([
            'https://cdn.tailwindcss.com',
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            (string) env('CSP_SCRIPT_SRC_EXTRA', ''),
        ]),

        /*
        |----------------------------------------------------------------------
        | Style Sources
        |----------------------------------------------------------------------
        | 'unsafe-inline' เพิ่มอัตโนมัติสำหรับ Tailwind utility classes
        */
        'style_src' => array_filter([
            'https://fonts.googleapis.com',
            'https://cdnjs.cloudflare.com',
            (string) env('CSP_STYLE_SRC_EXTRA', ''),
        ]),

        /*
        |----------------------------------------------------------------------
        | Font Sources
        |----------------------------------------------------------------------
        */
        'font_src' => array_filter([
            'https://fonts.gstatic.com',
            'https://cdnjs.cloudflare.com',
            (string) env('CSP_FONT_SRC_EXTRA', ''),
        ]),

        /*
        |----------------------------------------------------------------------
        | Image Sources
        |----------------------------------------------------------------------
        | 'self', data:, blob:, minio, imgproxy เพิ่มอัตโนมัติจาก config
        */
        'img_src' => array_filter([
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
            'https://lh3.googleusercontent.com',
            (string) env('CSP_IMG_SRC_EXTRA', ''),
        ]),

        /*
        |----------------------------------------------------------------------
        | Connect Sources (XHR, Fetch, WebSocket)
        |----------------------------------------------------------------------
        | app.url, minio, reverb WebSocket เพิ่มอัตโนมัติจาก config
        */
        'connect_src' => array_filter([
            'https://www.google-analytics.com',
            'https://www.googletagmanager.com',
            (string) env('CSP_CONNECT_SRC_EXTRA', ''),
        ]),

    ],

    /*
    |--------------------------------------------------------------------------
    | Imgproxy Endpoint
    |--------------------------------------------------------------------------
    | URL ของ imgproxy server สำหรับ image transformation
    | ใช้ env IMGPROXY_URL หรือ fallback ไปที่ค่า default
    */
    'imgproxy_url' => (string) env('IMGPROXY_URL', 'https://imgprox.ppp-online.com'),

];
