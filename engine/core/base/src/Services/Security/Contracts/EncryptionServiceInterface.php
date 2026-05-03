<?php

declare(strict_types=1);

namespace Core\Base\Services\Security\Contracts;

/**
 * EncryptionServiceInterface — สัญญาสำหรับบริการเข้ารหัส/ถอดรหัสหลัก
 *
 * ครอบคลุม:
 *  1. Content Fingerprint  — fingerprint()
 *  2. Key Derivation       — deriveKey(), generateSalts()
 *  3. Digital Signatures   — sign(), verify()
 *  4. JWT Custom Tokens    — jwtencode(), jwtdecode()
 */
interface EncryptionServiceInterface
{
    /**
     * สร้าง deterministic fingerprint จาก data (cache key, deduplication, ETag)
     *
     * @param  mixed  $data  ข้อมูล (string, array, object)
     * @param  string  $algorithm  hash algorithm (default: sha3-256)
     * @param  int  $length  ตัดผลลัพธ์ให้สั้นลง (0 = ไม่ตัด)
     * @return string fingerprint hex string
     */
    public function fingerprint(mixed $data, string $algorithm = 'sha3-256', int $length = 0): string;

    /**
     * สร้าง random salt ที่ปลอดภัยด้วยการเข้ารหัส
     *
     * @param  int  $length  ความยาว Salt (bytes)
     * @param  bool  $useBinary  คืนเป็น Base64 หรือ raw binary
     * @return string Salt ที่สร้างขึ้น
     */
    public function generateSalts(int $length = 32, bool $useBinary = false): string;

    /**
     * อนุมานกุญแจย่อยจาก Master Key ด้วย HKDF-SHA3-256
     *
     * @param  string  $context  บริบทการใช้งาน / Domain Separation Label
     * @param  string  $saltb64  Salt ในรูปแบบ Base64 (optional)
     * @param  string|null  $inputKeyMaterial  กุญแจต้นทาง (null = ใช้ masterkey จาก config)
     * @param  bool  $useBinary  คืนเป็น Base64 หรือ raw binary
     * @return string กุญแจย่อย 32 bytes
     */
    public function deriveKey(string $context = 'default', string $saltb64 = '', ?string $inputKeyMaterial = null, bool $useBinary = false): string;

    /**
     * สร้าง Detached Signature (Ed25519)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการลงนาม
     * @param  string  $sk  Secret key ของผู้ส่ง (Base64)
     * @param  bool  $useBinary  คืนเป็น Base64 หรือ raw binary
     * @return string ลายเซ็น
     */
    public function sign(mixed $data, string $sk, bool $useBinary = false): string;

    /**
     * ตรวจสอบ Detached Signature (Ed25519)
     *
     * @param  string  $signatureb64  ลายเซ็นในรูปแบบ Base64
     * @param  mixed  $data  ข้อมูลต้นฉบับ
     * @param  string  $pk  Public key ของผู้ส่ง (Base64)
     * @return bool true ถ้าลายเซ็นถูกต้อง
     */
    public function verify(string $signatureb64, mixed $data, string $pk): bool;

    /**
     * เข้ารหัสข้อมูลเป็น JWT (Custom Claim)
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการบรรจุใน JWT
     * @return string JWT string
     */
    public function jwtencode(mixed $data): string;

    /**
     * ถอดรหัสและตรวจสอบลายเซ็น JWT — ดึง claim 'data' กลับมา
     *
     * @param  string  $token  JWT string
     * @return mixed ข้อมูลดั้งเดิมจาก claim 'data'
     */
    public function jwtdecode(string $token): mixed;
}
