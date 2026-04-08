<?php

declare(strict_types=1);

namespace Core\Base\Services\Security\Contracts;

/**
 * SodiumHybridP2pInterface — สัญญาสำหรับการใช้งาน Sodium Hybrid P2p Encryption
 * ─────────────────────────────────────────────────────────────────────────
 * รองรับทั้งโหมด Session (Stateful) และ Cross-host (Stateless)
 */
interface SodiumHybridP2pInterface
{
    /**
     * [Key Management] สร้าง Ed25519 signing keypair สำหรับ encryptForSigned()
     *
     * @return array{signing: string, verify: string} hex-encoded keypair
     */
    public static function generateSigningKeyPair(): array;

    /**
     * [Cross-host Mode] เข้ารหัสสำหรับส่งข้ามโฮส (Stateless, v2)
     * ephPk ถูก bind เข้า effective AAD — ปลอดภัยกว่า v1
     */
    public static function encryptFor(string $message, string $recipientPubKey, string $aad = ''): string;

    /**
     * [Cross-host Mode] สร้าง Bundle ตอบกลับฝั่งส่ง (Stateless)
     */
    public static function replyTo(string $message, string $senderPubKey, string $aad = ''): string;

    /**
     * [Signed Hybrid Mode] เซ็น + เข้ารหัส (Ed25519 + X25519/XChaCha20, v2s)
     *
     * @param  string  $signingSecretKeyHex  Ed25519 secret key ของผู้ส่ง (hex 128 chars)
     */
    public static function encryptForSigned(
        string $message,
        string $recipientPubKey,
        string $signingSecretKeyHex,
        string $aad = '',
    ): string;

    /**
     * [Multi-recipient Mode] เข้ารหัสครั้งเดียวสำหรับหลายผู้รับ
     *
     * @param  array<string,string>  $recipients  ['recipientId' => pubKeyHex/base64]
     */
    public static function sealForMany(string $message, array $recipients, string $aad = ''): string;

    /**
     * ดึง Public Key (Hex) ของ instance นี้
     */
    public function getPublicKeyHex(): string;

    /**
     * ดึง Public Key (Base64) ของ instance นี้
     */
    public function getPublicKeyBase64(): string;

    /**
     * ดึง Keypair (Hex) — ต้องเก็บเป็นความลับ
     */
    public function getKeypairHex(): string;

    /**
     * ดึง Keypair (Base64) — ต้องเก็บเป็นความลับ
     */
    public function getKeypairBase64(): string;

    /**
     * [Session Mode] สร้าง Session Keys จาก Public Key ของอีกฝ่าย
     */
    public function setupSession(string $otherPublicKey, bool $isClient = true): static;

    /**
     * [Session Mode] เข้ารหัสด้วย tx key (ต้อง setupSession() ก่อน)
     */
    public function encrypt(string $message, string $aad = ''): string;

    /**
     * [Session Mode] ถอดรหัสด้วย rx key (ต้อง setupSession() ก่อน)
     */
    public function decrypt(string $payload, string $aad = ''): string;

    /**
     * [Cross-host Mode] ถอดรหัส bundle จากผู้ส่ง (รองรับ v1, v2, legacy)
     */
    public function decryptMessage(string $bundle, string $aad = ''): string;

    /**
     * [Signed Hybrid Mode] ถอดรหัส + ยืนยันลายเซ็น Ed25519 จาก bundle v2s
     *
     * @param  string  $senderVerifyKeyHex  Ed25519 public key ของผู้ส่ง (hex 64 chars)
     */
    public function decryptMessageVerified(
        string $bundle,
        string $senderVerifyKeyHex,
        string $aad = '',
    ): string;

    /**
     * [Multi-recipient Mode] ถอดรหัส multi-recipient envelope
     *
     * @param  string  $recipientId  ID ที่ตรงกับ key ที่ส่งใน sealForMany()
     */
    public function openMultiRecipient(string $envelope, string $recipientId, string $aad = ''): string;

    /**
     * ล้าง Session Keys ออกจาก Memory
     */
    public function wipe(): void;
}
