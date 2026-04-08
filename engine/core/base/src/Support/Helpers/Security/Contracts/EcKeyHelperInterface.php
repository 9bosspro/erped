<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Security\Contracts;

/**
 * SodiumHelperInterface — สัญญาสำหรับ Sodium Crypto Helper (instance API)
 *
 * ครอบคลุม API ระดับ instance ที่ CryptHelper ต้องจัดหาให้:
 *
 *  - Key Management   — สร้าง instance ใหม่พร้อม key (immutable pattern)
 *  - Sign & Verify    — Ed25519 sign/verify ด้วย instance signing key
 *  - Box / Seal       — X25519 anonymous encryption ด้วย instance box key
 *  - ECDH             — X25519 Diffie-Hellman shared secret
 *
 * หมายเหตุ: static utility methods (generate, hash, kdf, pwhash, aead ฯลฯ)
 * ไม่รวมอยู่ใน interface นี้ เนื่องจาก PHP ไม่ enforce static methods
 * ผ่าน interface ได้อย่างมีประสิทธิภาพ
 *
 * Key format: base64url (RFC 4648, ไม่มี padding) สำหรับ Ed25519 / X25519 raw keys
 */
interface EcKeyHelperInterface
{
    // ─── Key Management ──────────────────────────────────────────────────────

    /**
     * สร้าง instance ใหม่พร้อม signing key ที่กำหนด (immutable pattern)
     *
     * ใช้กับ Ed25519 signing key pair:
     *  - $privateKey = base64url Ed25519 secret key (64 bytes)
     *  - $publicKey  = base64url Ed25519 public key (32 bytes)
     *  - $passphrase = ไม่ใช้ใน sodium (คงไว้เพื่อ interface compat)
     *
     * @param  string|null  $privateKey  base64url signing secret key ใหม่
     * @param  string|null  $publicKey  base64url signing public key ใหม่
     * @param  string|null  $passphrase  (ไม่ได้ใช้ — sodium ไม่ encrypt key ใน RAM)
     */
    public function withKeys(
        ?string $privateKey = null,
        ?string $publicKey = null,
        ?string $passphrase = null,
    ): static;

    /**
     * สร้าง instance ใหม่พร้อม Ed25519 signing key pair (immutable)
     *
     * @param  string|null  $signingSecretKey  base64url Ed25519 secret key (64 bytes)
     * @param  string|null  $signingPublicKey  base64url Ed25519 public key (32 bytes)
     */
    public function withSigningKeys(
        ?string $signingSecretKey = null,
        ?string $signingPublicKey = null,
    ): static;

    /**
     * สร้าง instance ใหม่พร้อม X25519 box key pair (immutable)
     *
     * @param  string|null  $boxSecretKey  base64url X25519 secret key (32 bytes)
     * @param  string|null  $boxPublicKey  base64url X25519 public key (32 bytes)
     */
    public function withBoxKeys(
        ?string $boxSecretKey = null,
        ?string $boxPublicKey = null,
    ): static;

    /**
     * ดึง base64url Ed25519 signing secret key ที่ตั้งไว้ใน instance
     *
     * @return string|null base64url string หรือ null ถ้าไม่ได้ตั้งค่า
     */
    public function getPrivateKey(): ?string;

    /**
     * ดึง base64url Ed25519 signing public key ที่ตั้งไว้ใน instance
     *
     * @return string|null base64url string หรือ null ถ้าไม่ได้ตั้งค่า
     */
    public function getPublicKey(): ?string;

    /**
     * ดึง passphrase ที่ตั้งไว้ (sodium ไม่ใช้ — คงไว้เพื่อ interface compat)
     */
    public function getPassphrase(): ?string;

    /**
     * ดึง base64url X25519 box secret key ที่ตั้งไว้ใน instance
     *
     * @return string|null base64url string หรือ null ถ้าไม่ได้ตั้งค่า
     */
    public function getBoxPrivateKey(): ?string;

    /**
     * ดึง base64url X25519 box public key ที่ตั้งไว้ใน instance
     *
     * @return string|null base64url string หรือ null ถ้าไม่ได้ตั้งค่า
     */
    public function getBoxPublicKey(): ?string;

    // ─── Sign & Verify (Instance — Ed25519) ──────────────────────────────────

    /**
     * เซ็นข้อความโดยใช้ instance Ed25519 signing secret key
     *
     * คืน detached binary signature (64 bytes)
     * (params $hash, $signatureFormat คงไว้เพื่อ interface compat — sodium ไม่ใช้)
     *
     * @param  string  $message  ข้อความที่ต้องการเซ็น
     * @param  string  $hash  (ไม่ใช้ — sodium Ed25519 ไม่ต้องระบุ hash)
     * @param  string  $signatureFormat  (ไม่ใช้ — sodium Ed25519 มี format เดียว)
     * @return string binary signature (64 bytes)
     */
    public function signWith(
        string $message,
        string $hash = '',
        string $signatureFormat = 'ASN1',
    ): string;

    /**
     * ตรวจสอบ binary Ed25519 signature โดยใช้ instance signing public key
     *
     * @param  string  $message  ข้อความต้นฉบับ
     * @param  string  $signature  binary signature (64 bytes)
     * @param  string  $hash  (ไม่ใช้)
     * @param  string  $signatureFormat  (ไม่ใช้)
     */
    public function verifyWith(
        string $message,
        string $signature,
        string $hash = '',
        string $signatureFormat = 'ASN1',
    ): bool;

    /**
     * เซ็นข้อความโดยใช้ instance signing key — คืน base64url signature
     *
     * @param  string  $message  ข้อความที่ต้องการเซ็น
     * @param  string  $hash  (ไม่ใช้)
     * @param  string  $signatureFormat  (ไม่ใช้)
     * @return string base64url-encoded signature
     */
    public function signBase64With(
        string $message,
        string $hash = '',
        string $signatureFormat = 'IEEE',
    ): string;

    /**
     * ตรวจสอบ base64url Ed25519 signature โดยใช้ instance signing public key
     *
     * @param  string  $message  ข้อความต้นฉบับ
     * @param  string  $signatureBase64  base64url-encoded signature
     * @param  string  $hash  (ไม่ใช้)
     * @param  string  $signatureFormat  (ไม่ใช้)
     */
    public function verifyBase64With(
        string $message,
        string $signatureBase64,
        string $hash = '',
        string $signatureFormat = 'IEEE',
    ): bool;

    // ─── Box / Seal (Instance — X25519) ──────────────────────────────────────

    /**
     * เข้ารหัสแบบ Anonymous Box ด้วย instance X25519 box public key
     *
     * ใช้ sodium_crypto_box_seal — ผู้รับตรวจสอบ ciphertext ได้ แต่ไม่รู้ว่าใครส่ง
     *
     * @param  string  $message  ข้อความต้นฉบับ
     * @return string binary ciphertext
     */
    public function sealWith(string $message): string;

    /**
     * ถอดรหัส Anonymous Box ด้วย instance X25519 box keys
     *
     * @param  string  $ciphertext  binary ciphertext จาก seal/sealWith
     * @return string plaintext
     */
    public function sealOpenWith(string $ciphertext): string;

    // ─── ECDH (Instance — X25519) ────────────────────────────────────────────

    /**
     * คำนวณ X25519 ECDH shared secret โดยใช้ instance box secret key
     *
     * ⚠️  raw shared secret (32 bytes) ต้องผ่าน KDF (hash/kdfDerive) ก่อนนำไปใช้เป็น cipher key
     *
     * @param  string  $peerPublicKey  base64url X25519 public key ของคู่สนทนา (32 bytes)
     * @return string binary shared secret (32 bytes)
     */
    public function ecdhSharedSecretWith(string $peerPublicKey): string;
}
