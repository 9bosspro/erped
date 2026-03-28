<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * RsaHelperInterface — สัญญาสำหรับ RSA Encryption Helper
 *
 * ครอบคลุม:
 *  - Key management (สร้าง, ตรวจสอบ, แปลง format, แยก public key)
 *  - RSA encrypt/decrypt (OAEP-SHA256)
 *  - RSA sign/verify (PSS-SHA256)
 *  - Hybrid encryption (RSA + AES-256-GCM)
 *  - Signed payload (JSON + timestamp, anti-replay)
 */
interface RsaHelperInterface
{
    // ─── Key Management ────────────────────────────────────────

    public function withKeys(?string $privateKey = null, ?string $publicKey = null): static;

    public function getPrivateKey(): ?string;

    public function getPublicKey(): ?string;

    /** @return array{private: string, public: string} */
    public function generateKeyPair(int $bits = 4096): array;

    /** @return array{private: string, public: string} */
    public function generateProtectedKeyPair(int $bits = 4096, string $passphrase = ''): array;

    public function extractPublicKey(string $privateKeyPem): string;

    public function isKeyPairMatch(string $privateKeyPem, string $publicKeyPem): bool;

    // ─── Key Inspection ────────────────────────────────────────

    public function getKeyType(string $key): string;

    public function isPrivateKey(string $key): bool;

    public function isPublicKey(string $key): bool;

    public function isValidKey(string $key): bool;

    /** @return array{type: string, bits: int, fingerprint: string, max_encrypt_bytes: int} */
    public function getKeyInfo(string $key): array;

    public function getKeyFingerprint(string $key): string;

    public function getKeySize(string $key): int;

    public function getMaxEncryptSize(?string $key = null): int;

    // ─── Key Format Conversion ─────────────────────────────────

    public function convertToPkcs1(string $key): string;

    public function convertToPkcs8(string $key): string;

    public function loadKeyFromFile(string $path): string;

    // ─── RSA Encrypt / Decrypt ─────────────────────────────────

    public function encrypt(string $data, ?string $publicKeyPem = null): string;

    public function decrypt(string $encryptedBase64, ?string $privateKeyPem = null): string;

    public function encryptData(mixed $data, ?string $publicKeyPem = null): string;

    public function decryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed;

    // ─── RSA Sign / Verify ─────────────────────────────────────

    public function sign(string $data, ?string $privateKeyPem = null): string;

    public function verifySignature(string $data, string $signatureBase64, ?string $publicKeyPem = null): bool;

    public function signData(mixed $data, ?string $privateKeyPem = null): string;

    public function verifyDataSignature(mixed $data, string $signatureBase64, ?string $publicKeyPem = null): bool;

    /** @return array{data: array, signature: string} */
    public function signPayload(array $payload): array;

    public function verifyPayload(array $signedPayload, int $maxAgeSeconds = 300): bool;

    // ─── Hybrid Encryption ─────────────────────────────────────

    public function hybridEncrypt(string $data, ?string $publicKeyPem = null): string;

    public function hybridDecrypt(string $encryptedBase64, ?string $privateKeyPem = null): string;

    /** @return array{v: int, cipher: string, encrypted_key: string, iv: string, tag: string, data: string} */
    public function hybridEncryptEnvelope(mixed $data, ?string $publicKeyPem = null): array;

    public function hybridDecryptEnvelope(array $envelope, ?string $privateKeyPem = null): mixed;

    public function hybridEncryptData(mixed $data, ?string $publicKeyPem = null): string;

    public function hybridDecryptData(string $encryptedBase64, ?string $privateKeyPem = null): mixed;
}
