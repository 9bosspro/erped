<?php

// use Illuminate\Support\Str;
return [
    'oauth2' => [
        'ssooauth2' => [
            'url' => 'http://serversso.test',
            'clientid' => env('SSO_SERVER_CLIENTID', ''),
            'clientsecret' => env('SSO_SERVER_CLIENTSECRET', ''),
            'redirecturl' => env('SSO_SERVER_REDIRECTURL', ''),
            'scope' => env('SSO_SERVER_SCOPE', ''),
        ],
        'apiserver' => [
            'url' => 'http://serversso.test',
            'clientid' => env('SSO_SERVER_CLIENTID', ''),
            'clientsecret' => env('SSO_SERVER_CLIENTSECRET', ''),
            'redirecturl' => env('SSO_SERVER_REDIRECTURL', ''),
            'scope' => env('SSO_SERVER_SCOPE', ''),
        ],
    ],
];
