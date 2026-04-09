<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface;
use Core\Base\Support\Helpers\Crypto\Contracts\KeyDerivationInterface;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use RuntimeException;
use Throwable;

/**
 * HashHelper — Hashing Helper ที่สมบูรณ์ครบวงจร
 *
 * ครอบคลุมทุก use case ของ hashing รวมถึง password (Argon2id via Sodium)
 *
 * ─── Standard Hash ──────────────────────────────────────
 *  hash()              — hash string/mixed data ด้วย algorithm ที่เลือก
 *  verifyHash()        — ตรวจสอบ hash (timing-safe)
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
final class HashHelper implements HashHelperInterface, KeyDerivationInterface
{
    use DataNormalization, ParsesEncryptionKey;

    public const string PWHASH_INTERACTIVE = 'interactive';

    /** MODERATE — เหมาะสำหรับข้อมูล sensitive (~256 MB RAM, ~1 s) */
    public const string PWHASH_MODERATE = 'moderate';

    /** SENSITIVE — ความปลอดภัยสูงสุด (~1 GB RAM, ~5 s) */
    public const string PWHASH_SENSITIVE = 'sensitive';

    private const DEFAULT_ALGO = 'sha3-256';

    private const HMAC_DEFAULT_ALGO = 'sha3-256'; // sha256  sha3-384

    private const SALT_LENGTH = 16;

    private const AES_KEY_LENGTH = 32;

    private readonly string $appKey;

    private readonly ?string $saltKey1;

    private readonly ?string $saltKey2;

    public function __construct()
    {
        $rawKey = (string) config('app.key', '');
        $this->appKey = $this->parseKey($rawKey);
        $this->saltKey1 = config('core.base::security.hash_salt.key1');
        $this->saltKey2 = config('core.base::security.hash_salt.key2');
    }

    /**
     * คืน [opslimit, memlimit] ตาม Argon2id level
     *
     * @return array{0: int, 1: int}
     *
     * @throws InvalidArgumentException เมื่อ level ไม่รองรับ
     */
    private static function pwhashParams(string $level): array
    {
        return match ($level) {
            self::PWHASH_INTERACTIVE => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,
            ],
            self::PWHASH_MODERATE => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE,
            ],
            self::PWHASH_SENSITIVE => [
                SODIUM_CRYPTO_PWHASH_OPSLIMIT_SENSITIVE,
                SODIUM_CRYPTO_PWHASH_MEMLIMIT_SENSITIVE,
            ],
            default => throw new InvalidArgumentException(
                "Argon2id level ไม่รองรับ: '{$level}' — ใช้ PWHASH_INTERACTIVE, PWHASH_MODERATE, หรือ PWHASH_SENSITIVE",
            ),
        };
    }

    /**
     * ล้างข้อมูลกุญแจออกจากหน่วยความจำเมื่อทำลาย Object
     */
    public function __destruct()
    {
        $key = $this->appKey;
        self::memzero($key);
    }

    // ═══════════════════════════════════════════════════════════
    //  Standard Hash
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash จาก string
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการ hash
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @param  bool  $binary  true = คืน raw binary แทน hex
     * @return string hash (hex หรือ binary)
     */
    public function hash(mixed $data, string $algorithm = self::DEFAULT_ALGO, bool $binary = false): string
    {
        $this->assertAlgorithm($algorithm);
        $data = $this->normalizeData($data);

        return hash($algorithm, $data, $binary);
    }

    /**
     * ตรวจสอบ hash (timing-safe)
     *
     * @param  mixed  $data  ข้อมูลต้นทาง
     * @param  string  $hash  hash ที่ต้องการตรวจสอบ
     * @param  string  $algorithm  hash algorithm (ต้องตรงกับตอน hash)
     * @param  bool  $binary  true = hash เป็น binary
     * @return bool true ถ้าตรงกัน
     */
    public function verifyHash(mixed $data, string $hash, string $algorithm = self::DEFAULT_ALGO, bool $binary = false): bool
    {
        $this->assertAlgorithm($algorithm);
        $data = $this->normalizeData($data);

        return hash_equals($hash, hash($algorithm, $data, $binary));
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
     * @param  mixed  $data  ข้อมูลที่ต้องการ hash
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @return string "hex_hash:hex_salt"
     */
    public function hashWithSalt(mixed $data, string $algorithm = self::DEFAULT_ALGO): string
    {
        $this->assertHmacAlgorithm($algorithm);

        $salt = random_bytes(self::SALT_LENGTH);
        $data = $this->normalizeData($data);
        $hash = hash_hmac($algorithm, $data, $salt);

        return $hash.':'.bin2hex($salt);
    }

    /**
     * ตรวจสอบ salted hash (timing-safe)
     *
     * @param  mixed  $data  ข้อมูลต้นทาง
     * @param  string  $saltedHash  hash ในรูปแบบ "hex_hash:hex_salt"
     * @param  string  $algorithm  hash algorithm (ต้องตรงกับตอน hash)
     * @return bool true ถ้าตรงกัน
     */
    public function verifySaltedHash(mixed $data, string $saltedHash, string $algorithm = self::DEFAULT_ALGO): bool
    {
        $this->assertHmacAlgorithm($algorithm);
        $data = $this->normalizeData($data);
        $parts = explode(':', $saltedHash, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$expectedHash, $saltHex] = $parts;

        $salt = hex2bin($saltHex);

        if ($salt === false) {
            return false;
        }

        $actualHash = hash_hmac($algorithm, $data, $salt);

        return hash_equals($expectedHash, $actualHash);
    }

    // ═══════════════════════════════════════════════════════════
    //  Double-Salt Hash (SHA256 + SHA3-256 layered)
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง hash แบบ one-way ด้วย double keyed HMAC (nested HMAC)
     *
     * กระบวนการ:
     *   1. HMAC-SHA3-256(input, key1) → layer1
     *   2. HMAC-SHA3-256(layer1, key2) → final
     *
     * เหมาะสำหรับ: hash ข้อมูลที่ต้องการความปลอดภัยสูง (เช่น sensitive identifier)
     * ต้องกำหนด HASH_SALT_KEY1 + HASH_SALT_KEY2 ใน .env สำหรับ production
     *
     * @param  string  $input  ข้อความที่ต้องการ hash (empty → คืน empty)
     * @return string SHA3-256 hex hash หรือ empty string
     *
     * @throws RuntimeException ถ้า production ไม่ได้กำหนด keys
     */
    public function hashWithDoubleSalt(mixed $input): string
    {
        if ($input === '' || $input === null) {
            throw new InvalidArgumentException('hashWithDoubleSalt: input ต้องไม่เป็นค่าว่าง');
        }
        $input = $this->normalizeData($input);

        [$key1, $key2] = $this->resolveSaltKeys();

        $layer1 = hash_hmac(self::DEFAULT_ALGO, $input, $key1);

        return hash_hmac(self::DEFAULT_ALGO, $layer1, $key2);
    }

    /**
     * ตรวจสอบ double-salt hash (timing-safe)
     *
     * @param  mixed  $input  ข้อความต้นทาง
     * @param  string  $expectedHash  hash ที่คาดหวัง
     * @return bool true ถ้าตรงกัน
     */
    public function verifyDoubleSalt(mixed $input, string $expectedHash): bool
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
     * @param  array|string  $data  ข้อมูล (array → json_encode อัตโนมัติ)
     * @param  string|null  $key  กุญแจ HMAC (null = ใช้ APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (default: sha256)
     * @param  bool  $binary  true = คืน raw binary
     * @return string HMAC signature (hex หรือ binary)
     */
    public function hmacSign(
        mixed $data,
        ?string $key = null,
        string $algorithm = self::HMAC_DEFAULT_ALGO,
        bool $binary = false,
    ): string {
        $this->assertHmacAlgorithm($algorithm);
        $data = $this->normalizeData($data);
        $resolvedKey = $this->resolveAppKey($key);

        return hash_hmac($algorithm, $data, $resolvedKey, $binary);
    }

    /**
     * ตรวจสอบ HMAC signature (timing-safe)
     *
     * @param  array|string  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  string  $signature  signature ที่ต้องเปรียบเทียบ
     * @param  string|null  $key  กุญแจ (null = ใช้ APP_KEY)
     * @param  string  $algorithm  HMAC algorithm (ต้องตรงกับตอน sign)
     * @param  bool  $binary  true = เปรียบเทียบแบบ binary
     * @return bool true ถ้า signature ถูกต้อง
     */
    public function hmacVerify(
        mixed $data,
        string $signature,
        ?string $key = null,
        string $algorithm = self::HMAC_DEFAULT_ALGO,
        bool $binary = false,
    ): bool {
        if ($signature === '') {
            return false;
        }

        return hash_equals(
            $this->hmacSign($data, $key, $algorithm, $binary),
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
    public function hashStream($stream, string $algorithm = self::DEFAULT_ALGO, int $chunkSize = 8192): string
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
    public function hmacStream($stream, ?string $key = null, string $algorithm = self::DEFAULT_ALGO, int $chunkSize = 8192): string
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
    public function hashChunked(iterable $chunks, string $algorithm = self::DEFAULT_ALGO): string
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
    public function fileChecksum(string $filePath, string $algorithm = self::DEFAULT_ALGO): string
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
    public function verifyFileChecksum(string $filePath, string $expectedChecksum, string $algorithm = self::DEFAULT_ALGO): bool
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
    public function fileHmac(string $filePath, ?string $key = null, string $algorithm = self::DEFAULT_ALGO): string
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
    public function verifyFileHmac(string $filePath, string $expectedHmac, ?string $key = null, string $algorithm = self::DEFAULT_ALGO): bool
    {
        return hash_equals(
            $this->fileHmac($filePath, $key, $algorithm),
            $expectedHmac,
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  HKDF (Hash-based Key Derivation — RFC 5869)
    // ═══════════════════════════════════════════════════════════
    public function deriveKeyFromPassword(string $inputPassword, string $saltb64, bool $isBase64 = true, bool $urlSafe = false): string
    {
        $salt = $this->decodeb64($saltb64);
        if (strlen($salt) !== SODIUM_CRYPTO_PWHASH_SALTBYTES) {
            throw new RuntimeException(
                'Salt must be exactly '.SODIUM_CRYPTO_PWHASH_SALTBYTES.' bytes',
            );
        }
        if (empty($inputPassword)) {
            throw new RuntimeException(
                'InputPassword must not be empty',
            );
        }

        $key = sodium_crypto_pwhash(
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,         // ความยาวกุญแจที่ต้องการ (เช่น 32 bytes สำหรับ AES-256)
            $inputPassword,     // รหัสผ่านจากผู้ใช้
            $salt,         // Salt ขนาด 16 bytes (SODIUM_CRYPTO_PWHASH_SALTBYTES)
            SODIUM_CRYPTO_PWHASH_OPSLIMIT_INTERACTIVE,        // จำนวนรอบการประมวลผล
            SODIUM_CRYPTO_PWHASH_MEMLIMIT_INTERACTIVE,        // จำนวน RAM ที่ใช้ (หน่วยเป็น Bytes)
            SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13,             // เลือก SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13
        );

        return $this->maybeBase64($key, $isBase64, $urlSafe);
        // password_hash() ใช้ Argon2id เป็นค่า default
        // ซึ่งเป็น algorithm ที่แนะนำสำหรับ password hashing
        // return password_hash($password, PASSWORD_ARGON2ID, ['salt' => $salt]);
    }

    public function verifyDerivedKeyFromPassword(string $inputPassword, string $storedSaltb64, string $storedKey): bool
    {
        $derivedKey = $this->deriveKeyFromPassword($inputPassword, $storedSaltb64);

        // ✅ ใช้ hash_equals() — ป้องกัน timing attack
        return hash_equals($storedKey, $derivedKey);
    }

    /**
     * สร้างกุญแจย่อยจาก Master Key โดยใช้ HKDF
     *
     * @param  string  $context  บริบทการใช้งาน (Domain Separation)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64
     * @param  string|null  $inputKeyMaterial  Input Key Material (ถ้าเป็น null จะใช้ config('core.base::security.masterkey'))
     * @param  bool  $isBase64  ส่งคืนค่าเป็น Base64 หรือไม่
     * @param  bool  $urlSafe  ใช้ URL-safe Base64 หรือไม่
     * @return string กุญแจย่อยที่สร้างขึ้น
     *
     * @throws RuntimeException ถ้า inputKeyMaterial หรือ salt เป็นค่าว่าง
     */
    public function deriveKey(string $context = 'default', string $saltb64 = '', ?string $inputKeyMaterial = null, bool $isBase64 = true, bool $urlSafe = false): string
    {
        if (empty($inputKeyMaterial)) {
            $inputKeyMaterial = config('core.base::security.masterkey', '');
        }
        if (empty($inputKeyMaterial)) {
            throw new RuntimeException('Invalid inputKeyMaterial string.');
        }
        // ถ้าไม่ส่ง salt ให้ใช้ empty string (hash_hkdf รองรับ salt ว่าง)
        // แต่ถ้าส่ง salt มาแล้ว decode ไม่ได้ → throw
        if ($saltb64 !== '') {
            $decoded = $this->decodeb64($saltb64);
            if ($decoded === false || $decoded === '') {
                throw new RuntimeException('Invalid salt string. in '.__FUNCTION__);
            }
            $salt = $decoded;
        } else {
            $salt = '';
        }

        // 1. ตรวจสอบความถูกต้องของ Algorithm
        $this->assertHmacAlgorithm(self::HMAC_DEFAULT_ALGO);
        // 2. ใช้ hash_hkdf เพื่อสร้างกุญแจย่อย
        // การใส่ $context (Info) จะทำให้กุญแจแต่ละประเภทแยกออกจากกันเด็ดขาด (Domain Separation)
        $binaryKey = hash_hkdf(self::HMAC_DEFAULT_ALGO, $inputKeyMaterial, self::AES_KEY_LENGTH, $context, $salt);

        // 3. ส่งคืนค่า (แนะนำให้ส่งเป็น Raw Binary เพื่อนำไปใช้ต่อใน openssl_encrypt ได้ทันที)
        return $this->maybeBase64($binaryKey, $isBase64, $urlSafe);
    }

    // ใช้งาน native hash_hkdf
    /**
     * RFC 5869 HKDF — ใช้งาน native hash_hkdf
     */
    public function hkdf(string $ikm, int $length = 32, string $info = '', string $salt = '', string $algorithm = 'sha3-256'): string
    {
        $this->assertHmacAlgorithm($algorithm);

        return hash_hkdf($algorithm, $ikm, $length, $info, $salt);
    }

    /**
     * ตรวจสอบว่า Derived Key นี้มาจาก Master Key เดิมหรือไม่
     */
    public function verifyDerivedKey(string $providedDerivedKey, string $purpose, string $saltb64 = '', string $masterKey = ''): bool
    {
        // สร้าง Derived Key ใหม่จาก Master Key
        $computedKey = $this->deriveKey($purpose, $saltb64, $masterKey);

        // เปรียบเทียบแบบปลอดภัย (timing attack safe)
        return hash_equals($computedKey, $providedDerivedKey);
    }

    /**
     * สร้างกุญแจย่อยจาก Master Key โดยใช้ HKDF
     *
     * @param  string  $context  บริบทการใช้งาน (Domain Separation)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64
     * @param  bool  $isBase64  ส่งคืนค่าเป็น Base64 หรือไม่
     * @param  bool  $urlSafe  ใช้ URL-safe Base64 หรือไม่
     * @return string กุญแจย่อยที่สร้างขึ้น
     *
     * @throws RuntimeException ถ้า inputKeyMaterial หรือ salt เป็นค่าว่าง
     */
    public function passwordForSafe(string $context = 'default', string $saltb64 = '', string $masterKey = '', bool $isBase64 = false, bool $urlSafe = false): string
    {
        $salt = $this->decodeb64($saltb64);
        $MasterKeyPassword = $this->deriveKeyFromPassword($masterKey, $saltb64);  // แปลงรหัสผ่าน master key ให้เป็นกุญแจ เข้ารหัส Argon2id
        $derivedKey = $this->deriveKey($context, $saltb64, $MasterKeyPassword); // สร้างกุญแจ ดอกใหม่จาก แม่กุญแจ
        if ($derivedKey === false) {
            throw new RuntimeException('Invalid derived key string.');
        }

        // สร้าง HMAC โดยใช้ SHA-256  sha3-256
        $hmac = hash_hmac('sha3-256', $derivedKey, $salt);

        return $this->maybeBase64($hmac, $isBase64, $urlSafe);
    }

    /**
     * ตรวจสอบกุญแจย่อยว่ามาจาก Master Key เดิมหรือไม่
     *
     * @param  string  $providedDerivedKey  กุญแจย่อยที่ต้องการตรวจสอบ
     * @param  string  $context  บริบทการใช้งาน (ต้องตรงกับตอนสร้าง)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64 (ต้องตรงกับตอนสร้าง)
     * @return bool true ถ้ากุญแจถูกต้อง, false ถ้าไม่ถูกต้อง
     */
    public function verifyPasswordForSafe(string $providedDerivedKey, string $context = 'default', string $saltb64 = '', string $masterKey = ''): bool
    {
        $computedKey = $this->passwordForSafe($context, $saltb64, $masterKey);

        return hash_equals($computedKey, $providedDerivedKey);
    }

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
        static $algos = null;
        $algos ??= hash_algos();

        return in_array($algorithm, $algos, true);
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

    /**
     * สร้าง ID (UUID v7 หรือ v4)
     *
     * @param  int  $version  เวอร์ชัน UUID (1-7)
     * @param  bool  $includeDash  รวมเครื่องหมายขีดหรือไม่
     */
    public function generateId(int $version = 7, bool $includeDash = true): string
    {
        try {
            $uuid = match ($version) {
                1 => Uuid::uuid1(),
                2 => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON),
                3 => Uuid::uuid3(Uuid::NAMESPACE_DNS, php_uname('n')),
                4 => Uuid::uuid4(),
                5 => Uuid::uuid5(Uuid::NAMESPACE_DNS, php_uname('n')),
                6 => Uuid::uuid6(),
                7 => Uuid::uuid7(),
                default => Uuid::uuid7(),
            };
        } catch (Throwable $e) {
            throw new RuntimeException("ไม่สามารถสร้าง UUID v{$version} ได้: {$e->getMessage()}", 0, $e);
        }

        $serialized = $uuid->toString();

        return $includeDash ? $serialized : str_replace('-', '', $serialized);
    }

    /**
     * สร้างรหัสสุ่มที่มีความปลอดภัยสูง (Cryptographically Secure Random String)
     *
     * @param  int  $length  ความยาว
     * @param  int  $count  จำนวนที่ต้องการสร้าง
     * @param  string  $characters  ชุดอักขระ (comma-separated: numbers, lower_case, upper_case)
     */
    public function randomString(
        int $length = 32,
        int $count = 1,
        string $characters = 'numbers,lower_case,upper_case,extra_password_fix2',
    ): string|array {
        $presets = [
            'lower_case' => 'abcdefghijklmnopqrstuvwxyz',
            'upper_case' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
            'numbers' => '1234567890',
            'numbers_th' => '๑๒๓๔๕๖๗๘๙๐',
            'char_th' => 'กขฃคฅฆงจฉชซฌญฎฏฐฑฒณดตถทธนบปผฝพฟภมยรลวศษสหฬอฮ',
            'special_symbols' => '!?~@#-_+<>[]{}',
            'extra_symbols' => '!?@#%[]{}',
            'extra_password' => '!@#%()_+[]{}?$*',
            'extra_password_fix' => '!@#[]{}$',
            'extra_password_fix2' => '!#()_+[]{}<>',
        ];

        $usedChars = '';
        foreach (explode(',', $characters) as $type) {
            $type = trim($type);
            $usedChars .= $presets[$type] ?? $type;
        }

        if ($usedChars === '') {
            $usedChars = $presets['lower_case'].$presets['upper_case'].$presets['numbers'];
        }

        $charList = preg_split('//u', $usedChars, -1, PREG_SPLIT_NO_EMPTY);
        $charCount = count($charList);

        $results = [];
        for ($c = 0; $c < $count; $c++) {
            $str = '';
            for ($i = 0; $i < $length; $i++) {
                $str .= $charList[random_int(0, $charCount - 1)];
            }
            $results[] = $str;
        }

        return $count === 1 ? $results[0] : $results;
    }

    /**
     * Hash รหัสผ่าน (Argon2id)
     *
     * @param  string  $password  รหัสผ่านที่ต้องการ hash
     * @param  string  $level  ระดับความปลอดภัย: PWHASH_INTERACTIVE | PWHASH_MODERATE | PWHASH_SENSITIVE
     *
     * @throws InvalidArgumentException เมื่อ level ไม่รองรับ
     */
    public function pwhash(string $password, string $level = self::PWHASH_INTERACTIVE): string
    {
        [$opsLimit, $memLimit] = self::pwhashParams($level);

        return \sodium_crypto_pwhash_str($password, $opsLimit, $memLimit);
    }

    public function pwhashVerify(string $hash, string $password): bool
    {
        return \sodium_crypto_pwhash_str_verify($hash, $password);
    }

    /**
     * ตรวจสอบว่าต้อง Rehash หรือไม่ (เมื่อ level เปลี่ยน หรือพารามิเตอร์ถูก upgrade)
     *
     * @param  string  $level  ระดับเดียวกับที่ใช้ตอน hash ปัจจุบัน
     */
    public function pwhashNeedsRehash(string $hash, string $level = self::PWHASH_INTERACTIVE): bool
    {
        [$opsLimit, $memLimit] = self::pwhashParams($level);

        return \sodium_crypto_pwhash_str_needs_rehash($hash, $opsLimit, $memLimit);
    }

    protected function getAppKey(): string
    {
        return $this->appKey;
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
                'Key is required. Set APP_KEY in .env or pass a key explicitly.',
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
     * ตรวจว่า hash algorithm ใช้ได้
     */
    private function assertAlgorithm(string $algorithm): void
    {
        static $algos = null;
        $algos ??= hash_algos();

        if (! in_array($algorithm, $algos, true)) {
            throw new InvalidArgumentException("Hash algorithm ไม่รองรับ: {$algorithm}");
        }
    }

    /**
     * ตรวจว่า HMAC algorithm ใช้ได้
     */
    private function assertHmacAlgorithm(string $algorithm): void
    {
        static $hmacAlgos = null;
        $hmacAlgos ??= hash_hmac_algos();

        if (! in_array($algorithm, $hmacAlgos, true)) {
            throw new InvalidArgumentException("HMAC algorithm ไม่รองรับ: {$algorithm}");
        }
    }
}
