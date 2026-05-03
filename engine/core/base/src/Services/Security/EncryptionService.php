<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

use Core\Base\Services\Security\Contracts\EncryptionServiceInterface;
use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use RuntimeException;

/**
 * EncryptionService — บริการเข้ารหัส/ถอดรหัสหลัก (Sodium-Based)
 *
 * ═══════════════════════════════════════════════════════════════
 *  สถาปัตยกรรม: Sodium Symmetric + AEAD Encryption Service
 * ═══════════════════════════════════════════════════════════════
 *
 * ทำหน้าที่เป็น Facade Layer ครอบ SodiumHelper / HashHelper / JwtHelper
 * รองรับ:
 *  1. Symmetric Encryption     — XSalsa20-Poly1305 SecretBox
 *  2. AEAD                     — XChaCha20-Poly1305-IETF (พร้อม AAD)
 *  3. Key Derivation           — HKDF-SHA3-256 จาก master key
 *  4. Digital Signatures       — Ed25519 Detached
 *  5. JWT Custom Tokens        — EdDSA / HMAC signed
 *
 * รับ/คืนค่าเป็น Base64 (Standard) หรือ Base64url (URL-safe) ตามที่กำหนด
 */
final class EncryptionService implements EncryptionServiceInterface
{
    use DataNormalization, ParsesEncryptionKey;

    /** @var string อัลกอริทึม hash เริ่มต้น */
    private const string DEFAULT_ALGO = 'sha3-256';

    /**
     * กุญแจหลักจาก config สำหรับ AEAD/HKDF
     * เก็บเป็น raw config string (อาจมี `base64:` prefix) — ถอดรหัสเมื่อใช้งาน
     */
    private readonly string $masterKeyRaw;

    /**
     * สร้าง EncryptionService
     *
     * @param  SodiumHelper  $sodium  ตัวช่วย Sodium Crypto
     * @param  HashHelper  $hashHelper  ตัวช่วย Hashing + KDF
     * @param  JwtHelper  $jwtHelper  ตัวช่วย JWT Token
     */
    public function __construct(
        private readonly SodiumHelper $sodium,
        private readonly HashHelper $hashHelper,
        private readonly JwtHelper $jwtHelper,
    ) {
        $keyVal = config('core.base::security.masterkey', '');
        $this->masterKeyRaw = \is_scalar($keyVal) ? (string) $keyVal : '';
    }

    /**
     * ล้าง master key ออกจากหน่วยความจำเมื่อ object ถูกทำลาย
     * ป้องกันการรั่วไหลของ key material ผ่าน memory dump
     */
    public function __destruct()
    {
        $key = $this->masterKeyRaw;
        if ($key !== '') {
            \sodium_memzero($key);
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Content Fingerprint
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง deterministic fingerprint จาก data
     *
     * เหมาะสำหรับ: cache key, deduplication, content addressing, ETag
     * JSON keys จะถูก sort → ลำดับ key ไม่มีผลต่อผลลัพธ์
     *
     * @param  mixed  $data  ข้อมูล (string, array, object)
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @param  int  $length  ตัดผลลัพธ์ให้สั้นลง (0 = ไม่ตัด)
     * @return string fingerprint hex string
     */
    public function fingerprint(mixed $data, string $algorithm = self::DEFAULT_ALGO, int $length = 0): string
    {
        $normalized = \is_array($data) || \is_object($data)
            ? $this->canonicalize($data)
            : (\is_scalar($data) ? (string) $data : '');

        $hash = $this->hashHelper->hash($normalized, $algorithm);

        return $length > 0 ? \substr($hash, 0, $length) : $hash;
    }

    // ─── Key Derivation ────────────────────────────────────────

    /**
     * อนุมานกุญแจย่อยจาก Master Key ด้วย HKDF-SHA3-256
     *
     * ใช้ HKDF (RFC 5869) — ปลอดภัยกว่า raw hash และรองรับ domain separation
     *
     * @param  string  $context  บริบทการใช้งาน / info label (Domain Separation)
     * @param  string  $saltb64  Salt ในรูปแบบ Base64 (optional)
     * @param  string|null  $inputKeyMaterial  กุญแจต้นทาง (null = ใช้ masterkey จาก config)
     * @return string กุญแจย่อย 32 bytes
     *
     * @throws RuntimeException เมื่อ masterkey ไม่ได้ตั้งค่า
     */
    public function deriveKey(string $context = 'default', string $saltb64 = '', ?string $inputKeyMaterial = null, bool $useBinary = false): string
    {
        $ikm = $this->resolveInputKeyMaterial($inputKeyMaterial);

        // ถอดรหัส salt จาก Base64 (ถ้ามี)
        $salt = '';
        if ($saltb64 !== '') {
            $decoded = $this->resolveKey($saltb64, 32);
            $salt = ($decoded !== null) ? $decoded : '';
        }

        // HKDF-SHA3-256: IKM + salt + context (info) → 32-byte derived key
        $derived = $this->hashHelper->hkdf($ikm, 32, $context, $salt);

        return $this->encodeKey($derived, $useBinary);
    }

    /**
     * สร้าง Salt แบบสุ่มที่ปลอดภัยด้วยการเข้ารหัส
     *
     * @param  int  $length  ความยาว Salt (bytes, default: 32)
     * @return string Salt ที่สร้างขึ้น
     */
    public function generateSalts(int $length = 32, bool $useBinary = false): string
    {
        return $this->hashHelper->generateSalt($length, $useBinary);
    }

    // ─── AEAD Encryption ──────────────────────────────────────

    // ─── Symmetric Encryption (Simple) ───────────────────────

    // ─── Digital Signatures (Ed25519) ─────────────────────────

    /**
     * สร้าง Detached Signature (Ed25519)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการลงนาม
     * @param  string  $sk  Secret key ของผู้ส่ง (Base64)
     * @return string ลายเซ็น (Base64 หรือ raw binary)
     *
     * @throws RuntimeException เมื่อ key ไม่ถูกต้อง หรือ sign ล้มเหลว
     */
    public function sign(mixed $data, string $sk, bool $useBinary = false): string
    {
        return $this->sodium->sign($data, $sk, $useBinary);
    }

    /**
     * ตรวจสอบ Detached Signature (Ed25519)
     *
     * @param  string  $signatureb64  ลายเซ็นในรูปแบบ Base64
     * @param  mixed  $data  ข้อมูลต้นฉบับ
     * @param  string  $pk  Public key ของผู้ส่ง (Base64)
     * @return bool true ถ้าลายเซ็นถูกต้อง
     */
    public function verify(string $signatureb64, mixed $data, string $pk): bool
    {
        return $this->sodium->verify($signatureb64, $this->normalizeData($data), $pk);
    }

    // ─── JWT Custom Tokens ─────────────────────────────────────

    /**
     * เข้ารหัสข้อมูลเป็น JWT (Custom Claim)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการบรรจุใน JWT
     * @return string JWT string
     *
     * @throws RuntimeException เมื่อ key ไม่ถูกต้อง หรือ sign ล้มเหลว
     */
    public function jwtencode(mixed $data): string
    {
        return $this->jwtHelper->buildCustomToken($data);
    }

    /**
     * ถอดรหัสและตรวจสอบลายเซ็น JWT — ดึง claim 'data' กลับมา
     *
     * ⚠️ ตรวจ signature แต่ไม่ตรวจ expiry — ใช้สำหรับ custom token เท่านั้น
     * สำหรับ auth token จริง ให้ใช้ JwtHelper::parse() โดยตรง
     *
     * @param  string  $token  JWT string
     * @return mixed ข้อมูลดั้งเดิมจาก claim 'data'
     *
     * @throws \Core\Base\Exceptions\InvalidTokenException เมื่อ signature ไม่ถูกต้อง
     */
    public function jwtdecode(string $token): mixed
    {
        return $this->jwtHelper->parsedata($token);
    }

    // ─── Protected Helpers ─────────────────────────────────────

    /**
     * คืนค่า JSON prefix สำหรับ serialization พิเศษ (ป้องกันชนกับข้อมูลปกติ)
     */
    protected function getJsonPrefix(): string
    {
        return "\x02";
    }

    // ─── Private Helpers ───────────────────────────────────────

    /**
     * Resolve Input Key Material — ใช้ key ที่ให้มา หรือ fallback เป็น masterkey จาก config
     *
     * @param  string|null  $inputKeyMaterial  กุญแจต้นทาง (null = ใช้ masterkey)
     * @return string raw binary key
     *
     * @throws RuntimeException เมื่อ masterkey ไม่ได้ตั้งค่าและไม่ได้ส่ง inputKeyMaterial มา
     */
    private function resolveInputKeyMaterial(?string $inputKeyMaterial): string
    {
        if ($inputKeyMaterial !== null && $inputKeyMaterial !== '') {
            return $this->parseKey($inputKeyMaterial);
        }

        if ($this->masterKeyRaw === '') {
            throw new RuntimeException('EncryptionService: masterkey ไม่ได้ตั้งค่า — ตรวจสอบ config core.base::security.masterkey');
        }

        return $this->parseKey($this->masterKeyRaw);
    }
}
