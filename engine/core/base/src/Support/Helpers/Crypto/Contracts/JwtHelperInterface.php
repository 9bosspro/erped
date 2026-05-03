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

    /** @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int} */
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

    /**
     * ตรวจ signature เท่านั้น — **ไม่ตรวจ expiry, nbf, issuer, audience**
     *
     * ⚠️ token หมดอายุก็ผ่าน method นี้ได้
     * สำหรับ authentication จริง ใช้ parse() หรือ validateToken() แทน
     */
    public function validateSignatureOnly(string $token): bool;

    // ─── Token Refresh ──────────────────────────────────────────

    /** @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int} */
    public function refreshTokenPair(string $refreshToken, array $claims = []): array;

    // ─── Claim Access ───────────────────────────────────────────

    /**
     * คืน payload ทั้งหมดจาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ใช้เพื่อ debug / log เท่านั้น — ห้ามนำไปใช้ตัดสินใจด้าน auth/authz
     */
    public function getPayload(string $token): ?array;

    /** ดึง claim จาก validated token (ตรวจ signature + expiry) */
    public function getClaim(string $token, string $claimName): mixed;

    /**
     * ดึง claim จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ข้อมูลที่ได้อาจถูก tampered — ใช้เฉพาะกรณีที่ต้องการอ่านก่อน validate เท่านั้น
     */
    public function getClaimUnvalidated(string $token, string $claimName): mixed;

    /**
     * ดึง user_id จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ห้ามใช้เพื่อ authenticate ผู้ใช้ — เหมาะสำหรับ refresh flow เท่านั้น
     */
    public function getUserId(string $token): ?int;

    /**
     * ดึงประเภท token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ค่าที่ได้อาจถูก forge — ใช้เพื่อ routing ก่อน validate เท่านั้น
     */
    public function getTokenType(string $token): ?string;

    /**
     * ดึง JWT ID (jti) **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ใช้สำหรับ blacklist lookup เบื้องต้น — ต้อง validate token ก่อนเชื่อ jti
     */
    public function getJti(string $token): ?string;

    /**
     * ดึง scopes **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ห้ามใช้ตัดสิน permission โดยตรง — ต้อง validate token ก่อนเสมอ
     *
     * @return string[]
     */
    public function getScopes(string $token): array;

    /**
     * ดึง subject (sub) **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ค่าที่ได้อาจถูก tampered — validate token ก่อนนำไปใช้งาน
     */
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

    /**
     * ตรวจว่า token หมดอายุหรือไม่ **โดยไม่ตรวจ signature**
     *
     * ⚠️ ใช้เพื่อ UI hint เท่านั้น — สำหรับ auth จริงใช้ parse()
     */
    public function isExpired(string $token): bool;

    /**
     * คืนเวลาคงเหลือ (วินาที) **โดยไม่ตรวจ signature** — คืน -1 ถ้าไม่มี exp
     *
     * ⚠️ ค่าที่ได้อาจไม่น่าเชื่อถือถ้า token ถูก tampered
     */
    public function getRemainingTtl(string $token): int;

    public function getExpirationTime(string $token): ?DateTimeImmutable;

    public function getIssuedAt(string $token): ?DateTimeImmutable;

    public function getHeader(string $token): ?array;

    // ─── Utility ────────────────────────────────────────────────

    /**
     * ดึง claim 'data' จาก token — ตรวจ signature แต่ไม่ตรวจ expiry
     *
     * ⚠️ token หมดอายุก็สามารถดึงข้อมูลได้ — เหมาะสำหรับ custom token เท่านั้น
     * สำหรับ auth จริงใช้ parse() แทน
     *
     * @throws \Core\Base\Exceptions\InvalidTokenException เมื่อ signature ไม่ถูกต้อง
     */
    public function parsedata(string $token): mixed;

    /**
     * สร้าง HMAC fingerprint ของ token ด้วย app.key
     *
     * ใช้สำหรับ: token deduplication, blacklist key, audit log
     */
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
