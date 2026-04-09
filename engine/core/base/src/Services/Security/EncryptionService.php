<?php

declare(strict_types=1);

namespace Core\Base\Services\Security;

use Core\Base\Services\Security\Contracts\EncryptionServiceInterface;
use Core\Base\Support\Helpers\Crypto\Concerns\DataNormalization;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\HashHelper;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;

/**
 * EncryptionService — บริการเข้ารหัส/ถอดรหัสหลัก (Sodium-Based)
 *
 * ═══════════════════════════════════════════════════════════════
 *  สถาปัตยกรรม: Sodium Symmetric + AEAD Encryption Service
 * ═══════════════════════════════════════════════════════════════
 *
 * ใช้ SodiumHelper เป็นหัวใจหลักในการจัดการความปลอดภัย รองรับ:
 *  1. Symmetric Encryption     — XSalsa20-Poly1305 SecretBox
 *  2. AEAD                     — XChaCha20-Poly1305-IETF (พร้อม AAD)
 *  3. Key Derivation           — HKDF-SHA3-256 สำหรับ master key
 *
 * รับ/คืนค่าเป็น Base64 (Standard) หรือ Base64url (URL-safe) ตามที่กำหนด
 */
final class EncryptionService implements EncryptionServiceInterface
{
    use DataNormalization, ParsesEncryptionKey;

    private const DEFAULT_ALGO = 'sha3-256';

    /** @var string กุญแจหลักสำหรับ Symmetric Encryption */
    private string $key32;

    /**
     * สร้าง EncryptionService
     *
     * @param  SodiumHelper  $sodium  ตัวช่วย Sodium
     */
    public function __construct(
        private readonly SodiumHelper $sodium,
        private readonly HashHelper $hashHelper,
        private readonly JwtHelper $jwtHelper,
    ) {
        // เก็บ key เป็น base64 ไว้ส่งต่อให้ SodiumHelper ซึ่งต้องการ base64-encoded key
        $this->key32 = (string) config('core.base::security.base64key32', '');
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
    public function fingerprint(mixed $data, string $algorithm = self::DEFAULT_ALGO, int $length = 0): string
    {
        $normalized = \is_array($data) || \is_object($data)
            ? $this->canonicalize($data)
            : (string) $data;

        $hash = $this->hashHelper->hash($normalized, $algorithm);

        return $length > 0 ? \substr($hash, 0, $length) : $hash;
    }

    // ─── Key Derivation ────────────────────────────────────────

    public function deriveKey(string $context = 'default', string $saltb64 = '', ?string $inputKeyMaterial = null, bool $isBase64 = true, bool $urlSafe = false): string
    {
        return $this->hashHelper->deriveKey($context, $saltb64, $inputKeyMaterial, $isBase64, $urlSafe);
    }

    public function generateSalts(int $length = 32, bool $isBase64 = false, bool $urlSafe = false): string
    {
        return $this->hashHelper->generateSalt($length, $isBase64, $urlSafe);
    }

    /**
     * เข้ารหัสข้อมูลด้วย Sodium SecretBox (XSalsa20-Poly1305)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเข้ารหัส (string หรือ mixed — auto JSON)
     * @param  string  $key32b64  Base64-encoded key ขนาด 32 bytes
     * @return string payload เข้ารหัสแล้ว
     */
    public function encryptWithKey(mixed $data, ?string $key32b64 = null, bool $urlSafe = false): string
    {
        $plaintext = $this->normalizeData($data);

        return $this->sodium->encrypt($plaintext, $key32b64, urlSafe: $urlSafe);
    }

    /**
     * ถอดรหัสข้อมูลจาก Sodium SecretBox
     *
     * @param  string  $ciphertext  payload เข้ารหัส (Base64 หรือ Base64url)
     * @param  string  $key32b64  Base64-encoded key ขนาด 32 bytes
     * @param  bool  $urlSafe  ถ้าเป็น true ใช้ Base64url (ไม่มี +/=) แทน Base64 ปกติ
     * @return mixed ข้อมูลดั้งเดิม (string หรือ array/object ถ้าเดิมเป็น mixed)
     */
    public function decryptWithKey(string $ciphertext, ?string $key32b64 = null, bool $urlSafe = false): mixed
    {
        return $this->sodium->decrypt($ciphertext, $key32b64, urlSafe: $urlSafe);
    }

    // ─── AEAD Encryption ──────────────────────────────────────

    /**
     * เข้ารหัสข้อมูลด้วย AEAD (XChaCha20-Poly1305-IETF) พร้อม Additional Authenticated Data
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเข้ารหัส
     * @param  string  $aad  Additional Authenticated Data (ไม่เข้ารหัส แต่ตรวจสอบความถูกต้อง)
     * @param  string  $keyb64  Base64-encoded key
     * @param  bool  $returnBase64  ถ้าเป็น true จะเข้ารหัสผลลัพธ์เป็น Base64
     * @return string payload เข้ารหัสแล้ว
     */
    public function encryptWithKeyAead(mixed $data, string $aad = '', ?string $keyb64 = null, bool $returnBase64 = true): string
    {
        $plaintext = $this->normalizeData($data);
        $keyb64 ??= $this->key32;

        //  $key = $this->hashHelper->decodeb64($keyb64);
        return $this->sodium->encryptAead($plaintext, $aad, $keyb64, $returnBase64);
    }

    /**
     * ถอดรหัสข้อมูลจาก AEAD — ต้องส่ง AAD เดิมเพื่อตรวจสอบ integrity
     *
     * @param  string  $ciphertextb64  payload เข้ารหัส
     * @param  string  $aad  Additional Authenticated Data (ต้องตรงกับตอนเข้ารหัส)
     * @param  string  $keyb64  Base64-encoded key
     * @param  bool  $isBase64Input  ถ้าเป็น true จะถอดรหัสข้อมูลจาก Base64 ก่อน
     * @return mixed ข้อมูลดั้งเดิม
     */
    public function decryptWithKeyAead(string $ciphertextb64, string $aad = '', ?string $keyb64 = null, bool $isBase64Input = true): mixed
    {
        $keyb64 ??= $this->key32;

        return $this->sodium->decryptAead($ciphertextb64, $aad, $keyb64, $isBase64Input);
    }

    /**
     * สร้าง Detached Signature (Ed25519)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการลงนาม
     * @param  string  $sk  Secret key ของผู้ส่ง (Base64)
     * @return string ลายเซ็น (Base64)
     */
    public function sign(mixed $data, string $sk, bool $isBase64 = true, bool $urlSafe = false): string
    {
        $plaintext = $this->normalizeData($data);
        $sign = $this->sodium->sign($plaintext, $sk);

        return $this->maybeBase64($sign, $isBase64, $urlSafe);
    }

    /**
     * ตรวจสอบ Detached Signature (Ed25519)
     *
     * @param  mixed  $data  ข้อมูลต้นฉบับ
     * @param  string  $pk  Public key ของผู้ส่ง (Base64)
     */
    public function verify(string $signatureb64, mixed $data, string $pk): bool
    {
        $signature = $this->hashHelper->decodeb64($signatureb64);
        if ($signature === false) {
            return false;
        }

        return $this->sodium->verify($signature, $this->normalizeData($data), $pk);
    }

    public function jwtencode(mixed $data): string
    {
        return $this->jwtHelper->buildCustomToken($data);
    }

    public function jwtdecode(string $token): mixed
    {
        return $this->jwtHelper->parsedata($token);
    }

    /**
     * คืนค่า JSON prefix สำหรับ serialization พิเศษ เพื่อหลีกเลี่ยงการชนกับข้อมูลปกติ
     */
    protected function getJsonPrefix(): string
    {
        return "\x02";
    }
}
