<?php

// use Illuminate\Support\Str;
return [
    'ssosv' => [
        'name' => env('SSOSV_NAME', 'ssosv'),
        'namedescipt' => env('SSOSV_NAME_DESCIPT', 'ssosv'),
        'endpoint' => env('SSOSV_ENDPOINT', ''),
        'clientid' => env('SSOSV_CLIENT_ID', ''),
        'clientsecret' => env('SSOSV_CLIENT_SECRET', ''),
        'redirecturl' => env('SSOSV_REDIRECT_URL', ''),
        'scope' => env('SSOSV_SCOPE', ''),
        'authorize' => env('SSOSV_AUTHORIZE', ''),
        'token' => env('SSOSV_TOKEN', ''),
        'userinfo' => env('SSOSV_USERINFO', ''),
    ],
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => 'http://example.com/callback-url',
    ],
    'twitter' => [
        'client_id' => env('TWITTER_KEY'),
        'client_secret' => env('TWITTER_SECRET'),
        'redirect' => env('TWITTER_REDIRECT_URI'),
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENTID', ''),
        'client_secret' => env('GOOGLE_CLIENTSECRET', ''),
        'redirect' => env('GOOGLE_REDIRECTURL', ''),
        'scopes' => env('GOOGLE_SCOPES', 'email profile'),
    ],
    'facebook' => [
        'client_id' => env('TWITTER_KEY', '111'),
        'client_secret' => env('TWITTER_SECRET', '111'),
        'redirect' => env('TWITTER_REDIRECT_URI', '111'),
        // 'SCOPES' =>    env('GOOGLE_SCOPES', 'email profile'),
    ],
    'line' => [
        'client_id' => env('TWITTER_KEY', '111'),
        'client_secret' => env('TWITTER_SECRET', '111'),
        'redirect' => env('TWITTER_REDIRECT_URI', '111'),
        // 'SCOPES' =>    env('GOOGLE_SCOPES', 'email profile'),
    ],
];
