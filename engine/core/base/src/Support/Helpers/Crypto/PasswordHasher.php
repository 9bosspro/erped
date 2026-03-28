<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Contracts\PasswordHasherInterface;
use InvalidArgumentException;

/**
 * PasswordHasher — จัดการ Password Hashing ที่สมบูรณ์ครบวงจร
 *
 * ═══════════════════════════════════════════════════════════════
 *  Hashing (สร้าง hash)
 * ═══════════════════════════════════════════════════════════════
 *  hash($password)                      — Hash ด้วย Argon2id (แนะนำ)
 *  hashBcrypt($password)                — Hash ด้วย Bcrypt (compatibility)
 *  hashWithPepper($password)            — Hash ด้วย Argon2id + pepper
 *  generateSecurePassword($len, $spec)  — สร้างรหัสผ่านสุ่มที่ปลอดภัย
 *
 * ═══════════════════════════════════════════════════════════════
 *  Verification (ตรวจสอบ)
 * ═══════════════════════════════════════════════════════════════
 *  verify($password, $hash)             — ตรวจรหัสผ่านกับ hash
 *  verifyWithPepper($pw, $hash)         — ตรวจรหัสผ่าน + pepper
 *  needsRehash($hash)                   — ตรวจว่าต้อง rehash หรือไม่
 *  upgradeHash($password, $currentHash) — Rehash อัตโนมัติถ้าจำเป็น
 *
 * ═══════════════════════════════════════════════════════════════
 *  Validation (ตรวจ policy)
 * ═══════════════════════════════════════════════════════════════
 *  validate($password)            — ตรวจ policy (return bool)
 *  validateWithDetails($password) — ตรวจ policy พร้อมรายละเอียด
 *  getStrength($password)         — วัดความแข็งแรง (0–6)
 *  getStrengthLabel($password)    — ระดับความแข็งแรงเป็นข้อความ
 *  meetsMinimumStrength($pw, $min)— ตรวจว่าถึงเกณฑ์หรือไม่
 *
 * ═══════════════════════════════════════════════════════════════
 *  Hash Inspection (ตรวจสอบ hash)
 * ═══════════════════════════════════════════════════════════════
 *  getHashInfo($hash)             — ดูข้อมูล algorithm, options
 *  getAlgorithmName($hash)        — ดูชื่อ algorithm ที่ใช้
 *  isArgon2id($hash)              — ตรวจว่าเป็น Argon2id
 *  isArgon2i($hash)               — ตรวจว่าเป็น Argon2i
 *  isBcrypt($hash)                — ตรวจว่าเป็น Bcrypt
 *  isLegacySaltFormat($hash)      — ตรวจว่าเป็น legacy "hash:salt"
 *
 * ═══════════════════════════════════════════════════════════════
 *  Legacy Support (backward compatibility)
 * ═══════════════════════════════════════════════════════════════
 *  hashWithSalt($password)        — @deprecated SHA256 + salt
 *  verifyWithSalt($pw, $hash)     — ตรวจ legacy salt hash
 *
 * ─── ความปลอดภัย ────────────────────────────────────────────
 *  - Argon2id เป็น algorithm ที่แนะนำ (memory-hard, ป้องกัน GPU/ASIC)
 *  - Pepper (server-side secret) เพิ่มอีกชั้น แม้ DB รั่วก็ crack ไม่ได้
 *  - generateSecurePassword() ใช้ random_int() (CSPRNG)
 *  - Configurable policy ผ่าน config('crypto.password')
 *  - ไม่ hash empty string → throw exception ทันที
 *  - Auto-rehash เมื่อ parameters เปลี่ยน
 */
final class PasswordHasher implements PasswordHasherInterface
{
    // ─── Defaults ───────────────────────────────────────────────

    /** @var int Argon2id memory cost (KiB) — default 65536 (64 MiB) */
    private const DEFAULT_ARGON_MEMORY = PASSWORD_ARGON2_DEFAULT_MEMORY_COST;

    /** @var int Argon2id time cost (iterations) — default 4 */
    private const DEFAULT_ARGON_TIME = PASSWORD_ARGON2_DEFAULT_TIME_COST;

    /** @var int Argon2id threads — default 1 */
    private const DEFAULT_ARGON_THREADS = PASSWORD_ARGON2_DEFAULT_THREADS;

    /** @var int Bcrypt cost factor — default 12 */
    private const DEFAULT_BCRYPT_COST = 12;

    /** @var int ความยาวขั้นต่ำของรหัสผ่าน */
    private const MIN_LENGTH = 8;

    /** @var int ความยาวสูงสุดของรหัสผ่าน (Argon2id ไม่จำกัด — เลือก 128 เป็น sane limit) */
    private const MAX_LENGTH = 128;

    private readonly int $argonMemory;

    private readonly int $argonTime;

    private readonly int $argonThreads;

    private readonly int $bcryptCost;

    private readonly string $pepper;

    private readonly int $minLength;

    private readonly int $maxLength;

    // ─── Configurable policy flags ──────────────────────────────

    private readonly bool $requireUppercase;

    private readonly bool $requireLowercase;

    private readonly bool $requireDigit;

    private readonly bool $requireSpecial;

    public function __construct()
    {
        $this->argonMemory      = (int)  config('crypto.password.argon_memory',       self::DEFAULT_ARGON_MEMORY);
        $this->argonTime        = (int)  config('crypto.password.argon_time',         self::DEFAULT_ARGON_TIME);
        $this->argonThreads     = (int)  config('crypto.password.argon_threads',      self::DEFAULT_ARGON_THREADS);
        $this->bcryptCost       = (int)  config('crypto.password.bcrypt_cost',        self::DEFAULT_BCRYPT_COST);
        $this->pepper           = (string) config('crypto.password.pepper',           '');
        $this->minLength        = (int)  config('crypto.password.min_length',         self::MIN_LENGTH);
        $this->maxLength        = (int)  config('crypto.password.max_length',         self::MAX_LENGTH);
        $this->requireUppercase = (bool) config('crypto.password.require_uppercase',  true);
        $this->requireLowercase = (bool) config('crypto.password.require_lowercase',  true);
        $this->requireDigit     = (bool) config('crypto.password.require_digit',      true);
        $this->requireSpecial   = (bool) config('crypto.password.require_special',    true);
    }

    // ═══════════════════════════════════════════════════════════
    //  Hashing
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash ของรหัสผ่านด้วย Argon2id
     *
     * Argon2id = Argon2i (side-channel resistant) + Argon2d (GPU resistant)
     * ป้องกันทั้ง timing attack และ GPU/ASIC brute force
     *
     * @param  string  $password  รหัสผ่าน plain text
     * @return string  Argon2id hash string
     *
     * @throws InvalidArgumentException ถ้ารหัสผ่านว่าง
     */
    public function hash(string $password): string
    {
        $this->assertNotEmpty($password);

        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->argonMemory,
            'time_cost'   => $this->argonTime,
            'threads'     => $this->argonThreads,
        ]);
    }

    /**
     * สร้าง hash ด้วย Bcrypt
     *
     * ใช้เมื่อ: ระบบไม่รองรับ Argon2id หรือต้องการ compatibility กับ legacy
     * ⚠️ Bcrypt ตัด input ที่ 72 bytes — รหัสผ่านยาวกว่านี้จะถูกตัด
     *
     * @param  string  $password  รหัสผ่าน plain text
     * @return string  Bcrypt hash string
     */
    public function hashBcrypt(string $password): string
    {
        $this->assertNotEmpty($password);

        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => $this->bcryptCost,
        ]);
    }

    /**
     * สร้าง hash ด้วย Argon2id + pepper
     *
     * Pepper = server-side secret ที่ไม่เก็บใน DB
     * → แม้ DB รั่ว attacker ก็ crack ไม่ได้เพราะไม่มี pepper
     *
     * ขั้นตอน: HMAC-SHA256(pepper, password) → Argon2id
     * HMAC ให้ fixed-length 64-char hex → ป้องกัน Bcrypt 72-byte truncation
     *
     * ⚠️ ต้องตั้ง CRYPTO_PASSWORD_PEPPER ใน .env
     *    ถ้า pepper เปลี่ยน → hash เก่าทั้งหมดจะ verify ไม่ผ่าน
     *
     * @param  string  $password  รหัสผ่าน plain text
     * @return string  Argon2id hash string (peppered)
     *
     * @throws InvalidArgumentException ถ้า pepper ไม่ได้ตั้งค่า
     */
    public function hashWithPepper(string $password): string
    {
        $this->assertNotEmpty($password);
        $this->assertPepperConfigured();

        $peppered = hash_hmac('sha256', $password, $this->pepper);

        return $this->hash($peppered);
    }

    /**
     * สร้างรหัสผ่านสุ่มที่ปลอดภัยด้วย CSPRNG (random_int)
     *
     * รับประกัน:
     *  - มีอักษรพิมพ์ใหญ่ พิมพ์เล็ก และตัวเลขอย่างน้อย 1 ตัวเสมอ
     *  - มีอักขระพิเศษอย่างน้อย 1 ตัว (ถ้า $includeSpecial = true)
     *  - Shuffle ด้วย Fisher-Yates + random_int() (ไม่ใช้ str_shuffle)
     *
     * @param  int   $length          ความยาวรหัสผ่าน (ขั้นต่ำ 8)
     * @param  bool  $includeSpecial  ใส่อักขระพิเศษหรือไม่
     * @return string  รหัสผ่านสุ่ม
     *
     * @throws InvalidArgumentException ถ้า $length < 8
     */
    public function generateSecurePassword(int $length = 16, bool $includeSpecial = true): string
    {
        if ($length < 8) {
            throw new InvalidArgumentException('ความยาวรหัสผ่านต้องไม่น้อยกว่า 8 ตัวอักษร');
        }

        $lower   = 'abcdefghijklmnopqrstuvwxyz';
        $upper   = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits  = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $pool = $lower . $upper . $digits . ($includeSpecial ? $special : '');

        // รับประกัน mandatory chars อย่างน้อย 1 ตัวจากแต่ละหมวด
        $password  = $lower[random_int(0, strlen($lower) - 1)];
        $password .= $upper[random_int(0, strlen($upper) - 1)];
        $password .= $digits[random_int(0, strlen($digits) - 1)];

        if ($includeSpecial) {
            $password .= $special[random_int(0, strlen($special) - 1)];
        }

        $poolLen = strlen($pool);

        while (strlen($password) < $length) {
            $password .= $pool[random_int(0, $poolLen - 1)];
        }

        return $this->secureShuffle($password);
    }

    // ═══════════════════════════════════════════════════════════
    //  Verification
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจสอบรหัสผ่านกับ hash
     *
     * รองรับทุก algorithm ที่ PHP password_verify() รองรับ
     * (Argon2id, Argon2i, Bcrypt) และ legacy "hash:salt" format
     *
     * @param  string  $password  รหัสผ่าน plain text
     * @param  string  $hash      password hash จากฐานข้อมูล
     * @return bool  true ถ้ารหัสผ่านตรงกับ hash
     */
    public function verify(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        if ($this->isLegacySaltFormat($hash)) {
            return $this->verifyWithSalt($password, $hash);
        }

        return password_verify($password, $hash);
    }

    /**
     * ตรวจสอบรหัสผ่าน + pepper
     *
     * @param  string  $password  รหัสผ่าน plain text
     * @param  string  $hash      password hash (ที่ hash ด้วย hashWithPepper)
     * @return bool  true ถ้ารหัสผ่านตรงกัน
     */
    public function verifyWithPepper(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        $this->assertPepperConfigured();

        $peppered = hash_hmac('sha256', $password, $this->pepper);

        return password_verify($peppered, $hash);
    }

    /**
     * ตรวจสอบว่ารหัสผ่านต้อง rehash หรือไม่
     *
     * กรณีที่ต้อง rehash:
     *  - Legacy salt format (มี ":" ใน hash)
     *  - Bcrypt → ควร migrate เป็น Argon2id
     *  - Argon2id parameters เก่ากว่า config ปัจจุบัน
     *
     * @param  string  $hash  password hash
     * @return bool  true ถ้าต้อง rehash
     */
    public function needsRehash(string $hash): bool
    {
        if ($hash === '' || $this->isLegacySaltFormat($hash)) {
            return true;
        }

        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->argonMemory,
            'time_cost'   => $this->argonTime,
            'threads'     => $this->argonThreads,
        ]);
    }

    /**
     * Rehash รหัสผ่านถ้าจำเป็น (ตาม needsRehash)
     *
     * ใช้ใน login flow: ถ้า verify ผ่านและ needsRehash → rehash แล้วบันทึก hash ใหม่
     *
     * Flow ที่แนะนำ:
     * ```php
     * if ($hasher->verify($password, $storedHash)) {
     *     $newHash = $hasher->upgradeHash($password, $storedHash);
     *     if ($newHash !== null) {
     *         $user->update(['password' => $newHash]);
     *     }
     * }
     * ```
     *
     * @param  string  $password     รหัสผ่าน plain text (ต้อง verify ผ่านก่อน)
     * @param  string  $currentHash  hash ปัจจุบันในฐานข้อมูล
     * @return string|null  hash ใหม่ถ้าต้องอัปเกรด, null ถ้าไม่จำเป็น
     */
    public function upgradeHash(string $password, string $currentHash): ?string
    {
        if (! $this->needsRehash($currentHash)) {
            return null;
        }

        if (! $this->verify($password, $currentHash)) {
            return null;
        }

        return $this->hash($password);
    }

    // ═══════════════════════════════════════════════════════════
    //  Validation
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจสอบรหัสผ่านตาม policy
     *
     * Policy ควบคุมได้จาก config('crypto.password'):
     *  - ความยาว min_length – max_length
     *  - require_uppercase  — ต้องมีตัวพิมพ์ใหญ่ (A-Z)
     *  - require_lowercase  — ต้องมีตัวพิมพ์เล็ก (a-z)
     *  - require_digit      — ต้องมีตัวเลข (0-9)
     *  - require_special    — ต้องมีอักขระพิเศษ
     *
     * @param  string  $password  รหัสผ่านที่ต้องการตรวจสอบ
     * @return bool  true ถ้าผ่านเกณฑ์ทั้งหมด
     */
    public function validate(string $password): bool
    {
        return $this->validateWithDetails($password)['valid'];
    }

    /**
     * ตรวจสอบรหัสผ่านพร้อมรายละเอียด — เหมาะสำหรับแสดง error ให้ user
     *
     * @param  string  $password  รหัสผ่าน
     * @return array{valid: bool, errors: string[], checks: array<string, bool>}
     */
    public function validateWithDetails(string $password): array
    {
        $length = mb_strlen($password);

        $checks = [
            'min_length' => $length >= $this->minLength,
            'max_length' => $length <= $this->maxLength,
            'uppercase'  => ! $this->requireUppercase || preg_match('/[A-Z]/', $password) === 1,
            'lowercase'  => ! $this->requireLowercase || preg_match('/[a-z]/', $password) === 1,
            'digit'      => ! $this->requireDigit     || preg_match('/\d/', $password) === 1,
            'special'    => ! $this->requireSpecial   || preg_match('/[^A-Za-z\d]/', $password) === 1,
        ];

        $errors = [];

        if (! $checks['min_length']) {
            $errors[] = "รหัสผ่านต้องมีอย่างน้อย {$this->minLength} ตัวอักษร";
        }

        if (! $checks['max_length']) {
            $errors[] = "รหัสผ่านต้องไม่เกิน {$this->maxLength} ตัวอักษร";
        }

        if (! $checks['uppercase']) {
            $errors[] = 'ต้องมีตัวอักษรพิมพ์ใหญ่ (A-Z) อย่างน้อย 1 ตัว';
        }

        if (! $checks['lowercase']) {
            $errors[] = 'ต้องมีตัวอักษรพิมพ์เล็ก (a-z) อย่างน้อย 1 ตัว';
        }

        if (! $checks['digit']) {
            $errors[] = 'ต้องมีตัวเลข (0-9) อย่างน้อย 1 ตัว';
        }

        if (! $checks['special']) {
            $errors[] = 'ต้องมีอักขระพิเศษ (!@#$%^&* ฯลฯ) อย่างน้อย 1 ตัว';
        }

        return [
            'valid'  => $errors === [],
            'errors' => $errors,
            'checks' => $checks,
        ];
    }

    /**
     * วัดความแข็งแรงของรหัสผ่าน (0–6)
     *
     * เกณฑ์การให้คะแนน (แต่ละข้อ +1):
     *  0–1: อ่อนแอมาก  |  2–3: ปานกลาง  |  4–5: แข็งแรง  |  6: แข็งแรงมาก
     *
     * @param  string  $password  รหัสผ่าน
     * @return int  คะแนน 0–6
     */
    public function getStrength(string $password): int
    {
        $score = 0;

        if (mb_strlen($password) >= 8)  { $score++; }
        if (mb_strlen($password) >= 12) { $score++; }
        if (preg_match('/[A-Z]/', $password))    { $score++; }
        if (preg_match('/[a-z]/', $password))    { $score++; }
        if (preg_match('/\d/', $password))       { $score++; }
        if (preg_match('/[^A-Za-z\d]/', $password)) { $score++; }

        return $score;
    }

    /**
     * ดึงระดับความแข็งแรงเป็นข้อความ
     *
     * @param  string  $password  รหัสผ่าน
     * @return string  ระดับ: "อ่อนแอมาก" | "อ่อนแอ" | "ปานกลาง" | "แข็งแรง" | "แข็งแรงมาก"
     */
    public function getStrengthLabel(string $password): string
    {
        $score = $this->getStrength($password);

        return match (true) {
            $score <= 1 => 'อ่อนแอมาก',
            $score <= 2 => 'อ่อนแอ',
            $score <= 3 => 'ปานกลาง',
            $score <= 4 => 'แข็งแรง',
            default     => 'แข็งแรงมาก',
        };
    }

    /**
     * ตรวจว่ารหัสผ่านถึงเกณฑ์ความแข็งแรงขั้นต่ำหรือไม่
     *
     * @param  string  $password      รหัสผ่าน
     * @param  int     $minimumScore  คะแนนขั้นต่ำ (default: 4)
     * @return bool  true ถ้าถึงเกณฑ์
     */
    public function meetsMinimumStrength(string $password, int $minimumScore = 4): bool
    {
        return $this->getStrength($password) >= $minimumScore;
    }

    // ═══════════════════════════════════════════════════════════
    //  Hash Inspection
    // ═══════════════════════════════════════════════════════════

    /**
     * ดูข้อมูลของ password hash
     *
     * @param  string  $hash  password hash
     * @return array{algo: int|string, algoName: string, options: array}
     */
    public function getHashInfo(string $hash): array
    {
        if ($this->isLegacySaltFormat($hash)) {
            return [
                'algo'     => 'legacy',
                'algoName' => 'sha256+salt (legacy)',
                'options'  => [],
            ];
        }

        $info = password_get_info($hash);

        return [
            'algo'     => $info['algo'],
            'algoName' => $info['algoName'] ?: 'unknown',
            'options'  => $info['options'],
        ];
    }

    /**
     * ดูชื่อ algorithm ที่ใช้ hash
     *
     * @param  string  $hash  password hash
     * @return string  ชื่อ algorithm (เช่น "argon2id", "bcrypt", "sha256+salt")
     */
    public function getAlgorithmName(string $hash): string
    {
        return $this->getHashInfo($hash)['algoName'];
    }

    /**
     * ตรวจว่า hash เป็น Argon2id หรือไม่
     */
    public function isArgon2id(string $hash): bool
    {
        return str_starts_with($hash, '$argon2id$');
    }

    /**
     * ตรวจว่า hash เป็น Argon2i หรือไม่
     */
    public function isArgon2i(string $hash): bool
    {
        return str_starts_with($hash, '$argon2i$');
    }

    /**
     * ตรวจว่า hash เป็น Bcrypt หรือไม่
     */
    public function isBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2b$');
    }

    /**
     * ตรวจว่า hash เป็น legacy "hash:salt" format หรือไม่
     */
    public function isLegacySaltFormat(string $hash): bool
    {
        return str_contains($hash, ':') && ! str_starts_with($hash, '$');
    }

    // ═══════════════════════════════════════════════════════════
    //  Legacy Support
    // ═══════════════════════════════════════════════════════════

    /**
     * Hash ด้วย SHA256 + random salt (legacy)
     *
     * @deprecated ใช้ hash() ด้วย Argon2id แทน — SHA256 ไม่เหมาะสำหรับ password
     *
     * @param  string  $password  รหัสผ่าน
     * @return string  hash ในรูปแบบ "sha256_hex:salt_hex"
     */
    public function hashWithSalt(string $password): string
    {
        $this->assertNotEmpty($password);

        $salt = bin2hex(random_bytes(16));

        return hash('sha256', $salt . $password) . ':' . $salt;
    }

    /**
     * ตรวจสอบรหัสผ่านกับ legacy "hash:salt" format
     *
     * @param  string  $password      รหัสผ่าน plain text
     * @param  string  $hashWithSalt  hash ในรูปแบบ "sha256_hex:salt_hex"
     * @return bool  true ถ้าตรงกัน
     */
    public function verifyWithSalt(string $password, string $hashWithSalt): bool
    {
        if ($password === '' || $hashWithSalt === '') {
            return false;
        }

        $parts = explode(':', $hashWithSalt, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$expectedHash, $salt] = $parts;

        return hash_equals($expectedHash, hash('sha256', $salt . $password));
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * ตรวจว่ารหัสผ่านไม่ว่าง
     */
    private function assertNotEmpty(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('รหัสผ่านต้องไม่เป็นค่าว่าง');
        }
    }

    /**
     * ตรวจว่า pepper ถูกตั้งค่าแล้ว
     */
    private function assertPepperConfigured(): void
    {
        if ($this->pepper === '') {
            throw new InvalidArgumentException(
                'Pepper is required. Set CRYPTO_PASSWORD_PEPPER in .env',
            );
        }
    }

    /**
     * Shuffle string ด้วย Fisher-Yates + CSPRNG (random_int)
     *
     * ปลอดภัยกว่า str_shuffle() ที่ใช้ PHP internal RNG ที่ไม่ใช่ CSPRNG
     *
     * @param  string  $str  string ที่ต้องการ shuffle
     * @return string  shuffled string
     */
    private function secureShuffle(string $str): string
    {
        $chars = str_split($str);
        $n     = count($chars);

        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
