<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Enums\ArgonLevel;
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

    private const DEFAULT_ALGO = 'sha3-256';

    private const HMAC_DEFAULT_ALGO = 'sha3-256'; // sha256  sha3-384

    private const SALT_LENGTH = 16;

    private const AES_KEY_LENGTH = 32;

    /** @var string|null กุญแจหลักแอปพลิเคชัน — ไม่ใช้ readonly เพื่อให้ memzero ใน __destruct ทำงานได้ */
    private ?string $appKey;

    private readonly string $saltKey1;

    private readonly string $saltKey2;

    public function __construct()
    {
        $rawKeyVal = config('app.key', '');
        $rawKey = is_scalar($rawKeyVal) ? (string) $rawKeyVal : '';
        $this->appKey = $this->parseKey($rawKey);

        $sKey1 = config('core.base::security.hash_salt.key1');
        $this->saltKey1 = is_scalar($sKey1) ? (string) $sKey1 : '';

        $sKey2 = config('core.base::security.hash_salt.key2');
        $this->saltKey2 = is_scalar($sKey2) ? (string) $sKey2 : '';
    }

    /**
     * ล้างข้อมูลกุญแจออกจากหน่วยความจำเมื่อทำลาย Object
     */
    public function __destruct()
    {
        if ($this->appKey !== null) {
            \sodium_memzero($this->appKey);
            $this->appKey = null;
        }
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

    /**
     * สร้างกุญแจย่อยจาก Master Key ด้วย BLAKE2b KDF
     *
     * @param  string  $context  บริบทการใช้งาน (ตัดหรือ pad ให้เป็น 8 bytes อัตโนมัติ)
     * @param  int  $subkeyId  รหัสกุญแจย่อย (0–4294967295)
     * @param  string|null  $inputKeyMaterial  กุญแจหลัก (null = ใช้ config masterkey)
     * @param  bool  $useBinary  ส่งคืนเป็น Base64 หรือ raw binary
     * @return string กุญแจย่อย (binary หรือ Base64)
     *
     * @throws RuntimeException เมื่อ inputKeyMaterial ว่าง หรือขนาดกุญแจไม่ถูกต้อง
     */
    public function deriveKey(string $context = 'default_', int $subkeyId = 0, ?string $inputKeyMaterial = null, bool $useBinary = false): string
    {
        $ikm = $this->resolveKey($inputKeyMaterial, 32);
        // เช็ค $context  ต้องมีขนาด  8 ตัวอักษรเท่านั้น
        if (strlen($context) !== SODIUM_CRYPTO_KDF_CONTEXTBYTES) {
            throw new RuntimeException('deriveKey: context ต้องมีขนาด '.SODIUM_CRYPTO_KDF_CONTEXTBYTES.' ตัวอักษรเท่านั้น');
        }

        if (empty($ikm)) {
            $masterKeyRaw = config('core.base::security.masterkey', '');
            $ikm = is_scalar($masterKeyRaw) ? (string) $masterKeyRaw : '';
        }

        if (empty($ikm)) {
            throw new RuntimeException('deriveKey: inputKeyMaterial ไม่สามารถเว้นว่างได้');
        }

        // Context ต้องเป็นขนาด SODIUM_CRYPTO_KDF_CONTEXTBYTES (8 bytes) พอดี
        $ctx = \str_pad(\substr($context, 0, SODIUM_CRYPTO_KDF_CONTEXTBYTES), SODIUM_CRYPTO_KDF_CONTEXTBYTES, "\0");

        // Key ต้องเป็นขนาด SODIUM_CRYPTO_KDF_KEYBYTES (32 bytes) — parse base64 ก่อนถ้าจำเป็น
        if (\strlen($ikm) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            $parsed = $this->parseKey($ikm);
            if ($parsed !== null && \strlen($parsed) === SODIUM_CRYPTO_KDF_KEYBYTES) {
                $ikm = $parsed;
            } else {
                // Normalize ด้วย HKDF ให้ได้ 32 bytes
                $ikm = hash_hkdf('sha3-256', $ikm, SODIUM_CRYPTO_KDF_KEYBYTES, 'kdf-key-normalize');
            }
        }

        $binaryKey = \sodium_crypto_kdf_derive_from_key(self::AES_KEY_LENGTH, $subkeyId, $ctx, $ikm);

        return self::encodeKey($binaryKey, $useBinary);
    }

    /**
     * ตรวจสอบว่า Derived Key นี้มาจาก Master Key เดิมหรือไม่
     */
    public function verifyDerivedKey(string $providedDerivedKey, string $context, int $subkeyId, string $masterKey = '', bool $useBinary = false): bool
    {
        // สร้าง Derived Key ใหม่จาก Master Key
        $computedKey = $this->deriveKey($context, $subkeyId, $masterKey, $useBinary);

        // เปรียบเทียบแบบปลอดภัย (timing attack safe)
        return hash_equals($computedKey, $providedDerivedKey);
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
     * สร้างกุญแจย่อยแบบ Password-Based ด้วย Argon2id + HKDF + HMAC
     *
     * กระบวนการ 3 ขั้นตอน:
     *   1. Argon2id KDF — แปลง masterKey + salt → raw key (ป้องกัน brute-force)
     *   2. HKDF — อนุมานกุญแจใหม่พร้อม domain separation ด้วย context
     *   3. HMAC-SHA3-256 — binding กุญแจกับ salt เพื่อความสมบูรณ์
     *
     * @param  string  $context  บริบทการใช้งาน (Domain Separation Label)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64
     * @param  string  $masterKey  รหัสผ่าน / master key ต้นทาง
     * @return string กุญแจย่อย (raw binary หรือ Base64)
     *
     * @throws RuntimeException เมื่อ masterKey หรือ salt ไม่ถูกต้อง
     */
    public function passwordForSafe(string $context = 'default', string $saltb64 = '', string $masterKey = '', bool $useBinary = false): string
    {
        $rawSalt = $saltb64 !== '' ? $this->resolveKey($saltb64, 32) : '';
        $rawSalt = ($rawSalt !== false) ? $rawSalt : '';

        // 1. Argon2id — แปลง masterKey → raw binary key (ป้องกัน brute-force)
        $argonKey = $this->deriveKeyFromPassword($masterKey, $saltb64, $useBinary);

        // 2. HKDF — domain separation ด้วย context
        $hkdfKey = $this->hkdf($argonKey, 32, $context, $rawSalt);

        // 3. HMAC-SHA3-256 — binding กุญแจกับ salt
        $hmac = hash_hmac('sha3-256', $hkdfKey, $rawSalt, true);

        return self::encodeKey($hmac, $useBinary);
    }

    /**
     * ตรวจสอบกุญแจย่อยว่ามาจาก Master Key เดิมหรือไม่ (timing-safe)
     *
     * @param  string  $providedDerivedKey  กุญแจย่อยที่ต้องการตรวจสอบ
     * @param  string  $context  บริบทการใช้งาน (ต้องตรงกับตอนสร้าง)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64 (ต้องตรงกับตอนสร้าง)
     * @param  string  $masterKey  รหัสผ่าน / master key ต้นทาง
     * @param  bool  $useBinary  กุญแจที่ให้มาเป็น Base64 หรือไม่
     * @return bool true ถ้ากุญแจถูกต้อง
     */
    public function verifyPasswordForSafe(string $providedDerivedKey, string $context = 'default', string $saltb64 = '', string $masterKey = '', bool $useBinary = false): bool
    {
        $computedKey = $this->passwordForSafe($context, $saltb64, $masterKey, $useBinary);

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

        $charListRaw = preg_split('//u', $usedChars, -1, PREG_SPLIT_NO_EMPTY);
        $charList = is_array($charListRaw) ? $charListRaw : [];
        $charCount = count($charList);

        if ($charCount === 0) {
            throw new RuntimeException('randomString: ไม่มีอักขระสำหรับสร้าง string');
        }

        $results = [];
        for ($c = 0; $c < $count; $c++) {
            $str = '';
            for ($i = 0; $i < $length; $i++) {
                /** @var string $char */
                $char = $charList[random_int(0, $charCount - 1)];
                $str .= $char;
            }
            $results[] = $str;
        }

        return $count === 1 ? (string) $results[0] : $results;
    }

    /**
     * Hash รหัสผ่าน (Argon2id)
     */
    public function pwhash(string $password, ArgonLevel $level = ArgonLevel::Interactive): string
    {
        [$opsLimit, $memLimit] = $level->params();

        return \sodium_crypto_pwhash_str($password, $opsLimit, $memLimit);
    }

    public function pwhashVerify(string $hash, string $password): bool
    {
        return \sodium_crypto_pwhash_str_verify($hash, $password);
    }

    /**
     * ตรวจสอบว่าต้อง Rehash หรือไม่ (เมื่อ level เปลี่ยน หรือพารามิเตอร์ถูก upgrade)
     */
    public function pwhashNeedsRehash(string $hash, ArgonLevel $level = ArgonLevel::Interactive): bool
    {
        [$opsLimit, $memLimit] = $level->params();

        return \sodium_crypto_pwhash_str_needs_rehash($hash, $opsLimit, $memLimit);
    }

    protected function getAppKey(): string
    {
        return $this->resolveAppKey();
    }

    // ─── Private ────────────────────────────────────────────────
    // หมายเหตุ: parseKey() มาจาก ParsesEncryptionKey trait

    /**
     * Resolve APP_KEY — ใช้ key ที่ให้มา หรือ fallback เป็น APP_KEY
     */
    private function resolveAppKey(?string $key = null): string
    {
        $resolved = $key !== null ? $this->parseKey($key) : $this->appKey;

        if ($resolved === null || $resolved === '') {
            throw new RuntimeException('Hash key is missing or invalid');
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

        if (app()->isProduction() && ($key1 === '' || $key2 === '')) {
            throw new RuntimeException('HASH_SALT_KEY1 and HASH_SALT_KEY2 must be set in .env for production');
        }

        return [
            $key1 !== '' ? $key1 : 'dev-key-1-change-in-production',
            $key2 !== '' ? $key2 : 'dev-key-2-change-in-production',
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
