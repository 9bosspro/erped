<?php

declare(strict_types=1);

/**
 * Crypto Configuration — ค่ากำหนดสำหรับ Crypto Helpers และ Services ทั้งหมด
 *
 * ═══════════════════════════════════════════════════════════════
 *  ครอบคลุม:
 * ═══════════════════════════════════════════════════════════════
 *  - hash_salt          — HashHelper (double-salt HMAC)
 *  - password           — PasswordHasher (Argon2id policy)
 *  - jwt                — JwtHelper + TokenBlacklistService
 *  - rsa                — RsaHelper (RSA key paths)
 *  - sodium             — SodiumHelper (Ed25519 + X25519 key pairs)
 *  - encrypter_secret   — HybridEncryptionService (AES-256-GCM internal key)
 */
return [

    // ─── Hash Salt ───────────────────────────────────────────────────
    // ใช้โดย HashHelper สำหรับ double-salt hashing (HMAC layered)
    // ⚠️ Production: ต้องกำหนดค่าทั้งสองใน .env หรือ secret manager
    'hash_salt' => [
        'key1' => env('HASH_SALT_KEY1'),
        'key2' => env('HASH_SALT_KEY2'),
    ],

    // ─── Password Hashing ────────────────────────────────────────────
    // ใช้โดย PasswordHasher
    'password' => [
        // Argon2id parameters
        'argon_memory' => (int) env('CRYPTO_ARGON_MEMORY', 65536),   // KiB (64 MiB)
        'argon_time' => (int) env('CRYPTO_ARGON_TIME', 4),         // iterations
        'argon_threads' => (int) env('CRYPTO_ARGON_THREADS', 1),      // parallelism

        // Bcrypt fallback (legacy support)
        'bcrypt_cost' => (int) env('CRYPTO_BCRYPT_COST', 12),

        // Pepper — server-side secret (ไม่เก็บใน DB)
        'pepper' => env('CRYPTO_PASSWORD_PEPPER', ''),

        // Password length policy
        'min_length' => (int) env('CRYPTO_PASSWORD_MIN_LENGTH', 8),
        'max_length' => (int) env('CRYPTO_PASSWORD_MAX_LENGTH', 128),

        // Complexity policy — ตั้ง false เพื่อผ่อนปรน (เช่น ระบบ internal)
        'require_uppercase' => (bool) env('CRYPTO_PASSWORD_REQUIRE_UPPERCASE', true),
        'require_lowercase' => (bool) env('CRYPTO_PASSWORD_REQUIRE_LOWERCASE', true),
        'require_digit' => (bool) env('CRYPTO_PASSWORD_REQUIRE_DIGIT', true),
        'require_special' => (bool) env('CRYPTO_PASSWORD_REQUIRE_SPECIAL', true),
    ],

    // ─── JWT ─────────────────────────────────────────────────────────
    // ใช้โดย JwtHelper และ TokenBlacklistService
    'jwt' => [
        // Algorithm: 'RS256' | 'RS384' | 'RS512' (asymmetric)
        //            'HS256' | 'HS384' | 'HS512' (symmetric)
        //            'EdDSA' (Ed25519)
        'algorithm' => env('JWT_ALGORITHM', 'RS256'),

        // Secret key สำหรับ HMAC algorithms (HS256/HS384/HS512)
        'secret' => env('JWT_SECRET'),

        // Access token lifetime (seconds) — default 1 ชั่วโมง
        'access_ttl' => (int) env('JWT_ACCESS_TTL', 3600),

        // Refresh token lifetime (seconds) — default 30 วัน
        'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 2592000),

        // Issuer & Audience claims
        'issuer' => env('JWT_ISSUER', env('APP_URL', 'http://localhost')),
        'audience' => env('JWT_AUDIENCE', env('APP_URL', 'http://localhost')),

        // Redis connection สำหรับ TokenBlacklistService
        'blacklist_connection' => env('JWT_BLACKLIST_CONN', 'default'),
    ],

    // ─── RSA ─────────────────────────────────────────────────────────
    // ใช้โดย RsaHelper
    'rsa' => [
        'private_key' => env('RSA_PRIVATE_KEY', storage_path('app/keys/passport/oauth-private.key')),
        'public_key' => env('RSA_PUBLIC_KEY', storage_path('app/keys/passport/oauth-public.key')),
        'passphrase' => env('RSA_PASSPHRASE', ''),
    ],

    // ─── Sodium (libsodium) ──────────────────────────────────────────
    // ใช้โดย SodiumHelper
    // Keys ทุกตัวเก็บเป็น Base64 (SODIUM_BASE64_VARIANT_ORIGINAL)
    // สร้างด้วย: SodiumHelper::generateSignatureKeyPair() / generateBoxKeyPair()
    'sodium' => [
        // Ed25519 signing key pair (Digital Signature)
        'sign_sk' => env('SODIUM_SIGN_SK', ''),  // Base64 Ed25519 secret key (64 bytes)
        'sign_pk' => env('SODIUM_SIGN_PK', ''),  // Base64 Ed25519 public key  (32 bytes)

        // X25519 box key pair (Asymmetric Encryption / ECDH)
        'box_sk' => env('SODIUM_BOX_SK', ''),   // Base64 X25519 secret key   (32 bytes)
        'box_pk' => env('SODIUM_BOX_PK', ''),   // Base64 X25519 public key   (32 bytes)
    ],

    // ─── Hybrid Encryption Service ───────────────────────────────────
    // ใช้โดย HybridEncryptionService (AES-256-GCM internal key derivation)
    'encrypter_secret' => env('CRYPTO_ENCRYPTER_SECRET', ''),

];
