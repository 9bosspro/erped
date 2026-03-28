<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * HashHelperInterface — สัญญาสำหรับ Hash Helper
 *
 * ครอบคลุม:
 *  - Standard Hash (hash, hashData)
 *  - Salted Hash (hashWithSalt, verifySaltedHash)
 *  - Double-Salt Hash (hashWithDoubleSalt, verifyDoubleSalt)
 *  - HMAC Sign/Verify (hmacSign, hmacVerify)
 *  - Signature Hash (signatureHash, verifySignatureHash)
 *  - Streaming Hash (hashStream, hmacStream, hashChunked)
 *  - File Checksum (fileChecksum, verifyFileChecksum, fileHmac, verifyFileHmac)
 *  - Content Fingerprint (fingerprint)
 *  - HKDF Key Derivation (hkdf)
 *  - Utility (equals, getAvailableAlgorithms, isAlgorithmSupported, getHashLength)
 */
interface HashHelperInterface
{
    // ─── Standard Hash ──────────────────────────────────────────

    public function hash(string $data, string $algorithm = 'sha3-256', bool $binary = false): string;

    public function hashData(mixed $data, string $algorithm = 'sha3-256'): string;

    // ─── Salted Hash ────────────────────────────────────────────

    public function hashWithSalt(string $data, string $algorithm = 'sha3-256'): string;

    public function verifySaltedHash(string $data, string $saltedHash, string $algorithm = 'sha3-256'): bool;

    // ─── Double-Salt Hash ────────────────────────────────────────

    public function hashWithDoubleSalt(string $input): string;

    public function verifyDoubleSalt(string $input, string $expectedHash): bool;

    // ─── HMAC Sign / Verify ─────────────────────────────────────

    public function hmacSign(
        string|array $data,
        ?string $key = null,
        string $algorithm = 'sha256',
        bool $binary = false,
    ): string;

    public function hmacVerify(
        string|array $data,
        string $signature,
        ?string $key = null,
        string $algorithm = 'sha256',
        bool $binary = false,
    ): bool;

    // ─── Signature Hash ─────────────────────────────────────────

    public function signatureHash(mixed $data = '', bool $useDoubleSalt = true, string $algorithm = 'sha3-256'): string;

    public function verifySignatureHash(
        mixed $data = '',
        string $signature = '',
        bool $useDoubleSalt = true,
        string $algorithm = 'sha3-256',
    ): bool;

    // ─── Streaming / Incremental ────────────────────────────────

    /** @param resource $stream */
    public function hashStream($stream, string $algorithm = 'sha256', int $chunkSize = 8192): string;

    /** @param resource $stream */
    public function hmacStream($stream, ?string $key = null, string $algorithm = 'sha256', int $chunkSize = 8192): string;

    public function hashChunked(iterable $chunks, string $algorithm = 'sha256'): string;

    // ─── File Checksum ──────────────────────────────────────────

    public function fileChecksum(string $filePath, string $algorithm = 'sha256'): string;

    public function verifyFileChecksum(string $filePath, string $expectedChecksum, string $algorithm = 'sha256'): bool;

    public function fileHmac(string $filePath, ?string $key = null, string $algorithm = 'sha256'): string;

    public function verifyFileHmac(string $filePath, string $expectedHmac, ?string $key = null, string $algorithm = 'sha256'): bool;

    // ─── Content Fingerprint ────────────────────────────────────

    public function fingerprint(mixed $data, string $algorithm = 'sha256', int $length = 0): string;

    // ─── HKDF ───────────────────────────────────────────────────

    public function hkdf(
        string $inputKeyMaterial,
        int $length = 32,
        string $info = '',
        string $salt = '',
        string $algorithm = 'sha256',
    ): string;

    // ─── Utility ────────────────────────────────────────────────

    public function equals(string $known, string $user): bool;

    /** @return string[] */
    public function getAvailableAlgorithms(): array;

    /** @return string[] */
    public function getAvailableHmacAlgorithms(): array;

    public function isAlgorithmSupported(string $algorithm): bool;

    public function getHashLength(string $algorithm): int;
}
