<?php

declare(strict_types=1);

namespace Core\Base\Services\Security\Contracts;

/**
 * EncryptionServiceInterface — สัญญาสำหรับบริการเข้ารหัส/ถอดรหัสหลัก
 */
interface EncryptionServiceInterface
{
    /**
     * สร้าง deterministic fingerprint จาก data (cache key, deduplication, ETag)
     */
    public function fingerprint(mixed $data, string $algorithm = 'sha3-256', int $length = 0): string;

    /**
     * สร้าง random salt (Base64url)
     */
    public function generateSalts(int $length = 32, bool $isBase64 = false, bool $urlSafe = false): string;

    /**
     * อนุมานกุญแจจาก Master Key ด้วย HKDF-SHA3-256
     */
    public function deriveKey(string $context = 'default', string $saltb64 = '', ?string $inputKeyMaterial = null): string;

    /**
     * เข้ารหัสข้อมูลด้วย Symmetric Key
     */
    public function encryptWithKey(mixed $data, ?string $key32b64 = null, bool $urlSafe = false): string;

    /**
     * ถอดรหัสข้อมูลด้วย Symmetric Key
     */
    public function decryptWithKey(string $ciphertext, ?string $key32b64 = null, bool $urlSafe = false): mixed;

    /**
     * เข้ารหัสข้อมูลด้วย AEAD (XChaCha20-Poly1305-IETF)
     */
    public function encryptWithKeyAead(mixed $data, string $aad = '', ?string $keyb64 = null, bool $returnBase64 = true): string;

    /**
     * ถอดรหัสข้อมูลจาก AEAD
     */
    public function decryptWithKeyAead(string $ciphertextb64, string $aad = '', ?string $keyb64 = null, bool $isBase64Input = true): mixed;

    /**
     * สร้าง Detached Signature (Ed25519)
     */
    public function sign(mixed $data, string $sk, bool $isBase64 = true, bool $urlSafe = false): string;

    /**
     * ตรวจสอบ Detached Signature (Ed25519)
     */
    public function verify(string $signatureb64, mixed $data, string $pk): bool;

    /**
     * เข้ารหัสข้อมูลเป็น JWT (Custom)
     */
    public function jwtencode(mixed $data): string;

    /**
     * ถอดรหัสข้อมูลจาก JWT (Custom)
     */
    public function jwtdecode(string $token): mixed;
}
