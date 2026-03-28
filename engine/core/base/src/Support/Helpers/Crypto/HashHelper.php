<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface;
use InvalidArgumentException;
use RuntimeException;

/**
 * HashHelper — Hashing Helper ที่สมบูรณ์ครบวงจร
 *
 * ครอบคลุมทุก use case ของ hashing (ยกเว้น password → ใช้ PasswordHasher)
 *
 * ─── Standard Hash ──────────────────────────────────────
 *  hash()              — hash string ด้วย algorithm ที่เลือก
 *  hashData()          — hash mixed data (array/object → json_encode อัตโนมัติ)
 *
 * ─── Salted Hash ────────────────────────────────────────
 *  hashWithSalt()      — hash + random salt → คืน "hex_hash:hex_salt"
 *  verifySaltedHash()  — verify salted hash (timing-safe)
 *  hashWithDoubleSalt()— SHA256 + SHA3-256 layered (double keyed)
 *  verifyDoubleSalt()  — verify double-salt hash
 *
 * ─── HMAC Sign / Verify ────────────────────────────────
 *  hmacSign()          — HMAC signature (รองรับหลาย algo)
 *  hmacVerify()        — verify HMAC (timing-safe)
 *
 * ─── Signature Hash ────────────────────────────────────
 *  signatureHash()     — สร้าง signature จาก mixed data
 *  verifySignatureHash() — verify signature
 *
 * ─── Streaming / Incremental ───────────────────────────
 *  hashStream()        — hash จาก resource/stream (ไม่โหลดทั้งไฟล์เข้า memory)
 *  hashChunked()       — hash จาก array ของ chunks
 *
 * ─── File Checksum ─────────────────────────────────────
 *  fileChecksum()      — hash ไฟล์
 *  verifyFileChecksum()— verify checksum ไฟล์
 *
 * ─── Content Fingerprint ───────────────────────────────
 *  fingerprint()       — deterministic fingerprint สำหรับ caching/dedup
 *
 * ─── HKDF (Key Derivation) ─────────────────────────────
 *  hkdf()              — RFC 5869 HKDF — derive key จาก key material
 *
 * ─── Utility ───────────────────────────────────────────
 *  equals()            — timing-safe comparison
 *  getAvailableAlgorithms() / isAlgorithmSupported()
 */
final class HashHelper implements HashHelperInterface
{
    use ParsesEncryptionKey;

    private const DEFAULT_ALGO = 'sha3-256';

    private const HMAC_DEFAULT_ALGO = 'sha256';

    private const SALT_LENGTH = 16;

    private readonly string $appKey;

    private readonly ?string $saltKey1;

    private readonly ?string $saltKey2;

    public function __construct()
    {
        $rawKey = (string) config('app.key', '');
        $this->appKey = $this->parseKey($rawKey);
        $this->saltKey1 = config('crypto.hash_salt.key1');
        $this->saltKey2 = config('crypto.hash_salt.key2');
    }

    // ═══════════════════════════════════════════════════════════
    //  Standard Hash
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash จาก string
     *
     * @param  string  $data  ข้อมูลที่ต้องการ hash
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @param  bool  $binary  true = คืน raw binary แทน hex
     * @return string hash (hex หรือ binary)
     */
    public function hash(string $data, string $algorithm = self::DEFAULT_ALGO, bool $binary = false): string
    {
        $this->assertAlgorithm($algorithm);

        return hash($algorithm, $data, $binary);
    }

    /**
     * สร้าง hash จาก mixed data (array/object จะถูก json_encode อัตโนมัติ)
     *
     * @param  mixed  $data  ข้อมูล (string, array, object, int ฯลฯ)
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @return string hash hex string
     */
    public function hashData(mixed $data, string $algorithm = self::DEFAULT_ALGO): string
    {
        return $this->hash($this->normalizeData($data), $algorithm);
    }

    // ═══════════════════════════════════════════════════════════
    //  Salted Hash (random salt)
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash พร้อม random salt
     *
     * Output format: "hex_hash:hex_salt"
     * เหมาะสำหรับ: hash ข้อมูลที่ต้องการ verify ภายหลัง แต่ไม่ใช่ password
     * (เช่น token, API key, secret identifier)
     *
     * @param  string  $data  ข้อมูลที่ต้องการ hash
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @return string "hex_hash:hex_salt"
     */
    public function hashWithSalt(string $data, string $algorithm = self::DEFAULT_ALGO): string
    {
        $this->assertAlgorithm($algorithm);

        $salt = random_bytes(self::SALT_LENGTH);
        $hash = hash($algorithm, $salt . $data);

        return $hash . ':' . bin2hex($salt);
    }

    /**
     * ตรวจสอบ salted hash (timing-safe)
     *
     * @param  string  $data  ข้อมูลต้นทาง
     * @param  string  $saltedHash  hash ในรูปแบบ "hex_hash:hex_salt"
     * @param  string  $algorithm  hash algorithm (ต้องตรงกับตอน hash)
     * @return bool true ถ้าตรงกัน
     */
    public function verifySaltedHash(string $data, string $saltedHash, string $algorithm = self::DEFAULT_ALGO): bool
    {
        $parts = explode(':', $saltedHash, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$expectedHash, $saltHex] = $parts;

        $salt = hex2bin($saltHex);

        if ($salt === false) {
            return false;
        }

        $actualHash = hash($algorithm, $salt . $data);

        return hash_equals($expectedHash, $actualHash);
    }

    // ═══════════════════════════════════════════════════════════
    //  Double-Salt Hash (SHA256 + SHA3-256 layered)
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash แบบ one-way ด้วย double salt (keyed, layered)
     *
     * กระบวนการ:
     *   1. key1 + input + key2 → SHA256 (layer 1)
     *   2. key2 + layer1 + key1 → SHA256 (layer 2)
     *   3. layer2 → SHA3-256 (final — algorithm ต่างตระกูลเพื่อลด collision risk)
     *
     * เหมาะสำหรับ: hash ข้อมูลที่ต้องการความปลอดภัยสูง (เช่น sensitive identifier)
     * ต้องกำหนด HASH_SALT_KEY1 + HASH_SALT_KEY2 ใน .env สำหรับ production
     *
     * @param  string  $input  ข้อความที่ต้องการ hash (empty → คืน empty)
     * @return string SHA3-256 hex hash หรือ empty string
     *
     * @throws RuntimeException ถ้า production ไม่ได้กำหนด keys
     */
    public function hashWithDoubleSalt(string $input): string
    {
        if ($input === '') {
            return '';
        }

        [$key1, $key2] = $this->resolveSaltKeys();

        $layer1 = hash('sha256', $key1 . $input . $key2);
        $layer2 = hash('sha256', $key2 . $layer1 . $key1);

        return hash('sha3-256', $layer2);
    }

    /**
     * ตรวจสอบ double-salt hash (timing-safe)
     *
     * @param  string  $input  ข้อความต้นทาง
     * @param  string  $expectedHash  hash ที่คาดหวัง
     * @return bool true ถ้าตรงกัน
     */
    public function verifyDoubleSalt(string $input, string $expectedHash): bool
    {
        if ($input === '' || $expectedHash === '') {
            return false;
        }

        return hash_equals($this->hashWithDoubleSalt($input), $expectedHash);
    }

    // ═══════════════════════════════════════════════════════════
    //  HMAC Sign / Verify
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง HMAC signature
     *
     * @param  string|array  $data  ข้อมูล (array → json_encode อัตโนมัติ)
     * @param  string|null  $key  กุญแจ HMAC (null = ใช้ APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (default: sha256)
     * @param  bool  $binary  true = คืน raw binary
     * @return string HMAC signature (hex หรือ binary)
     */
    public function hmacSign(
        string|array $data,
        ?string $key = null,
        string $algorithm = self::HMAC_DEFAULT_ALGO,
        bool $binary = false,
    ): string {
        $this->assertHmacAlgorithm($algorithm);

        $payload = is_array($data)
            ? json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
            : $data;

        $resolvedKey = $this->resolveAppKey($key);

        return hash_hmac($algorithm, $payload, $resolvedKey, $binary);
    }

    /**
     * ตรวจสอบ HMAC signature (timing-safe)
     *
     * @param  string|array  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  string  $signature  signature ที่ต้องเปรียบเทียบ
     * @param  string|null  $key  กุญแจ (null = ใช้ APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (ต้องตรงกับตอน sign)
     * @param  bool  $binary  true = เปรียบเทียบแบบ binary
     * @return bool true ถ้า signature ถูกต้อง
     */
    public function hmacVerify(
        string|array $data,
        string $signature,
        ?string $key = null,
        string $algorithm = self::HMAC_DEFAULT_ALGO,
        bool $binary = false,
    ): bool {
        return hash_equals(
            $this->hmacSign($data, $key, $algorithm, $binary),
            $signature,
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Signature Hash
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง signature hash จาก mixed data
     *
     * @param  mixed  $data  ข้อมูล (non-string → json_encode)
     * @param  bool  $useDoubleSalt  true = double-salt, false = standard hash
     * @param  string  $algorithm  hash algorithm สำหรับ standard mode
     * @return string signature hash hex string
     */
    public function signatureHash(mixed $data = '', bool $useDoubleSalt = true, string $algorithm = self::DEFAULT_ALGO): string
    {
        $stringData = $this->normalizeData($data);

        return $useDoubleSalt
            ? $this->hashWithDoubleSalt($stringData)
            : $this->hash($stringData, $algorithm);
    }

    /**
     * ตรวจสอบ signature hash (timing-safe)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string  $signature  signature ที่ต้องเปรียบเทียบ
     * @param  bool  $useDoubleSalt  ใช้ double-salt หรือไม่
     * @param  string  $algorithm  hash algorithm สำหรับ standard mode
     * @return bool true ถ้าตรงกัน
     */
    public function verifySignatureHash(
        mixed $data = '',
        string $signature = '',
        bool $useDoubleSalt = true,
        string $algorithm = self::DEFAULT_ALGO,
    ): bool {
        if ($signature === '') {
            return false;
        }

        return hash_equals(
            $this->signatureHash($data, $useDoubleSalt, $algorithm),
            $signature,
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Streaming / Incremental Hash
    // ═══════════════════════════════════════════════════════════

    /**
     * Hash จาก resource/stream — ไม่โหลดทั้งไฟล์เข้า memory
     *
     * เหมาะสำหรับ: ไฟล์ขนาดใหญ่, network stream, php://input
     *
     * @param  resource  $stream  readable stream resource
     * @param  string  $algorithm  hash algorithm (default: sha256)
     * @param  int  $chunkSize  ขนาด chunk ที่อ่านแต่ละรอบ (bytes, default: 8KB)
     * @return string hash hex string
     *
     * @throws InvalidArgumentException ถ้า stream ไม่ถูกต้อง
     */
    public function hashStream($stream, string $algorithm = 'sha256', int $chunkSize = 8192): string
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('ต้องเป็น stream resource ที่ valid');
        }

        $this->assertAlgorithm($algorithm);

        $ctx = hash_init($algorithm);

        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false) {
                throw new RuntimeException('อ่าน stream ล้มเหลว');
            }

            hash_update($ctx, $chunk);
        }

        return hash_final($ctx);
    }

    /**
     * Hash จาก HMAC stream — incremental HMAC สำหรับ stream ขนาดใหญ่
     *
     * @param  resource  $stream  readable stream resource
     * @param  string|null  $key  HMAC key (null = APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (default: sha256)
     * @param  int  $chunkSize  ขนาด chunk (bytes)
     * @return string HMAC hex string
     */
    public function hmacStream($stream, ?string $key = null, string $algorithm = 'sha256', int $chunkSize = 8192): string
    {
        if (! is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('ต้องเป็น stream resource ที่ valid');
        }

        $this->assertHmacAlgorithm($algorithm);
        $resolvedKey = $this->resolveAppKey($key);

        $ctx = hash_init($algorithm, HASH_HMAC, $resolvedKey);

        while (! feof($stream)) {
            $chunk = fread($stream, $chunkSize);

            if ($chunk === false) {
                throw new RuntimeException('อ่าน stream ล้มเหลว');
            }

            hash_update($ctx, $chunk);
        }

        return hash_final($ctx);
    }

    /**
     * Hash จาก array ของ chunks (incremental)
     *
     * เหมาะสำหรับ: รวม hash จากหลาย source โดยไม่ต้อง concat string ก่อน
     *
     * @param  iterable<string>  $chunks  ข้อมูลแต่ละ chunk
     * @param  string  $algorithm  hash algorithm (default: sha256)
     * @return string hash hex string
     */
    public function hashChunked(iterable $chunks, string $algorithm = 'sha256'): string
    {
        $this->assertAlgorithm($algorithm);

        $ctx = hash_init($algorithm);

        foreach ($chunks as $chunk) {
            hash_update($ctx, $chunk);
        }

        return hash_final($ctx);
    }

    // ═══════════════════════════════════════════════════════════
    //  File Checksum
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง checksum ของไฟล์
     *
     * @param  string  $filePath  path ของไฟล์
     * @param  string  $algorithm  hash algorithm (default: sha256)
     * @return string checksum hex string
     *
     * @throws RuntimeException ถ้าไฟล์ไม่มีหรืออ่านไม่ได้
     */
    public function fileChecksum(string $filePath, string $algorithm = 'sha256'): string
    {
        $this->assertAlgorithm($algorithm);

        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("ไม่สามารถอ่านไฟล์: {$filePath}");
        }

        $hash = hash_file($algorithm, $filePath);

        if ($hash === false) {
            throw new RuntimeException("hash_file ล้มเหลว: {$filePath}");
        }

        return $hash;
    }

    /**
     * ตรวจสอบ checksum ของไฟล์ (timing-safe)
     *
     * @param  string  $filePath  path ของไฟล์
     * @param  string  $expectedChecksum  checksum ที่คาดหวัง
     * @param  string  $algorithm  hash algorithm (ต้องตรงกับตอน hash)
     * @return bool true ถ้าตรงกัน
     */
    public function verifyFileChecksum(string $filePath, string $expectedChecksum, string $algorithm = 'sha256'): bool
    {
        return hash_equals(
            $this->fileChecksum($filePath, $algorithm),
            $expectedChecksum,
        );
    }

    /**
     * สร้าง HMAC checksum ของไฟล์ (keyed — ป้องกันสร้า checksum ปลอม)
     *
     * @param  string  $filePath  path ของไฟล์
     * @param  string|null  $key  HMAC key (null = APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (default: sha256)
     * @return string HMAC hex string
     */
    public function fileHmac(string $filePath, ?string $key = null, string $algorithm = 'sha256'): string
    {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException("ไม่สามารถอ่านไฟล์: {$filePath}");
        }

        $stream = fopen($filePath, 'rb');

        if ($stream === false) {
            throw new RuntimeException("เปิดไฟล์ไม่ได้: {$filePath}");
        }

        try {
            return $this->hmacStream($stream, $key, $algorithm);
        } finally {
            fclose($stream);
        }
    }

    /**
     * ตรวจสอบ HMAC checksum ของไฟล์ (timing-safe)
     */
    public function verifyFileHmac(string $filePath, string $expectedHmac, ?string $key = null, string $algorithm = 'sha256'): bool
    {
        return hash_equals(
            $this->fileHmac($filePath, $key, $algorithm),
            $expectedHmac,
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Content Fingerprint
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง deterministic fingerprint จาก data
     *
     * เหมาะสำหรับ: cache key, deduplication, content addressing, ETag
     * JSON keys จะถูก sort → ลำดับ key ไม่มีผล
     *
     * @param  mixed  $data  ข้อมูล (string, array, object)
     * @param  string  $algorithm  hash algorithm (default: sha256)
     * @param  int  $length  ตัดผลลัพธ์ให้สั้นลง (0 = ไม่ตัด)
     * @return string fingerprint hex string
     */
    public function fingerprint(mixed $data, string $algorithm = 'sha256', int $length = 0): string
    {
        if (is_array($data) || is_object($data)) {
            $data = $this->canonicalize($data);
        } else {
            $data = (string) $data;
        }

        $hash = $this->hash($data, $algorithm);

        return $length > 0 ? substr($hash, 0, $length) : $hash;
    }

    // ═══════════════════════════════════════════════════════════
    //  HKDF (Hash-based Key Derivation — RFC 5869)
    // ═══════════════════════════════════════════════════════════

    /**
     * HKDF — derive key จาก input key material
     *
     * ใช้เมื่อ: มี key material ที่ดีอยู่แล้ว (เช่น shared secret จาก DH)
     * แต่ต้องการ derive sub-keys หลายตัวจากมัน
     *
     * ⚠️ ไม่เหมาะสำหรับ password → ใช้ PBKDF2 หรือ Argon2 แทน
     *
     * @param  string  $inputKeyMaterial  key material ต้นทาง
     * @param  int  $length  ความยาว output key (bytes)
     * @param  string  $info  context/application-specific info (แยก key ตาม purpose)
     * @param  string  $salt  salt (empty = ใช้ zero-filled)
     * @param  string  $algorithm  hash algorithm (default: sha256)
     * @return string derived key (raw binary)
     */
    public function hkdf(
        string $inputKeyMaterial,
        int $length = 32,
        string $info = '',
        string $salt = '',
        string $algorithm = 'sha256',
    ): string {
        $this->assertAlgorithm($algorithm);

        $derived = hash_hkdf($algorithm, $inputKeyMaterial, $length, $info, $salt);

        if ($derived === false) {
            throw new RuntimeException('HKDF derivation ล้มเหลว');
        }

        return $derived;
    }

    // ═══════════════════════════════════════════════════════════
    //  Utility
    // ═══════════════════════════════════════════════════════════

    /**
     * เปรียบเทียบ hash แบบ timing-safe
     *
     * ใช้ hash_equals() — ใช้เวลาเท่ากันไม่ว่า match หรือไม่
     * ป้องกัน attacker วัดเวลาเพื่อเดา hash ทีละ byte
     *
     * @param  string  $known  hash ที่รู้ค่า (expected)
     * @param  string  $user  hash จาก user (actual)
     * @return bool true ถ้าเท่ากัน
     */
    public function equals(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }

    /**
     * รายการ hash algorithms ที่ระบบรองรับ
     *
     * @return string[]
     */
    public function getAvailableAlgorithms(): array
    {
        return hash_algos();
    }

    /**
     * รายการ HMAC algorithms ที่ระบบรองรับ
     *
     * @return string[]
     */
    public function getAvailableHmacAlgorithms(): array
    {
        return hash_hmac_algos();
    }

    /**
     * ตรวจว่า hash algorithm รองรับหรือไม่
     */
    public function isAlgorithmSupported(string $algorithm): bool
    {
        return in_array($algorithm, hash_algos(), true);
    }

    /**
     * ดู output length ของ algorithm (hex chars)
     *
     * @return int จำนวน hex characters ที่ algorithm คืน
     */
    public function getHashLength(string $algorithm): int
    {
        $this->assertAlgorithm($algorithm);

        return strlen(hash($algorithm, ''));
    }

    // ─── Private ────────────────────────────────────────────────
    // หมายเหตุ: parseKey() มาจาก ParsesEncryptionKey trait

    /**
     * Resolve APP_KEY — ใช้ key ที่ให้มา หรือ fallback เป็น APP_KEY
     */
    private function resolveAppKey(?string $key): string
    {
        $resolved = $key !== null ? $this->parseKey($key) : $this->appKey;

        if ($resolved === '') {
            throw new InvalidArgumentException(
                'Key is required. Set APP_KEY in .env or pass a key explicitly.'
            );
        }

        return $resolved;
    }

    /**
     * Resolve double-salt keys จาก config
     *
     * @return array{0: string, 1: string}
     *
     * @throws RuntimeException ถ้า production ไม่ได้กำหนด keys
     */
    private function resolveSaltKeys(): array
    {
        $key1 = $this->saltKey1;
        $key2 = $this->saltKey2;

        if (app()->isProduction() && ($key1 === null || $key2 === null)) {
            throw new RuntimeException('HASH_SALT_KEY1 and HASH_SALT_KEY2 must be set in .env for production');
        }

        return [
            $key1 ?? 'dev-key-1-change-in-production',
            $key2 ?? 'dev-key-2-change-in-production',
        ];
    }

    /**
     * Normalize mixed data → string (สำหรับ hashing)
     */
    private function normalizeData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * Canonicalize data — sort keys recursively สำหรับ deterministic hash
     */
    private function canonicalize(mixed $data): string
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            ksort($data);

            foreach ($data as &$value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_decode($this->canonicalize($value), true);
                }
            }
            unset($value);
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * ตรวจว่า hash algorithm ใช้ได้
     */
    private function assertAlgorithm(string $algorithm): void
    {
        if (! in_array($algorithm, hash_algos(), true)) {
            throw new InvalidArgumentException("Hash algorithm ไม่รองรับ: {$algorithm}");
        }
    }

    /**
     * ตรวจว่า HMAC algorithm ใช้ได้
     */
    private function assertHmacAlgorithm(string $algorithm): void
    {
        if (! in_array($algorithm, hash_hmac_algos(), true)) {
            throw new InvalidArgumentException("HMAC algorithm ไม่รองรับ: {$algorithm}");
        }
    }
}
