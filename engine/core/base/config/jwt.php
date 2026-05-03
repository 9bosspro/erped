<?php

declare(strict_types=1);

/**
 * Crypto Configuration — ค่ากำหนดสำหรับ Crypto Helpers และ Services ทั้งหมด
 *
 * ครอบคลุม:
 *  - Hash Salt keys           (Security\HashHelper, HmacService)
 *  - Password hashing options (PasswordHasher)
 *  - JWT settings             (JwtHelper, TokenBlacklistService)
 *  - RSA key paths            (RsaHelper)
 *  - Hybrid/Encrypter secret  (HybridEncryptionService)
 */
// dd('crypto.php');

return [
    'algorithm' => env('JWT_ALGORITHM', 'EdDSA'),
    'secret' => env('JWT_SECRET'),
    'access_ttl' => (int) env('JWT_ACCESS_TTL', 3600),
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),
    'jwt_seed' => env('JWT_SEED'),
    // Issuer & Audience claims
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
    'audience' => env('JWT_AUDIENCE', env('APP_URL', 'http://localhost')),

    // Redis connection สำหรับ TokenBlacklistService
    'blacklist_connection' => env('JWT_BLACKLIST_CONN', 'default'),
    'publickey' => env('PUBLICKEYJWT'),
    'privatekey' => env('PRIVATEKEYJWT'),
];
