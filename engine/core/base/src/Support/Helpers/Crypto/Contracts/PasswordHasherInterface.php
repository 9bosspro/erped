<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * PasswordHasherInterface — สัญญาสำหรับ Password Hashing Helper
 *
 * ครอบคลุม:
 *  - Hashing     (hash, hashBcrypt, hashWithPepper, generateSecurePassword)
 *  - Verification (verify, verifyWithPepper, needsRehash, upgradeHash)
 *  - Validation   (validate, validateWithDetails, getStrength, getStrengthLabel,
 *                  meetsMinimumStrength)
 *  - Hash Inspection (getHashInfo, getAlgorithmName, isArgon2id, isArgon2i,
 *                     isBcrypt, isLegacySaltFormat)
 *  - Legacy Support  (hashWithSalt, verifyWithSalt)
 */
interface PasswordHasherInterface
{
    // ─── Hashing ────────────────────────────────────────────────

    public function hash(string $password): string;

    public function hashBcrypt(string $password): string;

    public function hashWithPepper(string $password): string;

    public function generateSecurePassword(int $length = 16, bool $includeSpecial = true): string;

    // ─── Verification ───────────────────────────────────────────

    public function verify(string $password, string $hash): bool;

    public function verifyWithPepper(string $password, string $hash): bool;

    public function needsRehash(string $hash): bool;

    public function upgradeHash(string $password, string $currentHash): ?string;

    // ─── Validation ─────────────────────────────────────────────

    public function validate(string $password): bool;

    /** @return array{valid: bool, errors: string[], checks: array<string, bool>} */
    public function validateWithDetails(string $password): array;

    public function getStrength(string $password): int;

    public function getStrengthLabel(string $password): string;

    public function meetsMinimumStrength(string $password, int $minimumScore = 4): bool;

    // ─── Hash Inspection ────────────────────────────────────────

    /** @return array{algo: int|string, algoName: string, options: array} */
    public function getHashInfo(string $hash): array;

    public function getAlgorithmName(string $hash): string;

    public function isArgon2id(string $hash): bool;

    public function isArgon2i(string $hash): bool;

    public function isBcrypt(string $hash): bool;

    public function isLegacySaltFormat(string $hash): bool;

    // ─── Legacy Support ─────────────────────────────────────────

    /** @deprecated ใช้ hash() ด้วย Argon2id แทน */
    public function hashWithSalt(string $password): string;

    public function verifyWithSalt(string $password, string $hashWithSalt): bool;
}
