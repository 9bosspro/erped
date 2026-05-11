<?php

declare(strict_types=1);

/**
 * การกำหนดค่า Slave Client
 *
 * ควบคุมพฤติกรรมของ EvoEngine Slave Client
 * ตั้งค่าผ่าน environment variable ใน .env
 */
return [

    /*
    |--------------------------------------------------------------------------
    | สถานะการติดตั้ง
    |--------------------------------------------------------------------------
    |
    | บ่งบอกว่า application ถูก install เรียบร้อยแล้วหรือยัง
    | ใช้สำหรับ guard middleware ที่ต้องการให้ install ก่อนใช้งาน
    |
    | .env: APP_INSTALLED=true
    |
    */
    'installed' => (bool) env('APP_INSTALLED', false),
    //
    'client_id' => (string) env('CLIENT_ID', ''),
    'client_secret' => (string) env('CLIENT_SECRET', ''),
    'master_url' => (string) env('MASTER_URL', ''),
    'callblack_url' => (string) env('CALLBLACK_URL', ''),
    'default_scope' => (string) env('DEFAULT_SCOPE', ''),
    //
    'signature_seed' => (string) env('SIGNATURE_SEED', ''),
    'box_seed' => (string) env('BOX_SEED', ''),
    'exchange_seed' => (string) env('EXCHANGE_SEED', ''),
    'jwt_seed' => (string) env('JWT_SEED', ''),
    //
    'public_signature' => (string) env('SIG_PUBLIC', ''),
    'public_exchange' => (string) env('EXCHANGE_PUBLIC', ''),
    'public_box' => (string) env('BOX_PUBLIC', ''),
    'public_jwt' => (string) env('JWT_PUBLIC', ''),

    //
    'trusted_proxies' => (string) env('TRUSTED_PROXIES', '172.17.0.0/24'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configurations
    |--------------------------------------------------------------------------
    */
    'timeout' => (int) env('MASTER_CLIENT_TIMEOUT', 15),
    'retry_times' => (int) env('MASTER_CLIENT_RETRY_TIMES', 2),
    'retry_delay' => (int) env('MASTER_CLIENT_RETRY_DELAY', 100),

    /**
     * ช่องทางเริ่มต้นในการเก็บรักษา Token 
     * รองรับ: null (default cache), 'redis', 'file', 'session'
     */
    'token_store' => env('MASTER_TOKEN_STORE', null),
];
