<?php

// echo "dfdf";
// dd('ok');
// dd('ทดสอบเสร็จสิ้น');

return [
    'engine' => env('Folder_Engine', 'engine'),
    'core' => env('Folder_Core', 'core'),
    'modules' => env('Folder_Modules', 'modules'),
    'plugins' => env('Folder_Plugins', 'plugins'),
    'themes' => env('Folder_Themes', 'themes'),
    'widgets' => env('Folder_Widgets', 'widgets'),
    'force_ssl' => env('FORCE_SCHEMA', false),
    // social  ad by  ampol
    /*  'github'   => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => 'http://example.com/callback-url',
    ],
    'twitter'  => [
        'client_id'     => env('TWITTER_KEY'),
        'client_secret' => env('TWITTER_SECRET'),
        'redirect'      => env('TWITTER_REDIRECT_URI'),
    ],
    'google'   => [
        'client_id'     => env('GOOGLE_CLIENTID', ''),
        'client_secret' => env('GOOGLE_CLIENTSECRET', ''),
        'redirect'      => env('GOOGLE_REDIRECTURL', ''),
        // 'SCOPES' =>    env('GOOGLE_SCOPES', 'email profile'),
    ],
    'facebook' => [
        'client_id'     => env('TWITTER_KEY', '111'),
        'client_secret' => env('TWITTER_SECRET', '111'),
        'redirect'      => env('TWITTER_REDIRECT_URI', '111'),
        // 'SCOPES' =>    env('GOOGLE_SCOPES', 'email profile'),
    ],
    'line'     => [
        'client_id'     => env('TWITTER_KEY', '111'),
        'client_secret' => env('TWITTER_SECRET',
            '111'
        ),
        'redirect'      => env('TWITTER_REDIRECT_URI', '111'),
        // 'SCOPES' =>    env('GOOGLE_SCOPES', 'email profile'),
    ],
 */
    'temporary_url' => [
        'ttl' => env('TEMP_URL_TTL', 60), // นาที (URL อายุจริง)
        'cache_ttl' => env('TEMP_URL_CACHE_TTL', 55), // cache (ต้องน้อยกว่า)
    ],
];
