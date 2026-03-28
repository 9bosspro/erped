<?php

declare(strict_types=1);

/**
 * Crypto Configuration — ค่ากำหนดสำหรับ Crypto Helpers และ Services ทั้งหมด
 *
 * ครอบคลุม:
 *  - Hash Salt keys           (HashHelper, HmacService)
 *  - Password hashing options (PasswordHasher)
 *  - JWT settings             (JwtHelper, TokenBlacklistService)
 *  - RSA key paths            (RsaHelpers)
 *  - Hybrid/Encrypter secret  (HybridEncryptionService)
 */

return [

    // ─── Hash Salt ───────────────────────────────────────────────────
    // ใช้โดย HashHelper สำหรับ double-salt hashing
    'hash_salt' => [
        'key1' => env('CRYPTO_HASH_SALT_KEY1', ''),
        'key2' => env('CRYPTO_HASH_SALT_KEY2', ''),
    ],

    // ─── Password Hashing ────────────────────────────────────────────
    // ใช้โดย PasswordHasher
    'password' => [
        // Argon2id parameters
        'argon_memory'  => (int) env('CRYPTO_ARGON_MEMORY', 65536),    // KiB (64 MiB)
        'argon_time'    => (int) env('CRYPTO_ARGON_TIME', 4),          // iterations
        'argon_threads' => (int) env('CRYPTO_ARGON_THREADS', 1),       // parallelism

        // Bcrypt fallback
        'bcrypt_cost'   => (int) env('CRYPTO_BCRYPT_COST', 12),        // cost factor

        // Pepper — server-side secret (ไม่เก็บใน DB)
        'pepper'        => env('CRYPTO_PASSWORD_PEPPER', ''),

        // Password length policy
        'min_length'    => (int) env('CRYPTO_PASSWORD_MIN_LENGTH', 8),
        'max_length'    => (int) env('CRYPTO_PASSWORD_MAX_LENGTH', 128), // Argon2id limit

        // Complexity policy — ตั้ง false เพื่อผ่อนปรน (เช่น ระบบ internal)
        'require_uppercase' => (bool) env('CRYPTO_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => (bool) env('CRYPTO_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_digit'     => (bool) env('CRYPTO_PASSWORD_REQUIRE_DIGIT',     true),
        'require_special'   => (bool) env('CRYPTO_PASSWORD_REQUIRE_SPECIAL',   true),
    ],

    // ─── JWT ─────────────────────────────────────────────────────────
    // ใช้โดย JwtHelper และ TokenBlacklistService
    'jwt' => [
        'algorithm'            => env('CRYPTO_JWT_ALGORITHM', 'RS256'),
        'issuer'               => env('CRYPTO_JWT_ISSUER'),            // default: app.url
        'audience'             => env('CRYPTO_JWT_AUDIENCE'),          // default: app.url
        'access_ttl'           => (int) env('CRYPTO_JWT_ACCESS_TTL', 3600),     // วินาที (1 ชม.)
        'refresh_ttl'          => (int) env('CRYPTO_JWT_REFRESH_TTL', 2592000), // วินาที (30 วัน)
        'secret'               => env('CRYPTO_JWT_SECRET'),            // สำหรับ HMAC algorithms
        'blacklist_connection' => env('CRYPTO_JWT_BLACKLIST_CONN', 'default'),  // Redis connection
    ],

    // ─── RSA ─────────────────────────────────────────────────────────
    // ใช้โดย RsaHelpers
    'rsa' => [
        'private_key' => env('CRYPTO_RSA_PRIVATE_KEY'),                // PEM string หรือ path
        'public_key'  => env('CRYPTO_RSA_PUBLIC_KEY'),                 // PEM string หรือ path
        'passphrase'  => env('CRYPTO_RSA_PASSPHRASE', ''),
    ],

    // ─── Hybrid Encryption Service ───────────────────────────────────
    // ใช้โดย HybridEncryptionService (encrypts/decrypts methods)
    'encrypter_secret' => env('CRYPTO_ENCRYPTER_SECRET', ''),

];
