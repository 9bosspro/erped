<?php

// use Illuminate\Support\Str;
return [
    'default' => 'ssosv',
    'server' => [
        'ssosv' => [
            'endpoint' => env('SSO_HOST', ''),
            'clientid' => env('SSO_CLIENT_ID', ''),
            'clientsecret' => env('SSO_CLIENT_SECRET', ''),
            'redirecturl' => env('SSO_REDIRECT_URI', ''),
            'scope' => env('SSO_SCOPES', ''),
        ],
        'ssosv2' => [
            'endpoint' => env('SSO_HOST', ''),
            'clientid' => env('SSO_CLIENT_ID', ''),
            'clientsecret' => env('SSO_CLIENT_SECRET', ''),
            'redirecturl' => env('SSO_REDIRECT_URI', ''),
            'scope' => env('SSO_SCOPES', ''),
        ],
        'google' => [
            'endpoint' => env('SSO_HOST', ''),
            'clientid' => env('SSO_CLIENT_ID', ''),
            'clientsecret' => env('SSO_CLIENT_SECRET', ''),
            'redirecturl' => env('SSO_REDIRECT_URI', ''),
            'scope' => env('SSO_SCOPES', ''),
        ],
        'facebook' => [
            'endpoint' => env('SSO_HOST', ''),
            'clientid' => env('SSO_CLIENT_ID', ''),
            'clientsecret' => env('SSO_CLIENT_SECRET', ''),
            'redirecturl' => env('SSO_REDIRECT_URI', ''),
            'scope' => env('SSO_SCOPES', ''),
        ],
        'line' => [
            'endpoint' => env('SSO_HOST', ''),
            'clientid' => env('SSO_CLIENT_ID', ''),
            'clientsecret' => env('SSO_CLIENT_SECRET', ''),
            'redirecturl' => env('SSO_REDIRECT_URI', ''),
            'scope' => env('SSO_SCOPES', ''),
        ],
    ],
];
