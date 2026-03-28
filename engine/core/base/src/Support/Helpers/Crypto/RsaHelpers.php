<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Support\Helpers\Crypto\Contracts\RsaHelperInterface;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Crypt\RSA;
use phpseclib3\Crypt\RSA\PrivateKey;
use phpseclib3\Crypt\RSA\PublicKey;
use RuntimeException;
use Carbon\Carbon;

/**
 * RsaHelpers — RSA Encryption Helper ที่สมบูรณ์ครบวงจร (via phpseclib3)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Key Management
 * ═══════════════════════════════════════════════════════════════
 *  withKeys($priv, $pub)          — สร้าง instance ใหม่พร้อม key (immutable)
 *  getPrivateKey() / getPublicKey() — ดึง PEM key ที่ตั้งไว้
 *  generateKeyPair($bits)         — สร้าง RSA key pair (PKCS8)
 *  generateProtectedKeyPair($bits, $passphrase) — key pair + passphrase
 *  extractPublicKey($privateKey)  — แยก public key จาก private key
 *  isKeyPairMatch($priv, $pub)    — ตรวจว่า key pair เข้าคู่กัน
 *
 * ═══════════════════════════════════════════════════════════════
 *  Key Inspection
 * ═══════════════════════════════════════════════════════════════
 *  isPrivateKey($key) / isPublicKey($key) / isValidKey($key)
 *  getKeyType($key)               — "private key" | "public key"
 *  getKeyInfo($key)               — ขนาด, type, fingerprint, format
 *  getKeyFingerprint($key)        — SHA-256 fingerprint ของ key
 *  getKeySize($key)               — ขนาด key (bits)
 *  getMaxEncryptSize($key)        — ขนาดสูงสุดที่ pure RSA encrypt ได้ (bytes)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Key Format Conversion
 * ═══════════════════════════════════════════════════════════════
 *  convertToPkcs1($key)           — แปลงเป็น PKCS1 (RSA PRIVATE/PUBLIC KEY)
 *  convertToPkcs8($key)           — แปลงเป็น PKCS8 (PRIVATE/PUBLIC KEY)
 *  loadKeyFromFile($path)         — โหลด key จาก file path
 *
 * ═══════════════════════════════════════════════════════════════
 *  RSA Encrypt / Decrypt  (pure RSA, OAEP-SHA256)
 * ═══════════════════════════════════════════════════════════════
 *  encrypt($data, $pubKey)        — encrypt string → base64
 *  decrypt($encrypted, $privKey)  — decrypt base64 → string
 *  encryptData($data, $pubKey)    — encrypt mixed data (json_encode auto)
 *  decryptData($encrypted, $privKey) — decrypt → mixed data
 *
 * ═══════════════════════════════════════════════════════════════
 *  RSA Sign / Verify  (PSS-SHA256)
 * ═══════════════════════════════════════════════════════════════
 *  sign($data, $privKey)          — sign → base64 signature
 *  verifySignature($data, $sig, $pubKey) — verify signature
 *  signData($data, $privKey)      — sign mixed data
 *  verifyDataSignature($data, $sig, $pubKey) — verify mixed data signature
 *  signPayload($payload)          — sign JSON + timestamp (anti-replay)
 *  verifyPayload($signed, $maxAge) — verify signed payload + age check
 *
 * ═══════════════════════════════════════════════════════════════
 *  Hybrid Encryption  (RSA + AES-256-GCM, ไม่จำกัดขนาด)
 * ═══════════════════════════════════════════════════════════════
 *  hybridEncrypt($data, $pubKey)     — binary packed format (compact)
 *  hybridDecrypt($encrypted, $privKey) — auto-detect V1/V2
 *  hybridEncryptEnvelope($data, $pubKey) — JSON envelope format (readable)
 *  hybridDecryptEnvelope($envelope, $privKey) — decrypt JSON envelope
 *  hybridEncryptData($data, $pubKey) — hybrid encrypt mixed data
 *  hybridDecryptData($encrypted, $privKey) — hybrid decrypt → mixed
 *
 * ─── ความปลอดภัย ────────────────────────────────────────────
 *  - OAEP-SHA256 padding (encryption) — ป้องกัน chosen-ciphertext attack
 *  - PSS-SHA256 padding (signature) — probabilistic, ปลอดภัยกว่า PKCS1v15
 *  - AES-256-GCM + AAD (hybrid V2) — authenticated encryption + header integrity
 *  - sodium_memzero() ล้าง AES key หลังใช้
 *  - Key size minimum 2048 bits
 *  - Timestamp + age check ใน signPayload (anti-replay)
 */
class RsaHelpers implements RsaHelperInterface
{
    // ─── Constants ──────────────────────────────────────────────

    private const HYBRID_MAGIC_V1 = "RSAHYB\x01";

    private const HYBRID_MAGIC_V2 = "RSAHYB\x02";

    private const MIN_KEY_BITS = 2048;

    private const AES_KEY_LENGTH = 32;

    private const GCM_IV_LENGTH = 12;

    private const GCM_TAG_LENGTH = 16;

    // OAEP-SHA256 overhead = 2 * hashLen + 2 = 2 * 32 + 2 = 66 bytes
    private const OAEP_SHA256_OVERHEAD = 66;

    private readonly ?string $privateKeyPem;

    private readonly ?string $publicKeyPem;

    private readonly string $passphrase;

    public function __construct(?string $privateKeyPem = null, ?string $publicKeyPem = null)
    {
        $this->privateKeyPem = $privateKeyPem ?? config('crypto.rsa.private_key');
        $this->publicKeyPem = $publicKeyPem ?? config('crypto.rsa.public_key');
        $this->passphrase = (string) config('crypto.rsa.passphrase', '');
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Management
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง instance ใหม่พร้อม key ที่กำหนด (immutable pattern)
     */
    public function withKeys(?string $privateKey = null, ?string $publicKey = null): static
    {
        return new static(
            $privateKey ?? $this->privateKeyPem,
            $publicKey ?? $this->publicKeyPem,
        );
    }

    public function getPrivateKey(): ?string
    {
        return $this->privateKeyPem;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKeyPem;
    }

    /**
     * สร้าง RSA key pair ใหม่ (PKCS8 format)
     *
     * @param  int  $bits  Key size (ต้อง >= 2048)
     * @return array{private: string, public: string}  PEM key pair
     */
    public function generateKeyPair(int $bits = 4096): array
    {
        $this->assertKeySize($bits);

        $privateKey = RSA::createKey($bits);

        return [
            'private' => $privateKey->toString('PKCS8'),
            'public' => $privateKey->getPublicKey()->toString('PKCS8'),
        ];
    }

    /**
     * สร้าง RSA key pair พร้อม passphrase protection
     *
     * Private key จะถูกเข้ารหัสด้วย passphrase — ต้องใส่ passphrase ทุกครั้งที่ใช้
     *
     * @param  int  $bits  Key size
     * @param  string  $passphrase  รหัสผ่านสำหรับ protect private key
     * @return array{private: string, public: string}  PEM key pair (private key encrypted)
     */
    public function generateProtectedKeyPair(int $bits = 4096, string $passphrase = ''): array
    {
        $this->assertKeySize($bits);

        if ($passphrase === '') {
            throw new RuntimeException('Passphrase is required for protected key pair');
        }

        $privateKey = RSA::createKey($bits);

        return [
            'private' => $privateKey->withPassword($passphrase)->toString('PKCS8'),
            'public' => $privateKey->getPublicKey()->toString('PKCS8'),
        ];
    }

    /**
     * แยก public key จาก private key
     *
     * @param  string  $privateKeyPem  PEM private key
     * @return string  PEM public key (PKCS8)
     */
    public function extractPublicKey(string $privateKeyPem): string
    {
        $key = $this->loadAndConfigureKey($privateKeyPem);

        if (! $key instanceof PrivateKey) {
            throw new RuntimeException('ต้องเป็น private key เท่านั้น');
        }

        return $key->getPublicKey()->toString('PKCS8');
    }

    /**
     * ตรวจว่า public key เข้าคู่กับ private key หรือไม่
     *
     * วิธีตรวจ: sign ด้วย private → verify ด้วย public
     *
     * @param  string  $privateKeyPem  PEM private key
     * @param  string  $publicKeyPem  PEM public key
     * @return bool  true ถ้าเข้าคู่กัน
     */
    public function isKeyPairMatch(string $privateKeyPem, string $publicKeyPem): bool
    {
        try {
            $challenge = random_bytes(32);
            $signature = $this->sign(bin2hex($challenge), $privateKeyPem);

            return $this->verifySignature(bin2hex($challenge), $signature, $publicKeyPem);
        } catch (\Throwable) {
            return false;
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Inspection
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจว่า key เป็น private หรือ public
     *
     * @return string  "private key" | "public key"
     */
    public function getKeyType(string $key): string
    {
        return $this->isPrivateKey($key) ? 'private key' : 'public key';
    }

    public function isPrivateKey(string $key): bool
    {
        try {
            return PublicKeyLoader::load($key) instanceof PrivateKey;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isPublicKey(string $key): bool
    {
        try {
            $loaded = PublicKeyLoader::load($key);

            return $loaded instanceof PublicKey && ! $loaded instanceof PrivateKey;
        } catch (\Throwable) {
            return false;
        }
    }

    public function isValidKey(string $key): bool
    {
        try {
            $loaded = PublicKeyLoader::load($key);

            return $loaded instanceof PublicKey || $loaded instanceof PrivateKey;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ดึงข้อมูลรายละเอียดของ key
     *
     * @param  string  $key  PEM key string
     * @return array{type: string, bits: int, fingerprint: string, max_encrypt_bytes: int}
     */
    public function getKeyInfo(string $key): array
    {
        $loaded = $this->loadRawKey($key);
        $publicKey = $loaded instanceof PrivateKey ? $loaded->getPublicKey() : $loaded;
        $bits = $publicKey->getLength();

        return [
            'type' => $loaded instanceof PrivateKey ? 'private' : 'public',
            'bits' => $bits,
            'fingerprint' => $this->computeFingerprint($publicKey),
            'max_encrypt_bytes' => (int) floor($bits / 8) - self::OAEP_SHA256_OVERHEAD,
        ];
    }

    /**
     * ดึง SHA-256 fingerprint ของ key (ใช้ public key component)
     *
     * format: "SHA256:xxxx" (base64, 43 chars) — เหมือน ssh-keygen -l
     *
     * @param  string  $key  PEM key string (private หรือ public)
     * @return string  fingerprint string
     */
    public function getKeyFingerprint(string $key): string
    {
        $loaded = $this->loadRawKey($key);
        $publicKey = $loaded instanceof PrivateKey ? $loaded->getPublicKey() : $loaded;

        return $this->computeFingerprint($publicKey);
    }

    /**
     * ดึงขนาด key (bits)
     *
     * @param  string  $key  PEM key string
     * @return int  key size in bits (เช่น 2048, 4096)
     */
    public function getKeySize(string $key): int
    {
        $loaded = $this->loadRawKey($key);
        $publicKey = $loaded instanceof PrivateKey ? $loaded->getPublicKey() : $loaded;

        return $publicKey->getLength();
    }

    /**
     * ดูขนาดสูงสุดที่ pure RSA encrypt ได้ (bytes)
     *
     * เกินกว่านี้ต้องใช้ hybrid encryption
     * คำนวณจาก: (keyBits / 8) - OAEP_overhead
     *
     * @param  string|null  $key  PEM key (null = ใช้ public key ที่ตั้งไว้)
     * @return int  max plaintext bytes
     */
    public function getMaxEncryptSize(?string $key = null): int
    {
        $keyPem = $key ?? $this->publicKeyPem;

        if ($keyPem === null || $keyPem === '') {
            throw new RuntimeException('RSA key ไม่ได้ตั้งค่า');
        }

        $loaded = $this->loadRawKey($keyPem);
        $publicKey = $loaded instanceof PrivateKey ? $loaded->getPublicKey() : $loaded;
        $bits = $publicKey->getLength();

        return (int) floor($bits / 8) - self::OAEP_SHA256_OVERHEAD;
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Format Conversion
    // ═══════════════════════════════════════════════════════════

    /**
     * แปลง key เป็น PKCS1 format
     *
     * PKCS1: "-----BEGIN RSA PRIVATE KEY-----" / "-----BEGIN RSA PUBLIC KEY-----"
     *
     * @param  string  $key  PEM key (PKCS1 หรือ PKCS8)
     * @return string  PEM key ในรูปแบบ PKCS1
     */
    public function convertToPkcs1(string $key): string
    {
        $loaded = $this->loadRawKey($key);

        return $loaded->toString('PKCS1');
    }

    /**
     * แปลง key เป็น PKCS8 format
     *
     * PKCS8: "-----BEGIN PRIVATE KEY-----" / "-----BEGIN PUBLIC KEY-----"
     *
     * @param  string  $key  PEM key (PKCS1 หรือ PKCS8)
     * @return string  PEM key ในรูปแบบ PKCS8
     */
    public function convertToPkcs8(string $key): string
    {
        $loaded = $this->loadRawKey($key);

        return $loaded->toString('PKCS8');
    }

    /**
     * โหลด key จาก file path
     *
     * @param  string  $path  file path ของ key
     * @return string  PEM key string
     */
    public function loadKeyFromFile(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("ไม่สามารถอ่านไฟล์ key: {$path}");
        }

        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("อ่านไฟล์ key ล้มเหลว: {$path}");
        }

        return trim($content);
    }

    // ═══════════════════════════════════════════════════════════
    //  RSA Encrypt / Decrypt (pure RSA, OAEP-SHA256)
    // ═══════════════════════════════════════════════════════════

    /**
     * Encrypt string ด้วย public key (OAEP-SHA256)
     *
     * ⚠️ data ต้องไม่เกิน getMaxEncryptSize() bytes
     *    ถ้าเกิน → ใช้ hybridEncrypt() แทน
     *
     * @param  string  $data  ข้อมูลที่ต้องการเข้ารหัส
     * @param  string|null  $publicKeyPem  PEM public key (null = ใช้ key ที่ตั้งไว้)
     * @return string  Base64-encoded ciphertext
     */
    public function encrypt(string $data, ?string $publicKeyPem = null): string
    {
        $key = $this->resolvePublicKey($publicKeyPem);

        try {
            return base64_encode($key->encrypt($data));
        } catch (\Throwable $e) {
            throw new RuntimeException('RSA encrypt ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Decrypt ด้วย private key (OAEP-SHA256)
     *
     * @param  string  $encryptedBase64  Base64-encoded ciphertext
     * @param  string|null  $privateKeyPem  PEM private key (null = ใช้ key ที่ตั้งไว้)
     * @return string  plaintext
     */
    public function decrypt(string $encryptedBase64, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);
        $raw = $this->safeBase64Decode($encryptedBase64);

        try {
            $result = $key->decrypt($raw);
        } catch (\Throwable $e) {
            throw new RuntimeException('RSA decrypt ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }

        if ($result === false) {
            throw new RuntimeException('RSA decrypt ล้มเหลว: ข้อมูลไม่ถูกต้อง');
        }

        return $result;
    }

    /**
     * Encrypt mixed data (array/object → json_encode อัตโนมัติ)
     *
     * ถ้า data ใหญ่เกินขนาดที่ RSA รับได้ → ใช้ hybrid อัตโนมัติ
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $publicKeyPem  PEM public key
     * @return string  Base64-encoded ciphertext
     */
    public function encryptData(mixed $data, ?string $publicKeyPem = null): string
    {
        $plaintext = $this->serialize($data);

        $maxSize = $this->getMaxEncryptSize($publicKeyPem);

        // ถ้าเกินขนาด RSA → ใช้ hybrid อัตโนมัติ
        if (strlen($plaintext) > $maxSize) {
            return $this->hybridEncrypt($plaintext, $publicKeyPem);
        }

        return $this->encrypt($plaintext, $publicKeyPem);
    }

    /**
     * Decrypt mixed data → คืนค่า type เดิม
     *
     * @param  string  $encryptedBase64  ข้อมูลจาก encryptData()
     * @param  string|null  $privateKeyPem  PEM private key
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function decryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed
    {
        // ตรวจว่าเป็น hybrid format หรือไม่
        $raw = base64_decode($encryptedBase64, true);

        if ($raw !== false && (
            str_starts_with($raw, self::HYBRID_MAGIC_V1) ||
            str_starts_with($raw, self::HYBRID_MAGIC_V2)
        )) {
            return $this->deserialize($this->hybridDecrypt($encryptedBase64, $privateKeyPem));
        }

        return $this->deserialize($this->decrypt($encryptedBase64, $privateKeyPem));
    }

    // ═══════════════════════════════════════════════════════════
    //  RSA Sign / Verify (PSS-SHA256)
    // ═══════════════════════════════════════════════════════════

    /**
     * Sign string ด้วย private key (PSS-SHA256)
     *
     * @param  string  $data  ข้อมูลที่ต้องการ sign
     * @param  string|null  $privateKeyPem  PEM private key
     * @return string  Base64-encoded signature
     */
    public function sign(string $data, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);

        try {
            return base64_encode($key->sign($data));
        } catch (\Throwable $e) {
            throw new RuntimeException('RSA sign ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Verify signature ด้วย public key
     *
     * @param  string  $data  ข้อมูลต้นฉบับ
     * @param  string  $signatureBase64  Base64-encoded signature
     * @param  string|null  $publicKeyPem  PEM public key
     * @return bool  true ถ้า signature ถูกต้อง
     */
    public function verifySignature(string $data, string $signatureBase64, ?string $publicKeyPem = null): bool
    {
        try {
            $key = $this->resolvePublicKey($publicKeyPem);
            $signature = base64_decode($signatureBase64, true);

            if ($signature === false) {
                return false;
            }

            return $key->verify($data, $signature);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Sign mixed data (array/object → json_encode ก่อน sign)
     *
     * @param  mixed  $data  ข้อมูล
     * @param  string|null  $privateKeyPem  PEM private key
     * @return string  Base64-encoded signature
     */
    public function signData(mixed $data, ?string $privateKeyPem = null): string
    {
        return $this->sign($this->serialize($data), $privateKeyPem);
    }

    /**
     * Verify signature ของ mixed data
     *
     * @param  mixed  $data  ข้อมูล (ต้องตรงกับตอน sign)
     * @param  string  $signatureBase64  Base64-encoded signature
     * @param  string|null  $publicKeyPem  PEM public key
     * @return bool  true ถ้า signature ถูกต้อง
     */
    public function verifyDataSignature(mixed $data, string $signatureBase64, ?string $publicKeyPem = null): bool
    {
        return $this->verifySignature($this->serialize($data), $signatureBase64, $publicKeyPem);
    }

    /**
     * Sign JSON payload พร้อม timestamp (ป้องกัน replay attack)
     *
     * เพิ่ม `_signed_at` ใน payload → receiver ตรวจอายุได้
     *
     * @param  array  $payload  ข้อมูลที่ต้องการ sign
     * @return array{data: array, signature: string}
     */
    public function signPayload(array $payload): array
    {
        $payload['_signed_at'] = now()->toIso8601String();
        $canonical = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return [
            'data' => $payload,
            'signature' => $this->sign($canonical),
        ];
    }

    /**
     * Verify signed payload พร้อมตรวจอายุ
     *
     * @param  array  $signedPayload  ข้อมูลจาก signPayload()
     * @param  int  $maxAgeSeconds  อายุสูงสุดของ signature (default: 300 = 5 นาที)
     * @return bool  true ถ้า signature ถูกต้องและยังไม่หมดอายุ
     */
    public function verifyPayload(array $signedPayload, int $maxAgeSeconds = 300): bool
    {
        $data = $signedPayload['data'] ?? null;
        $signature = $signedPayload['signature'] ?? null;

        if (! is_array($data) || ! is_string($signature)) {
            return false;
        }

        $signedAt = $data['_signed_at'] ?? null;

        if (! is_string($signedAt)) {
            return false;
        }

        try {
            $signedTime = Carbon::parse($signedAt);
        } catch (\Throwable) {
            return false;
        }

        if (abs(now()->diffInSeconds($signedTime)) > $maxAgeSeconds) {
            return false;
        }

        $canonical = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->verifySignature($canonical, $signature);
    }

    // ═══════════════════════════════════════════════════════════
    //  Hybrid Encryption — Binary Packed Format
    // ═══════════════════════════════════════════════════════════

    /**
     * Hybrid Encrypt — RSA + AES-256-GCM (binary packed, compact)
     *
     * Format V2: [MAGIC:7] [encKeyLen:2] [RSA(AES_key):N] [IV:12] [tag:16] [ciphertext]
     * AAD = MAGIC + encKeyLen + RSA(AES_key) → header integrity protected
     *
     * @param  string  $data  ข้อมูล (ไม่จำกัดขนาด)
     * @param  string|null  $publicKeyPem  PEM public key
     * @return string  Base64-encoded binary
     */
    public function hybridEncrypt(string $data, ?string $publicKeyPem = null): string
    {
        $key = $this->resolvePublicKey($publicKeyPem);

        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $iv = random_bytes(self::GCM_IV_LENGTH);

        try {
            $encryptedKey = $key->encrypt($aesKey);

            // AAD ครอบคลุม header → ถ้า header ถูกแก้ไข GCM จะ reject ทันที
            $aad = self::HYBRID_MAGIC_V2 . pack('n', strlen($encryptedKey)) . $encryptedKey;

            $tag = '';
            $ciphertext = openssl_encrypt(
                $data,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad,
                self::GCM_TAG_LENGTH,
            );

            if ($ciphertext === false) {
                throw new RuntimeException('AES encrypt ล้มเหลว');
            }

            return base64_encode($aad . $iv . $tag . $ciphertext);
        } finally {
            sodium_memzero($aesKey);
        }
    }

    /**
     * Hybrid Decrypt — auto-detect V1/V2 จาก magic header
     *
     * @param  string  $encryptedBase64  Base64-encoded hybrid payload
     * @param  string|null  $privateKeyPem  PEM private key
     * @return string  plaintext
     */
    public function hybridDecrypt(string $encryptedBase64, ?string $privateKeyPem = null): string
    {
        $key = $this->resolvePrivateKey($privateKeyPem);
        $raw = $this->safeBase64Decode($encryptedBase64);

        // ตรวจ version จาก magic header
        if (str_starts_with($raw, self::HYBRID_MAGIC_V2)) {
            $version = 2;
            $magic = self::HYBRID_MAGIC_V2;
        } elseif (str_starts_with($raw, self::HYBRID_MAGIC_V1)) {
            $version = 1;
            $magic = self::HYBRID_MAGIC_V1;
        } else {
            throw new RuntimeException('ไม่ใช่ hybrid format (magic header ไม่ตรง)');
        }

        $magicLen = strlen($magic);

        if (strlen($raw) < $magicLen + 2) {
            throw new RuntimeException('Hybrid payload สั้นเกินไป — ข้อมูลอาจเสียหาย');
        }

        // Unpack binary
        $offset = $magicLen;

        $encKeyLen = unpack('n', substr($raw, $offset, 2))[1];
        $offset += 2;

        $expectedMinLen = $offset + $encKeyLen + self::GCM_IV_LENGTH + self::GCM_TAG_LENGTH + 1;

        if (strlen($raw) < $expectedMinLen) {
            throw new RuntimeException('Hybrid payload สั้นเกินไป — ข้อมูลอาจเสียหาย');
        }

        $encryptedKey = substr($raw, $offset, $encKeyLen);
        $offset += $encKeyLen;

        $iv = substr($raw, $offset, self::GCM_IV_LENGTH);
        $offset += self::GCM_IV_LENGTH;

        $tag = substr($raw, $offset, self::GCM_TAG_LENGTH);
        $offset += self::GCM_TAG_LENGTH;

        $ciphertext = substr($raw, $offset);

        try {
            $aesKey = $key->decrypt($encryptedKey);
        } catch (\Throwable $e) {
            throw new RuntimeException('RSA decrypt AES key ล้มเหลว: ' . $e->getMessage(), 0, $e);
        }

        // V2: ใช้ AAD เพื่อ verify integrity ของ header | V1: ไม่มี AAD
        $aad = $version === 2
            ? $magic . pack('n', strlen($encryptedKey)) . $encryptedKey
            : '';

        try {
            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                $aad,
            );

            if ($plaintext === false) {
                throw new RuntimeException('AES decrypt ล้มเหลว (data อาจถูกแก้ไข — integrity check failed)');
            }

            return $plaintext;
        } finally {
            sodium_memzero($aesKey);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Hybrid Encryption — JSON Envelope Format
    // ═══════════════════════════════════════════════════════════

    /**
     * Hybrid Encrypt — JSON Envelope format (readable, transportable)
     *
     * เหมาะสำหรับ: API response, JSON storage, debugging ง่ายกว่า binary
     *
     * @param  mixed  $data  ข้อมูล (string, array, object — จะ json_encode อัตโนมัติ)
     * @param  string|null  $publicKeyPem  PEM public key
     * @return array{encrypted_key: string, iv: string, tag: string, data: string, cipher: string, v: int}
     */
    public function hybridEncryptEnvelope(mixed $data, ?string $publicKeyPem = null): array
    {
        $plaintext = $this->serialize($data);
        $key = $this->resolvePublicKey($publicKeyPem);

        $aesKey = random_bytes(self::AES_KEY_LENGTH);
        $iv = random_bytes(self::GCM_IV_LENGTH);

        try {
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plaintext,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
                '',
                self::GCM_TAG_LENGTH,
            );

            if ($ciphertext === false) {
                throw new RuntimeException('AES encrypt ล้มเหลว');
            }

            return [
                'v' => 2,
                'cipher' => 'rsa-aes-256-gcm',
                'encrypted_key' => base64_encode($key->encrypt($aesKey)),
                'iv' => base64_encode($iv),
                'tag' => base64_encode($tag),
                'data' => base64_encode($ciphertext),
            ];
        } finally {
            sodium_memzero($aesKey);
        }
    }

    /**
     * Hybrid Decrypt — JSON Envelope format
     *
     * @param  array{encrypted_key: string, iv: string, tag: string, data: string}  $envelope
     * @param  string|null  $privateKeyPem  PEM private key
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function hybridDecryptEnvelope(array $envelope, ?string $privateKeyPem = null): mixed
    {
        $required = ['encrypted_key', 'iv', 'tag', 'data'];
        $missing = array_diff($required, array_keys($envelope));

        if ($missing !== []) {
            throw new RuntimeException('Envelope missing fields: ' . implode(', ', $missing));
        }

        $key = $this->resolvePrivateKey($privateKeyPem);

        $aesKey = $key->decrypt($this->safeBase64Decode($envelope['encrypted_key']));
        $iv = $this->safeBase64Decode($envelope['iv']);
        $tag = $this->safeBase64Decode($envelope['tag']);
        $ciphertext = $this->safeBase64Decode($envelope['data']);

        try {
            $plaintext = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $aesKey,
                OPENSSL_RAW_DATA,
                $iv,
                $tag,
            );

            if ($plaintext === false) {
                throw new RuntimeException('AES decrypt ล้มเหลว (data อาจถูกแก้ไข)');
            }

            return $this->deserialize($plaintext);
        } finally {
            sodium_memzero($aesKey);
        }
    }

    /**
     * Hybrid encrypt mixed data → base64 string (convenience)
     *
     * @param  mixed  $data  ข้อมูล (array, object, string ฯลฯ)
     * @param  string|null  $publicKeyPem  PEM public key
     * @return string  Base64-encoded hybrid ciphertext
     */
    public function hybridEncryptData(mixed $data, ?string $publicKeyPem = null): string
    {
        return $this->hybridEncrypt($this->serialize($data), $publicKeyPem);
    }

    /**
     * Hybrid decrypt → mixed data
     *
     * @param  string  $encryptedBase64  ข้อมูลจาก hybridEncryptData()
     * @param  string|null  $privateKeyPem  PEM private key
     * @return mixed  ข้อมูลต้นฉบับ
     */
    public function hybridDecryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed
    {
        return $this->deserialize($this->hybridDecrypt($encryptedBase64, $privateKeyPem));
    }

    // ─── Private: Key Loading ───────────────────────────────────

    /**
     * โหลด key (raw — ไม่ configure padding)
     */
    private function loadRawKey(string $pem): PrivateKey|PublicKey
    {
        try {
            $key = $this->passphrase !== ''
                ? PublicKeyLoader::load($pem, $this->passphrase)
                : PublicKeyLoader::load($pem);
        } catch (\Throwable $e) {
            throw new RuntimeException('RSA key โหลดไม่ได้: ' . $e->getMessage(), 0, $e);
        }

        if (! $key instanceof PrivateKey && ! $key instanceof PublicKey) {
            throw new RuntimeException('RSA key format ไม่รองรับ');
        }

        return $key;
    }

    /**
     * โหลด key + configure OAEP-SHA256 / PSS-SHA256
     */
    private function loadAndConfigureKey(string $pem): PrivateKey|PublicKey
    {
        return $this->applyPadding($this->loadRawKey($pem));
    }

    private function applyPadding(PrivateKey|PublicKey $key): PrivateKey|PublicKey
    {
        return $key
            ->withPadding(RSA::ENCRYPTION_OAEP | RSA::SIGNATURE_PSS)
            ->withHash('sha256')
            ->withMGFHash('sha256');
    }

    private function resolvePublicKey(?string $pem = null): PublicKey
    {
        $raw = $pem ?? $this->publicKeyPem;

        if (! $raw) {
            throw new RuntimeException('RSA public key ไม่ได้ตั้งค่า — ตรวจ config crypto.rsa.public_key');
        }

        $key = $this->loadAndConfigureKey($raw);

        // ถ้าให้ private key มา → แยก public key ออก
        if ($key instanceof PrivateKey) {
            $key = $this->applyPadding($key->getPublicKey());
        }

        return $key;
    }

    private function resolvePrivateKey(?string $pem = null): PrivateKey
    {
        $raw = $pem ?? $this->privateKeyPem;

        if (! $raw) {
            throw new RuntimeException('RSA private key ไม่ได้ตั้งค่า — ตรวจ config crypto.rsa.private_key');
        }

        $key = $this->loadAndConfigureKey($raw);

        if (! $key instanceof PrivateKey) {
            throw new RuntimeException('key ที่ให้มาไม่ใช่ private key');
        }

        return $key;
    }

    // ─── Private: Serialization ─────────────────────────────────

    private function serialize(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function deserialize(string $data): mixed
    {
        $decoded = json_decode($data, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $data;
    }

    // ─── Private: Utility ───────────────────────────────────────

    private function safeBase64Decode(string $data): string
    {
        $decoded = base64_decode($data, true);

        if ($decoded === false) {
            throw new RuntimeException('Base64 decode ล้มเหลว — ข้อมูลไม่ถูกต้อง');
        }

        return $decoded;
    }

    private function assertKeySize(int $bits): void
    {
        if ($bits < self::MIN_KEY_BITS) {
            throw new RuntimeException(
                "Key size {$bits} bits ไม่ปลอดภัย — ต้องอย่างน้อย " . self::MIN_KEY_BITS . ' bits',
            );
        }
    }

    /**
     * คำนวณ SHA-256 fingerprint จาก public key DER
     */
    private function computeFingerprint(PublicKey $publicKey): string
    {
        $der = $publicKey->toString('PKCS8');

        return 'SHA256:' . rtrim(base64_encode(hash('sha256', $der, true)), '=');
    }
}
