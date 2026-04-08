<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

/**
 * HashHelperInterface — สัญญาสำหรับ Hash Helper
 *
 * ครอบคลุม:
 *  - Standard Hash        (hash, verifyHash)
 *  - Salted Hash          (hashWithSalt, verifySaltedHash)
 *  - Double-Salt Hash     (hashWithDoubleSalt, verifyDoubleSalt)
 *  - HMAC Sign/Verify     (hmacSign, hmacVerify)
 *  - Streaming Hash       (hashStream, hmacStream, hashChunked)
 *  - File Checksum        (fileChecksum, verifyFileChecksum, fileHmac, verifyFileHmac)
 *  - Content Fingerprint  (fingerprint)
 *  - HKDF Key Derivation  (hkdf)
 *  - Utility              (equals, getAvailableAlgorithms, isAlgorithmSupported, getHashLength)
 */
interface HashHelperInterface
{
    // ─── Standard Hash ──────────────────────────────────────────

    public function hash(mixed $data, string $algorithm = 'sha3-256', bool $binary = false): string;

    public function verifyHash(mixed $data, string $hash, string $algorithm = 'sha3-256', bool $binary = false): bool;

    // ─── Salted Hash ────────────────────────────────────────────

    public function hashWithSalt(mixed $data, string $algorithm = 'sha3-256'): string;

    public function verifySaltedHash(mixed $data, string $saltedHash, string $algorithm = 'sha3-256'): bool;

    // ─── Double-Salt Hash ────────────────────────────────────────

    public function hashWithDoubleSalt(mixed $input): string;

    public function verifyDoubleSalt(mixed $input, string $expectedHash): bool;

    // ─── HMAC Sign / Verify ─────────────────────────────────────

    public function hmacSign(
        mixed $data,
        ?string $key = null,
        string $algorithm = 'sha3-256',
        bool $binary = false,
    ): string;

    public function hmacVerify(
        mixed $data,
        string $signature,
        ?string $key = null,
        string $algorithm = 'sha3-256',
        bool $binary = false,
    ): bool;

    // ─── Streaming / Incremental ────────────────────────────────

    /** @param resource $stream */
    public function hashStream($stream, string $algorithm = 'sha3-256', int $chunkSize = 8192): string;

    /** @param resource $stream */
    public function hmacStream($stream, ?string $key = null, string $algorithm = 'sha3-256', int $chunkSize = 8192): string;

    public function hashChunked(iterable $chunks, string $algorithm = 'sha3-256'): string;

    // ─── File Checksum ──────────────────────────────────────────

    public function fileChecksum(string $filePath, string $algorithm = 'sha3-256'): string;

    public function verifyFileChecksum(string $filePath, string $expectedChecksum, string $algorithm = 'sha3-256'): bool;

    public function fileHmac(string $filePath, ?string $key = null, string $algorithm = 'sha3-256'): string;

    public function verifyFileHmac(string $filePath, string $expectedHmac, ?string $key = null, string $algorithm = 'sha3-256'): bool;

    // ─── Utility ────────────────────────────────────────────────

    public function equals(string $known, string $user): bool;

    /** @return string[] */
    public function getAvailableAlgorithms(): array;

    /** @return string[] */
    public function getAvailableHmacAlgorithms(): array;

    public function isAlgorithmSupported(string $algorithm): bool;

    public function getHashLength(string $algorithm): int;

    // ─── Key Derivation ──────────────────────────────────────────

    public function hkdf(
        string $ikm,
        int $length = 32,
        string $info = '',
        string $salt = '',
        string $algorithm = 'sha3-256',
    ): string;
}
