<?php

declare(strict_types=1);

return [
    'key32' => env('KEYSODIAM'),
    'sign_sk' => env('SODIUM_SIGN_SK', ''),  // Base64 Ed25519 secret key (64 bytes)
    'sign_pk' => env('SODIUM_SIGN_PK', ''),  // Base64 Ed25519 public key  (32 bytes)

    // X25519 box key pair (Asymmetric Encryption / ECDH)
    'box_sk' => env('SODIUM_BOX_SK', ''),   // Base64 X25519 secret key   (32 bytes)
    'box_pk' => env('SODIUM_BOX_PK', ''),   // Base64 X25519 public key   (32 bytes)
];
