<?php

declare(strict_types=1);

namespace Core\Base\Enums;

/**
 * ระดับความปลอดภัยของ Argon2id Password Hashing
 *
 * ใช้แทน PWHASH_* string constants ใน HashHelper และ SodiumHelper
 * เพื่อให้ type-safe และไม่มี constant ซ้ำกัน
 */
enum ArgonLevel: string
{
    /** เหมาะสำหรับ web login (~64 MB RAM, ~0.3 s) */
    case Interactive = 'interactive';

    /** เหมาะสำหรับข้อมูล sensitive (~256 MB RAM, ~1 s) */
    case Moderate = 'moderate';

    /** ความปลอดภัยสูงสุด (~1 GB RAM, ~5 s) */
    case Sensitive = 'sensitive';

    /**
     * คืน [opslimit, memlimit] สำหรับ sodium_crypto_pwhash() / sodium_crypto_pwhash_str()
     *
     * @return array{0: int, 1: int}
     */
    public function params(): array
    {
        return match ($this) {
            self::Interactive => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            ],
            self::Moderate => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            ],
            self::Sensitive => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
            ],
        };
    }

    /**
     * คืน options array สำหรับ PHP password_hash(PASSWORD_ARGON2ID, $options)
     *
     * ใช้ใน PasswordHasher::hashAtLevel() เพื่อ type-safe level selection
     *
     * @return array{memory_cost: int, time_cost: int, threads: int}
     */
    public function phpParams(): array
    {
        return match ($this) {
            self::Interactive => ['memory_cost' => 65536,   'time_cost' => 2, 'threads' => 1],
            self::Moderate => ['memory_cost' => 262144,  'time_cost' => 3, 'threads' => 1],
            self::Sensitive => ['memory_cost' => 1048576, 'time_cost' => 4, 'threads' => 1],
        };
    }
}
