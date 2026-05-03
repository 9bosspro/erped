<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * SodiumHelperInterface — สัญญาสำหรับ Sodium Crypto Helper
 *
 * ครอบคลุม:
 *  1.  Symmetric Encryption    — SecretBox (XSalsa20-Poly1305)
 *  2.  AEAD                    — XChaCha20-Poly1305-IETF
 *  3.  Asymmetric Encryption   — Box (Authenticated) & Seal (Anonymous) X25519
 *  4.  Digital Signatures      — Ed25519 (detached & multi-part)
 *  5.  Password Hashing        — Argon2id
 *  6.  File/Stream Encryption  — SecretStream XChaCha20-Poly1305
 *  7.  Hybrid Stream Encryption— Envelope Encryption
 *  8.  Stream Signatures       — BLAKE2b + Ed25519
 *  9.  Hashing & KDF           — BLAKE2b, BLAKE2b KDF
 *  10. Large String Helpers    — php://temp stream bridge
 */
interface SodiumHelperInterface
{
    // ─── 1. Symmetric Encryption ────────────────────────────────

    public function encrypt(mixed $message, ?string $keyBase64 = null, bool $useBinary = false): string;

    public function decrypt(string $payloadBase64, ?string $keyBase64 = null, bool $useBinary = false): mixed;

    // ─── 2. AEAD ────────────────────────────────────────────────

    public function encryptAead(string $aad, ?string $keyBase64, mixed $message, ?string $nonce = null, bool $useBinary = false): string;

    public function decryptAead(string $aad, ?string $keyBase64, string $payload, ?string $nonce = null, bool $useBinary = false): mixed;

    // ─── 3. Asymmetric Box & Seal ───────────────────────────────

    public function box(string $message, string $recipientPublicKey, string $senderSecretKey): string;

    public function boxUrlSafe(string $message, string $recipientPublicKey, string $senderSecretKey): string;

    public function boxOpen(string $payloadBase64, string $senderPublicKey, string $recipientSecretKey): string;

    public function boxOpenUrlSafe(string $payloadUrlSafe, string $senderPublicKey, string $recipientSecretKey): string;

    public function seal(string $message, string $recipientPublicKey): string;

    public function sealUrlSafe(string $message, string $recipientPublicKey): string;

    public function sealOpen(string $payloadBase64, string $recipientPublicKey, string $recipientSecretKey): string;

    public function sealOpenUrlSafe(string $payloadUrlSafe, string $recipientPublicKey, string $recipientSecretKey): string;

    // ─── 4. Digital Signatures ──────────────────────────────────

    public function sign(mixed $message, string $secretKeyBase64, bool $useBinary = false): string;

    public function verify(string $signature, mixed $message, string $publicKeyBase64, bool $useBinary = false): bool;

    public function signInit(): string;

    public function signUpdate(string &$state, string $chunk): void;

    public function signFinalCreate(string $state, string $secretKeyBase64, bool $useBinary = false): string;

    public function signFinalVerify(string $state, string $signatureBase64, string $publicKeyBase64): bool;

    // ─── 5. Password Hashing ────────────────────────────────────

    // ─── 6. File / Stream Encryption ────────────────────────────

    public function encryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void;

    public function decryptFile(string $sourcePath, string $destPath, ?string $keyBase64 = null): void;

    /**
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function encryptStream(mixed $inputStream, mixed $outputStream, string $keyBase64): void;

    /**
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function decryptStream(mixed $inputStream, mixed $outputStream, string $keyBase64): void;

    // ─── 7. Hybrid Stream Encryption ────────────────────────────

    /**
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function sealStream(mixed $inputStream, mixed $outputStream, string $recipientPublicKey): void;

    /**
     * @param  resource  $inputStream
     * @param  resource  $outputStream
     */
    public function openSealedStream(
        mixed $inputStream,
        mixed $outputStream,
        string $recipientPublicKey,
        string $recipientSecretKey,
    ): void;

    // ─── 8. Stream Signatures ───────────────────────────────────

    /** @param resource $inputStream */
    public function signStream(mixed $inputStream, string $secretKeyBase64, bool $useBinary = false): string;

    /** @param resource $inputStream */
    public function verifyStreamSignature(mixed $inputStream, string $signatureBase64, string $publicKeyBase64): bool;

    // ─── 9. Hashing & KDF ───────────────────────────────────────

    public function hash(string $message, string $keyBase64 = '', int $length = SODIUM_CRYPTO_GENERICHASH_BYTES): string;

    public function kdfDerive(string $masterKeyBase64, int $subkeyId, string $context, int $length = 32): string;

    // ─── 10. Large String Helpers ───────────────────────────────

    public function encryptHugeString(string $hugeText, string $keyBase64): string;

    public function decryptHugeString(string $encryptedBase64, string $keyBase64): string;
}
