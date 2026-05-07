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
            'https://fonts.bunny.net',
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
            'https://fonts.bunny.net',
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
            'https://picsum.photos',
            'https://img.youtube.com',
            'https://i.ytimg.com',
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

        /*
        |----------------------------------------------------------------------
        | Frame Sources — iframe embeds
        |----------------------------------------------------------------------
        | ว่างเปล่า = 'none' (บล็อกทุก iframe)
        | เพิ่ม youtube-nocookie.com เพื่อรองรับ YouTube embed
        */
        'frame_src' => array_filter([
            'https://www.youtube-nocookie.com',
            (string) env('CSP_FRAME_SRC_EXTRA', ''),
        ]),

        /*
        |----------------------------------------------------------------------
        | Media Sources — audio / video elements
        |----------------------------------------------------------------------
        | ว่างเปล่า = fallback เป็น https: (อนุญาตทุก HTTPS)
        */
        'media_src' => array_filter([
            (string) env('CSP_MEDIA_SRC_EXTRA', ''),
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
