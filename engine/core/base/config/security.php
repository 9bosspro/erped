<?php

declare(strict_types=1);

/**
 * Security Configuration — ค่ากำหนดด้านความปลอดภัยหลักของระบบ
 *
 * ═══════════════════════════════════════════════════════════════
 *  ครอบคลุม:
 * ═══════════════════════════════════════════════════════════════
 *  - masterkey  — กุญแจหลัก (Master Key) สำหรับ HKDF derivation
 *                 ใช้โดย EncryptionService::deriveKeyFromMasterKey()
 *                 และ AppHelper::deriveKey()
 *
 *  - key32      — กุญแจ Sodium 32 bytes (Base64)
 *                 ใช้โดย SodiumHelper (appKey default)
 *                 และ EncryptionService (encryptWithKey/decryptWithKey)
 *
 * ⚠️  Production: ทั้งสอง key ต้องกำหนดใน .env หรือ secret manager
 *      ห้ามใช้ค่า default ใน production โดยเด็ดขาด
 *
 * 🔑  วิธีสร้าง key32:
 *      php -r "echo base64_encode(sodium_crypto_secretbox_keygen());"
 *      แล้วนำไปใส่ KEYSODIAM ใน .env
 */
return [

    // ─── Master Key ──────────────────────────────────────────────────
    // กุญแจหลักสำหรับอนุมานกุญแจย่อยด้วย HKDF-SHA3-256
    // ⚠️ ต้องมีความยาวอย่างน้อย 32 bytes (recommend: 64+ bytes)
    'masterkey' => env('MASTERKEY'),

    // ─── Sodium Secret Key (32 bytes) ────────────────────────────────
    // กุญแจ Base64 สำหรับ XSalsa20-Poly1305 SecretBox / XChaCha20-Poly1305 AEAD
    // ⚠️ ต้องเป็น Base64 ของ raw 32 bytes พอดี (SODIUM_CRYPTO_SECRETBOX_KEYBYTES)
    'base64key32' => env('KEYSODIAM'),
    //
    'publickeysign' => env('PUBLICKEYSIGN'),
    'privatekeysign' => env('PRIVATEKEYSIGN'),
    //
    'publickeyexchange' => env('PUBLICKEYEXCHANGE'),
    'privatekeyexchange' => env('PRIVATEKEYEXCHANGE'),
    //
    //
    'publickeybox' => env('PUBLICKEYBOX'),
    'privatekeybox' => env('PRIVATEKEYBOX'),
    'hash_salt' => [
        'key1' => env('HASH_SALT_KEY1'), //
        'key2' => env('HASH_SALT_KEY2'),
    ],
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
];
