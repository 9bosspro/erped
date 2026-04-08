<?php

declare(strict_types=1);

return [
    'private_key' => env('RSA_PRIVATE_KEY'),
    'public_key' => env('RSA_PUBLIC_KEY'),
    'passphrase' => env('RSA_PASSPHRASE', ''),
];
