<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\PasswordHasherInterface;
use InvalidArgumentException;

/**
 * PasswordHasher — จัดการ Password Hashing ที่สมบูรณ์ครบวงจร (Security Namespace)
 */
final class PasswordHasher implements PasswordHasherInterface
{
    use ParsesEncryptionKey;
    // ─── Defaults ───────────────────────────────────────────────

    private const DEFAULT_ARGON_MEMORY = PASSWORD_ARGON2_DEFAULT_MEMORY_COST;

    private const DEFAULT_ARGON_TIME = PASSWORD_ARGON2_DEFAULT_TIME_COST;

    private const DEFAULT_ARGON_THREADS = PASSWORD_ARGON2_DEFAULT_THREADS;

    private const DEFAULT_BCRYPT_COST = 12;

    private const MIN_LENGTH = 8;

    private const MAX_LENGTH = 128;

    private readonly int $argonMemory;

    private readonly int $argonTime;

    private readonly int $argonThreads;

    private readonly int $bcryptCost;

    private readonly string $pepper;

    private readonly int $minLength;

    private readonly int $maxLength;

    private readonly bool $requireUppercase;

    private readonly bool $requireLowercase;

    private readonly bool $requireDigit;

    private readonly bool $requireSpecial;

    public function __construct()
    {
        $this->argonMemory = (int) config('core.base::security.password.argon_memory', self::DEFAULT_ARGON_MEMORY);
        $this->argonTime = (int) config('core.base::security.password.argon_time', self::DEFAULT_ARGON_TIME);
        $this->argonThreads = (int) config('core.base::security.password.argon_threads', self::DEFAULT_ARGON_THREADS);
        $this->bcryptCost = (int) config('core.base::security.password.bcrypt_cost', self::DEFAULT_BCRYPT_COST);
        $this->pepper = (string) config('core.base::security.password.pepper', '');
        $this->minLength = (int) config('core.base::security.password.min_length', self::MIN_LENGTH);
        $this->maxLength = (int) config('core.base::security.password.max_length', self::MAX_LENGTH);
        $this->requireUppercase = (bool) config('core.base::security.password.require_uppercase', true);
        $this->requireLowercase = (bool) config('core.base::security.password.require_lowercase', true);
        $this->requireDigit = (bool) config('core.base::security.password.require_digit', true);
        $this->requireSpecial = (bool) config('core.base::security.password.require_special', true);
    }

    // ═══════════════════════════════════════════════════════════
    //  Hashing
    // ═══════════════════════════════════════════════════════════

    public function hash(string $password): string
    {
        $this->assertNotEmpty($password);

        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->argonMemory,
            'time_cost' => $this->argonTime,
            'threads' => $this->argonThreads,
        ]);
    }

    public function hashBcrypt(string $password): string
    {
        $this->assertNotEmpty($password);

        if (strlen($password) > 72) {
            throw new InvalidArgumentException('BCrypt รองรับรหัสผ่านสูงสุด 72 bytes — ใช้ hash() (Argon2id) แทนสำหรับรหัสผ่านที่ยาวกว่านี้');
        }

        return password_hash($password, PASSWORD_BCRYPT, [
            'cost' => $this->bcryptCost,
        ]);
    }

    public function hashWithPepper(string $password): string
    {
        $this->assertNotEmpty($password);
        $this->assertPepperConfigured();

        $peppered = hash_hmac('sha256', $password, $this->pepper, true); // raw binary — entropy เต็ม 256 bits

        return $this->hash($peppered);
    }

    public function generateSecurePassword(int $length = 16, bool $includeSpecial = true): string
    {
        if ($length < 8) {
            throw new InvalidArgumentException('ความยาวรหัสผ่านต้องไม่น้อยกว่า 8 ตัวอักษร');
        }

        $lower = 'abcdefghijklmnopqrstuvwxyz';
        $upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $digits = '0123456789';
        $special = '!@#$%^&*()_+-=[]{}|;:,.<>?';

        $pool = $lower.$upper.$digits.($includeSpecial ? $special : '');

        $password = $lower[random_int(0, strlen($lower) - 1)];
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

    public function verifyWithPepper(string $password, string $hash): bool
    {
        if ($password === '' || $hash === '') {
            return false;
        }

        $this->assertPepperConfigured();

        $peppered = hash_hmac('sha256', $password, $this->pepper, true); // raw binary — ตรงกับ hashWithPepper

        return password_verify($peppered, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        if ($hash === '' || $this->isLegacySaltFormat($hash)) {
            return true;
        }

        return password_needs_rehash($hash, PASSWORD_ARGON2ID, [
            'memory_cost' => $this->argonMemory,
            'time_cost' => $this->argonTime,
            'threads' => $this->argonThreads,
        ]);
    }

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

    public function validate(string $password): bool
    {
        return $this->validateWithDetails($password)['valid'];
    }

    public function validateWithDetails(string $password): array
    {
        $length = mb_strlen($password);

        $checks = [
            'min_length' => $length >= $this->minLength,
            'max_length' => $length <= $this->maxLength,
            'uppercase' => ! $this->requireUppercase || preg_match('/[A-Z]/', $password) === 1,
            'lowercase' => ! $this->requireLowercase || preg_match('/[a-z]/', $password) === 1,
            'digit' => ! $this->requireDigit || preg_match('/\d/', $password) === 1,
            'special' => ! $this->requireSpecial || preg_match('/[^A-Za-z\d]/', $password) === 1,
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
            'valid' => $errors === [],
            'errors' => $errors,
            'checks' => $checks,
        ];
    }

    public function getStrength(string $password): int
    {
        $score = 0;
        $len = mb_strlen($password);

        // 1. ความยาวเป็นปัจจัยสำคัญที่สุด
        if ($len >= 8) {
            $score++;
        }
        if ($len >= 12) {
            $score++;
        }
        if ($len >= 16) {
            $score++;
        }

        // 2. ความหลากหลายของอักขระ
        if (preg_match('/[A-Z]/', $password)) {
            $score++;
        }
        if (preg_match('/[a-z]/', $password)) {
            $score++;
        }
        if (preg_match('/\d/', $password)) {
            $score++;
        }
        if (preg_match('/[^A-Za-z\d]/', $password)) {
            $score++;
        }

        // 3. บทลงโทษสำหรับรูปแบบที่เดาง่าย (Penalties)
        $weakPatterns = [
            '123', 'qwerty', 'admin', 'password', 'asdf', 'abc',
        ];

        foreach ($weakPatterns as $pattern) {
            if (str_contains(strtolower($password), $pattern)) {
                $score = max(0, $score - 1);
                break;
            }
        }

        return $score;
    }

    public function getStrengthLabel(string $password): string
    {
        $score = $this->getStrength($password);

        return match (true) {
            $score <= 1 => 'อ่อนแอมาก',
            $score <= 3 => 'อ่อนแอ',
            $score <= 4 => 'ปานกลาง',
            $score <= 5 => 'แข็งแรง',
            default => 'แข็งแรงมาก',  // 6-7
        };
    }

    public function meetsMinimumStrength(string $password, int $minimumScore = 4): bool
    {
        return $this->getStrength($password) >= $minimumScore;
    }

    // ═══════════════════════════════════════════════════════════
    //  Hash Inspection
    // ═══════════════════════════════════════════════════════════

    public function getHashInfo(string $hash): array
    {
        if ($this->isLegacySaltFormat($hash)) {
            return [
                'algo' => 'legacy',
                'algoName' => 'sha256+salt (legacy)',
                'options' => [],
            ];
        }

        $info = password_get_info($hash);

        return [
            'algo' => $info['algo'],
            'algoName' => $info['algoName'] ?: 'unknown',
            'options' => $info['options'],
        ];
    }

    public function getAlgorithmName(string $hash): string
    {
        return $this->getHashInfo($hash)['algoName'];
    }

    public function isArgon2id(string $hash): bool
    {
        return str_starts_with($hash, '$argon2id$');
    }

    public function isArgon2i(string $hash): bool
    {
        return str_starts_with($hash, '$argon2i$');
    }

    public function isBcrypt(string $hash): bool
    {
        return str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2b$');
    }

    public function isLegacySaltFormat(string $hash): bool
    {
        return str_contains($hash, ':') && ! str_starts_with($hash, '$');
    }

    // ═══════════════════════════════════════════════════════════
    //  Legacy Support
    // ═══════════════════════════════════════════════════════════

    /**
     * @deprecated ใช้เพื่อ verify legacy hash เท่านั้น ห้ามสร้าง hash ใหม่ด้วย method นี้
     *             SHA-256 เร็วเกินไปสำหรับ password — ใช้ hash() (Argon2id) แทน
     */
    public function hashWithSalt(string $password): string
    {
        $this->assertNotEmpty($password);

        $salt = bin2hex(random_bytes(16));

        return hash('sha256', $salt.$password).':'.$salt;
    }

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

        return hash_equals($expectedHash, hash('sha256', $salt.$password));
    }

    // ─── Private ────────────────────────────────────────────────

    private function assertNotEmpty(string $password): void
    {
        if ($password === '') {
            throw new InvalidArgumentException('รหัสผ่านต้องไม่เป็นค่าว่าง');
        }
    }

    private function assertPepperConfigured(): void
    {
        if ($this->pepper === '') {
            throw new InvalidArgumentException(
                'Pepper is required. Set core.base.crypto.password.pepper in config',
            );
        }
    }

    private function secureShuffle(string $str): string
    {
        $chars = str_split($str);
        $n = count($chars);

        for ($i = $n - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }
}
