<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Contracts;

use DateTimeImmutable;
use Lcobucci\JWT\Token\Plain;

/**
 * JwtHelperInterface — สัญญาสำหรับ JWT Token Helper
 *
 * ครอบคลุม:
 *  - Token Creation (createAccessToken, createRefreshToken, issueTokenPair, buildCustomToken,
 *                    createScopedToken, createOneTimeToken)
 *  - Token Parsing  (parse, parseSafe, parseUnvalidated, verifySignatureOnly, validateToken)
 *  - Token Refresh  (refreshTokenPair)
 *  - Claim Access   (getPayload, getClaim, getClaimUnvalidated, getUserId, getTokenType, getJti)
 *  - Scope Checking (getScopes, hasScope, hasAnyScope)
 *  - Token Type     (isAccessToken, isRefreshToken, isOneTimeToken)
 *  - Token Metadata (isExpired, getRemainingTtl, getExpirationTime, getIssuedAt, getHeader)
 *  - Utility        (fingerprint, rawDecode)
 *  - Config Getters (getAlgorithm, getAccessTtl, getRefreshTtl, getIssuer, getAudience, isAsymmetric)
 */
interface JwtHelperInterface
{
    // ─── Token Creation ─────────────────────────────────────────

    public function createAccessToken(int $userId, array $claims = []): string;

    public function createRefreshToken(int $userId): string;

    /** @return array{access_token: string, refresh_token: string, expires_in: int} */
    public function issueTokenPair(int $userId, array $claims = []): array;

    public function buildCustomToken(mixed $data, int $ttl = 3600, array $claims = []): string;

    public function createScopedToken(int $userId, array $scopes, ?int $ttl = null, array $claims = []): string;

    /** @return array{token: string, jti: string, expires_at: int} */
    public function createOneTimeToken(mixed $data, int $ttl = 900, string $purpose = 'one_time'): array;

    // ─── Token Parsing ──────────────────────────────────────────

    public function parse(string $token): Plain;

    public function parseSafe(string $token): ?Plain;

    public function parseUnvalidated(string $token): ?Plain;

    public function verifySignatureOnly(string $token): ?Plain;

    public function validateToken(string $token): bool;

    // ─── Token Refresh ──────────────────────────────────────────

    /** @return array{access_token: string, refresh_token: string, expires_in: int} */
    public function refreshTokenPair(string $refreshToken, array $claims = []): array;

    // ─── Claim Access ───────────────────────────────────────────

    public function getPayload(string $token): ?array;

    public function getClaim(string $token, string $claimName): mixed;

    public function getClaimUnvalidated(string $token, string $claimName): mixed;

    public function getUserId(string $token): ?int;

    public function getTokenType(string $token): ?string;

    public function getJti(string $token): ?string;

    /** @return string[] */
    public function getScopes(string $token): array;

    public function getSubject(string $token): ?string;

    public function getRegisteredClaims(string $token): array;

    // ─── Scope Checking ─────────────────────────────────────────

    public function hasScope(string $token, string|array $requiredScopes): bool;

    public function hasAnyScope(string $token, array $anyScopes): bool;

    // ─── Token Type ─────────────────────────────────────────────

    public function isAccessToken(string $token): bool;

    public function isRefreshToken(string $token): bool;

    public function isOneTimeToken(string $token): bool;

    // ─── Token Metadata ─────────────────────────────────────────

    public function isExpired(string $token): bool;

    public function getRemainingTtl(string $token): int;

    public function getExpirationTime(string $token): ?DateTimeImmutable;

    public function getIssuedAt(string $token): ?DateTimeImmutable;

    public function getHeader(string $token): ?array;

    // ─── Utility ────────────────────────────────────────────────

    public function fingerprint(string $token): string;

    public function rawDecode(string $token): ?array;

    // ─── Config Getters ─────────────────────────────────────────

    public function getAlgorithm(): string;

    public function getAccessTtl(): int;

    public function getRefreshTtl(): int;

    public function getIssuer(): string;

    public function getAudience(): string;

    public function isAsymmetric(): bool;
}
