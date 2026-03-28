<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * EncryptionHelperInterface — สัญญาสำหรับ Symmetric Encryption Helper
 *
 * ครอบคลุม:
 *  - AES-256-GCM            (encrypt, decrypt, encryptWithAad, decryptWithAad)
 *  - AES-256-CBC + HMAC     (encryptCbc, decryptCbc)
 *  - XChaCha20-Poly1305     (encryptSodium, decryptSodium)
 *  - Sealed Box             (generateSodiumKeyPair, sealEncrypt, sealDecrypt)
 *  - Password-Based         (encryptWithPassword, decryptWithPassword)
 *  - Deterministic          (encryptDeterministic, decryptDeterministic)
 *  - Expiring               (encryptExpiring, decryptExpiring)
 *  - URL-Safe               (encryptUrlSafe, decryptUrlSafe)
 *  - Stream                 (encryptStream, decryptStream)
 *  - Key Rotation / Utility (autoDecrypt, reEncrypt, decryptWithFallbackKeys, generateKey)
 *  - Base64 URL-Safe        (base64UrlEncode, base64UrlDecode)
 */
interface EncryptionHelperInterface
{
    // ─── AES-256-GCM ────────────────────────────────────────────

    public function encrypt(mixed $data, ?string $key = null): string;

    public function decrypt(string $encrypted, ?string $key = null): mixed;

    public function encryptWithAad(mixed $data, string $aad, ?string $key = null): string;

    public function decryptWithAad(string $encrypted, string $aad, ?string $key = null): mixed;

    // ─── AES-256-CBC + HMAC ─────────────────────────────────────

    public function encryptCbc(mixed $data, ?string $key = null): string;

    public function decryptCbc(string $encrypted, ?string $key = null): mixed;

    // ─── XChaCha20-Poly1305 ─────────────────────────────────────

    public function encryptSodium(mixed $data, ?string $key = null): string;

    public function decryptSodium(string $encrypted, ?string $key = null): mixed;

    // ─── Sealed Box ─────────────────────────────────────────────

    /** @return array{public_key: string, secret_key: string, key_pair: string} */
    public function generateSodiumKeyPair(): array;

    public function sealEncrypt(mixed $data, string $publicKeyBase64): string;

    public function sealDecrypt(string $encrypted, string $keyPairBase64): mixed;

    // ─── Password-Based ─────────────────────────────────────────

    public function encryptWithPassword(mixed $data, string $password, int $iterations = 100_000): string;

    public function decryptWithPassword(string $encrypted, string $password): mixed;

    // ─── Deterministic ──────────────────────────────────────────

    public function encryptDeterministic(mixed $data, ?string $key = null): string;

    public function decryptDeterministic(string $encrypted, ?string $key = null): mixed;

    // ─── Expiring ───────────────────────────────────────────────

    public function encryptExpiring(mixed $data, int $ttlSeconds = 300, ?string $key = null): string;

    public function decryptExpiring(string $encrypted, ?string $key = null): mixed;

    // ─── URL-Safe ───────────────────────────────────────────────

    public function encryptUrlSafe(mixed $data, ?string $key = null): string;

    public function decryptUrlSafe(string $encrypted, ?string $key = null): mixed;

    // ─── Stream ─────────────────────────────────────────────────

    /** @param resource $inputStream @param resource $outputStream */
    public function encryptStream($inputStream, $outputStream, ?string $key = null): string;

    /** @param resource $inputStream @param resource $outputStream */
    public function decryptStream($inputStream, $outputStream, string $headerBase64, ?string $key = null): void;

    // ─── Key Rotation / Utility ─────────────────────────────────

    public function autoDecrypt(string $encrypted, ?string $key = null): mixed;

    public function reEncrypt(string $encrypted, string $oldKey, string $newKey): string;

    /** @param string[] $keys */
    public function decryptWithFallbackKeys(string $encrypted, array $keys): mixed;

    public function generateKey(): string;

    // ─── Base64 URL-Safe ────────────────────────────────────────

    public function base64UrlEncode(string $data): string;

    public function base64UrlDecode(string $data): string;

    /** @return string[] */
    public function getAvailableCiphers(): array;
}
