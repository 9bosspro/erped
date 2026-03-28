<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Exceptions\InvalidTokenException;
use Core\Base\Support\Helpers\Crypto\Contracts\JwtHelperInterface;
use DateTimeImmutable;
use DateTimeZone;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HS256;
use Lcobucci\JWT\Signer\Hmac\Sha384 as HS384;
use Lcobucci\JWT\Signer\Hmac\Sha512 as HS512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RS256;
use Lcobucci\JWT\Signer\Rsa\Sha384 as RS384;
use Lcobucci\JWT\Signer\Rsa\Sha512 as RS512;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint;
use Illuminate\Support\Str;

/**
 * JwtHelper — JWT Token Helper ที่สมบูรณ์ครบวงจร
 *
 * ใช้ lcobucci/jwt v5.x เป็น library หลัก
 *
 * ═══════════════════════════════════════════════════════════════
 *  Token Creation
 * ═══════════════════════════════════════════════════════════════
 *  createAccessToken($userId, $claims)   — สร้าง access token
 *  createRefreshToken($userId)           — สร้าง refresh token
 *  issueTokenPair($userId, $claims)      — สร้างทั้ง access + refresh
 *  buildCustomToken($data, $ttl, $claims)— สร้าง custom token
 *  createScopedToken($userId, $scopes)   — สร้าง token พร้อม scopes/permissions
 *  createOneTimeToken($data, $ttl)       — สร้าง single-use token (ต้อง revoke หลังใช้)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Token Parsing & Validation
 * ═══════════════════════════════════════════════════════════════
 *  parse($token)                  — parse + validate ครบ (throw ถ้าไม่ผ่าน)
 *  parseSafe($token)              — parse + validate ไม่ throw (return null)
 *  parseUnvalidated($token)       — parse โดยไม่ validate (สำหรับอ่าน expired token)
 *  verifySignatureOnly($token)    — verify signature อย่างเดียว (ไม่ตรวจ exp/nbf)
 *  validateToken($token)          — validate โดยไม่ throw (return bool)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Token Refresh
 * ═══════════════════════════════════════════════════════════════
 *  refreshTokenPair($refreshToken, $claims) — ใช้ refresh token ออก pair ใหม่
 *
 * ═══════════════════════════════════════════════════════════════
 *  Claim Accessors
 * ═══════════════════════════════════════════════════════════════
 *  getPayload($token)             — ดึง payload ทั้งหมดเป็น array
 *  getClaim($token, $name)        — ดึง claim เดียว (validated)
 *  getClaimUnvalidated($token, $name) — ดึง claim โดยไม่ validate
 *  getUserId($token)              — ดึง user_id
 *  getTokenType($token)           — ดึง type (access/refresh)
 *  getJti($token)                 — ดึง JTI
 *  getScopes($token)              — ดึง scopes
 *  getSubject($token)             — ดึง sub claim
 *  getRegisteredClaims($token)    — ดึง standard claims (iss, sub, aud, exp, nbf, iat, jti)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Token Inspection (ไม่ต้อง validate)
 * ═══════════════════════════════════════════════════════════════
 *  isExpired($token)              — ตรวจว่าหมดอายุหรือยัง
 *  isAccessToken($token)          — ตรวจว่าเป็น access token
 *  isRefreshToken($token)         — ตรวจว่าเป็น refresh token
 *  getRemainingTtl($token)        — เวลาที่เหลือก่อน expire (วินาที)
 *  getExpirationTime($token)      — ดึง expiration time
 *  getIssuedAt($token)            — ดึง issued time
 *  getHeader($token)              — ดึง header (alg, typ, kid)
 *  fingerprint($token)            — สร้าง hash สำหรับ logging (ห้าม log token จริง)
 *  rawDecode($token)              — raw base64 decode สำหรับ debug
 *  hasScope($token, $scope)       — ตรวจว่ามี scope ที่ต้องการหรือไม่
 *
 * ═══════════════════════════════════════════════════════════════
 *  Configuration
 * ═══════════════════════════════════════════════════════════════
 *  getAlgorithm()                 — algorithm ที่ใช้อยู่
 *  getAccessTtl() / getRefreshTtl() — TTL ปัจจุบัน
 *
 * ─── ความปลอดภัย ────────────────────────────────────────────
 *  - รองรับ RS256/RS384/RS512 (asymmetric) + HS256/HS384/HS512 (symmetric)
 *  - Validation constraints: iss, aud, exp, nbf, iat, signature
 *  - ⚠️ JWT payload ไม่ได้ encrypt — ห้ามเก็บ sensitive data
 *  - fingerprint() สำหรับ safe logging
 */
final class JwtHelper implements JwtHelperInterface
{
    private Configuration $config;

    private readonly int $accessTtl;

    private readonly int $refreshTtl;

    private readonly string $issuer;

    private readonly string $audience;

    private readonly string $algorithm;

    public function __construct()
    {
        $this->algorithm = (string) config('crypto.jwt.algorithm', 'RS256');
        $this->issuer = (string) config('crypto.jwt.issuer', config('app.url', 'http://localhost'));
        $this->audience = (string) config('crypto.jwt.audience', config('app.url', 'http://localhost'));
        $this->accessTtl = (int) config('crypto.jwt.access_ttl', 3600);
        $this->refreshTtl = (int) config('crypto.jwt.refresh_ttl', 2592000);

        $config = $this->isAsymmetric()
            ? $this->configureRsa()
            : $this->configureHmac();

        $timezone = new DateTimeZone((string) config('app.timezone', 'Asia/Bangkok'));

        $this->config = $config->withValidationConstraints(
            new Constraint\IssuedBy($this->issuer),
            new Constraint\PermittedFor($this->audience),
            new Constraint\StrictValidAt(new SystemClock($timezone)),
            new Constraint\SignedWith(
                $config->signer(),
                $config->verificationKey(),
            ),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Creation
    // ═══════════════════════════════════════════════════════════

    /**
     * สร้าง Access Token
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $claims  custom claims (เช่น role, permissions)
     * @return string JWT token string
     */
    public function createAccessToken(int $userId, array $claims = []): string
    {
        return $this->buildToken($userId, 'access', $this->accessTtl, $claims);
    }

    /**
     * สร้าง Refresh Token
     *
     * ไม่ใส่ role/permissions — ดึงจาก DB ใหม่ตอน refresh เพื่อให้ได้ข้อมูลล่าสุด
     *
     * @param  int  $userId  User ID
     * @return string JWT token string
     */
    public function createRefreshToken(int $userId): string
    {
        return $this->buildToken($userId, 'refresh', $this->refreshTtl);
    }

    /**
     * สร้างทั้ง access + refresh token พร้อม metadata
     *
     * @param  int  $userId  User ID
     * @param  array<string, mixed>  $claims  custom claims สำหรับ access token
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function issueTokenPair(int $userId, array $claims = []): array
    {
        return [
            'access_token' => $this->createAccessToken($userId, $claims),
            'refresh_token' => $this->createRefreshToken($userId),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
        ];
    }

    /**
     * สร้าง custom JWT token
     *
     * @param  mixed  $data  ข้อมูลหลักที่ต้องการฝัง (เก็บใน claim 'data')
     * @param  int  $ttl  อายุ token (วินาที, default 3600)
     * @param  array<string, mixed>  $claims  custom claims เพิ่มเติม
     * @return string JWT token string
     */
    public function buildCustomToken(mixed $data, int $ttl = 3600, array $claims = []): string
    {
        $now = new DateTimeImmutable();
        $jti = Str::orderedUuid()->toString();

        $builder = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$ttl} seconds"))
            ->withClaim('data', $data);

        foreach ($claims as $key => $value) {
            $builder = $builder->withClaim($key, $value);
        }

        return $builder
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    /**
     * สร้าง token พร้อม scopes/permissions
     *
     * เหมาะสำหรับ: API ที่ต้องตรวจ scope (เช่น "read:users", "write:posts")
     *
     * @param  int  $userId  User ID
     * @param  string[]  $scopes  รายการ scope ที่อนุญาต
     * @param  int|null  $ttl  อายุ token (null = ใช้ accessTtl)
     * @param  array<string, mixed>  $claims  custom claims เพิ่มเติม
     * @return string JWT token string
     */
    public function createScopedToken(int $userId, array $scopes, ?int $ttl = null, array $claims = []): string
    {
        $claims['scopes'] = array_values(array_unique($scopes));

        return $this->buildToken($userId, 'access', $ttl ?? $this->accessTtl, $claims);
    }

    /**
     * สร้าง single-use token (one-time)
     *
     * เหมาะสำหรับ: email verification, password reset, invite link
     * ⚠️ ต้อง revoke ด้วย TokenBlacklistService หลังใช้งาน (JWT ไม่มี built-in one-time)
     *
     * @param  mixed  $data  ข้อมูลที่ฝังใน token
     * @param  int  $ttl  อายุ token (วินาที, default 900 = 15 นาที)
     * @param  string  $purpose  วัตถุประสงค์ (เช่น 'email_verify', 'password_reset')
     * @return array{token: string, jti: string} token + jti สำหรับ track/revoke
     */
    public function createOneTimeToken(mixed $data, int $ttl = 900, string $purpose = 'one_time'): array
    {
        $now = new DateTimeImmutable();
        $jti = Str::orderedUuid()->toString();

        $token = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$ttl} seconds"))
            ->withClaim('data', $data)
            ->withClaim('type', $purpose)
            ->withClaim('one_time', true)
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();

        return [
            'token' => $token,
            'jti' => $jti,
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Parsing & Validation
    // ═══════════════════════════════════════════════════════════

    /**
     * Parse + validate token ครบทุก constraint (signature, exp, nbf, iss, aud)
     *
     * @param  string  $token  JWT token string
     * @return Plain  parsed token object
     *
     * @throws InvalidTokenException เมื่อ token ไม่ถูกต้อง
     */
    public function parse(string $token): Plain
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            if (! $parsed instanceof Plain) {
                throw new InvalidTokenException('Token format ไม่ถูกต้อง');
            }

            $constraints = $this->config->validationConstraints();
            $this->config->validator()->assert($parsed, ...$constraints);

            return $parsed;
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new InvalidTokenException($e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse + validate แบบไม่ throw — return null ถ้าไม่ผ่าน
     *
     * @param  string  $token  JWT token string
     * @return Plain|null  parsed token หรือ null
     */
    public function parseSafe(string $token): ?Plain
    {
        try {
            return $this->parse($token);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Parse โดยไม่ validate — สำหรับอ่าน payload จาก expired token
     *
     * ⚠️ ห้ามใช้ตัดสินใจด้าน authorization — token อาจถูกปลอมแปลง
     *
     * @param  string  $token  JWT token string
     * @return Plain|null  parsed token หรือ null
     */
    public function parseUnvalidated(string $token): ?Plain
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            return $parsed instanceof Plain ? $parsed : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Verify signature อย่างเดียว — ไม่ตรวจ exp, nbf, iss, aud
     *
     * เหมาะสำหรับ: ตรวจว่า token ถูก sign ด้วย key ของเราจริง แม้จะ expire แล้ว
     * Use case: refresh flow ที่ต้องอ่าน user_id จาก expired access token
     *
     * @param  string  $token  JWT token string
     * @return Plain|null  parsed token (ถ้า signature ถูกต้อง) หรือ null
     */
    public function verifySignatureOnly(string $token): ?Plain
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            if (! $parsed instanceof Plain) {
                return null;
            }

            $signatureConstraint = new Constraint\SignedWith(
                $this->config->signer(),
                $this->config->verificationKey(),
            );

            if (! $this->config->validator()->validate($parsed, $signatureConstraint)) {
                return null;
            }

            return $parsed;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Validate token โดยไม่ throw — return bool
     *
     * @param  string  $token  JWT token string
     * @return bool  true ถ้า token valid ทุก constraint
     */
    public function validateToken(string $token): bool
    {
        return $this->parseSafe($token) !== null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Refresh
    // ═══════════════════════════════════════════════════════════

    /**
     * ใช้ refresh token ออก token pair ใหม่
     *
     * ขั้นตอน:
     * 1. Parse + validate refresh token (ต้อง valid ทุก constraint)
     * 2. ตรวจว่า type = 'refresh'
     * 3. ดึง user_id จาก token
     * 4. ออก access + refresh token ใหม่
     *
     * ⚠️ caller ต้อง revoke refresh token เก่าเองผ่าน TokenBlacklistService
     *
     * @param  string  $refreshToken  refresh token string
     * @param  array<string, mixed>  $claims  custom claims สำหรับ access token ใหม่
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, old_jti: string}
     *
     * @throws InvalidTokenException ถ้า token ไม่ถูกต้อง
     */
    public function refreshTokenPair(string $refreshToken, array $claims = []): array
    {
        $parsed = $this->parse($refreshToken);

        $type = $parsed->claims()->get('type');

        if ($type !== 'refresh') {
            throw new InvalidTokenException('ต้องใช้ refresh token เท่านั้น (ได้รับ type: ' . ($type ?? 'null') . ')');
        }

        $userId = $parsed->claims()->get('user_id');

        if (! is_int($userId) || $userId <= 0) {
            throw new InvalidTokenException('Refresh token ไม่มี user_id ที่ถูกต้อง');
        }

        $oldJti = $parsed->claims()->get('jti') ?? '';

        $pair = $this->issueTokenPair($userId, $claims);
        $pair['old_jti'] = $oldJti;

        return $pair;
    }

    // ═══════════════════════════════════════════════════════════
    //  Claim Accessors
    // ═══════════════════════════════════════════════════════════

    /**
     * ดึง payload ทั้งหมดจาก token เป็น array
     *
     * ⚠️ ใช้ parseUnvalidated — ไม่ verify signature, รองรับ expired token
     * ห้ามใช้ตัดสินใจด้าน authorization
     *
     * @param  string  $token  JWT token string
     * @return array<string, mixed>|null  payload array หรือ null
     */
    public function getPayload(string $token): ?array
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return null;
        }

        $claims = $parsed->claims()->all();

        // แปลง DateTimeImmutable → Unix timestamp ให้ใช้งานง่าย
        return array_map(
            static fn (mixed $v) => $v instanceof DateTimeImmutable ? $v->getTimestamp() : $v,
            $claims,
        );
    }

    /**
     * ดึง custom claim จาก validated token
     *
     * @param  string  $token  JWT token string
     * @param  string  $claimName  ชื่อ claim
     * @return mixed  ค่าของ claim หรือ null
     */
    public function getClaim(string $token, string $claimName): mixed
    {
        return $this->parseSafe($token)?->claims()->get($claimName);
    }

    /**
     * ดึง claim โดยไม่ validate token
     *
     * ⚠️ ห้ามใช้ตัดสินใจด้าน authorization
     *
     * @param  string  $token  JWT token string
     * @param  string  $claimName  ชื่อ claim
     * @return mixed  ค่าของ claim หรือ null
     */
    public function getClaimUnvalidated(string $token, string $claimName): mixed
    {
        return $this->parseUnvalidated($token)?->claims()->get($claimName);
    }

    /**
     * ดึง User ID จาก validated token
     *
     * @throws InvalidTokenException
     */
    public function getUserId(string $token): ?int
    {
        return $this->parse($token)->claims()->get('user_id');
    }

    /**
     * ดึงประเภท token (access / refresh)
     *
     * @throws InvalidTokenException
     */
    public function getTokenType(string $token): ?string
    {
        return $this->parse($token)->claims()->get('type');
    }

    /**
     * ดึง JTI (unique ID) สำหรับ blacklist / revoke
     *
     * @throws InvalidTokenException
     */
    public function getJti(string $token): ?string
    {
        return $this->parse($token)->claims()->get('jti');
    }

    /**
     * ดึง scopes จาก validated token
     *
     * @param  string  $token  JWT token string
     * @return string[]  รายการ scopes หรือ empty array
     *
     * @throws InvalidTokenException
     */
    public function getScopes(string $token): array
    {
        $scopes = $this->parse($token)->claims()->get('scopes');

        return is_array($scopes) ? $scopes : [];
    }

    /**
     * ดึง subject (sub) claim จาก validated token
     *
     * @throws InvalidTokenException
     */
    public function getSubject(string $token): ?string
    {
        return $this->parse($token)->claims()->get('sub');
    }

    /**
     * ดึง registered claims ทั้งหมด (RFC 7519 §4.1)
     *
     * @param  string  $token  JWT token string
     * @return array{iss: ?string, sub: ?string, aud: ?array, exp: ?int, nbf: ?int, iat: ?int, jti: ?string}
     */
    public function getRegisteredClaims(string $token): array
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return array_fill_keys(['iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti'], null);
        }

        $claims = $parsed->claims();

        $toTimestamp = static fn (mixed $v) => $v instanceof DateTimeImmutable ? $v->getTimestamp() : null;

        return [
            'iss' => $claims->get('iss'),
            'sub' => $claims->get('sub'),
            'aud' => $claims->get('aud'),
            'exp' => $toTimestamp($claims->get('exp')),
            'nbf' => $toTimestamp($claims->get('nbf')),
            'iat' => $toTimestamp($claims->get('iat')),
            'jti' => $claims->get('jti'),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Inspection (ไม่ต้อง validate)
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจว่า token หมดอายุหรือยัง
     *
     * @param  string  $token  JWT token string
     * @return bool  true = หมดอายุแล้ว (หรือ parse ไม่ได้)
     */
    public function isExpired(string $token): bool
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return true;
        }

        $exp = $parsed->claims()->get('exp');

        return ! $exp instanceof DateTimeImmutable || $exp < new DateTimeImmutable();
    }

    /**
     * ตรวจว่าเป็น access token หรือไม่
     *
     * @param  string  $token  JWT token string
     * @return bool  true = เป็น access token
     */
    public function isAccessToken(string $token): bool
    {
        return $this->parseUnvalidated($token)?->claims()->get('type') === 'access';
    }

    /**
     * ตรวจว่าเป็น refresh token หรือไม่
     *
     * @param  string  $token  JWT token string
     * @return bool  true = เป็น refresh token
     */
    public function isRefreshToken(string $token): bool
    {
        return $this->parseUnvalidated($token)?->claims()->get('type') === 'refresh';
    }

    /**
     * ตรวจว่าเป็น one-time token หรือไม่
     *
     * @param  string  $token  JWT token string
     * @return bool  true = เป็น one-time token
     */
    public function isOneTimeToken(string $token): bool
    {
        return $this->parseUnvalidated($token)?->claims()->get('one_time') === true;
    }

    /**
     * ดึงเวลาที่เหลือก่อน expire (วินาที)
     *
     * @param  string  $token  JWT token string
     * @return int  วินาทีที่เหลือ (0 = หมดอายุแล้ว, -1 = parse ไม่ได้)
     */
    public function getRemainingTtl(string $token): int
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return -1;
        }

        $exp = $parsed->claims()->get('exp');

        if (! $exp instanceof DateTimeImmutable) {
            return -1;
        }

        $remaining = $exp->getTimestamp() - time();

        return max(0, $remaining);
    }

    /**
     * ดึง expiration time จาก token
     *
     * @param  string  $token  JWT token string
     * @return DateTimeImmutable|null
     */
    public function getExpirationTime(string $token): ?DateTimeImmutable
    {
        $exp = $this->parseUnvalidated($token)?->claims()->get('exp');

        return $exp instanceof DateTimeImmutable ? $exp : null;
    }

    /**
     * ดึง issued at time จาก token
     *
     * @param  string  $token  JWT token string
     * @return DateTimeImmutable|null
     */
    public function getIssuedAt(string $token): ?DateTimeImmutable
    {
        $iat = $this->parseUnvalidated($token)?->claims()->get('iat');

        return $iat instanceof DateTimeImmutable ? $iat : null;
    }

    /**
     * ดึง header จาก token (alg, typ, kid ฯลฯ)
     *
     * @param  string  $token  JWT token string
     * @return array<string, mixed>|null  header array หรือ null
     */
    public function getHeader(string $token): ?array
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return null;
        }

        return $parsed->headers()->all();
    }

    /**
     * สร้าง fingerprint ของ token สำหรับ logging
     *
     * ⚠️ ห้าม log JWT token จริง — ใช้ fingerprint แทน
     * fingerprint = SHA-256(token)[0:16] — ไม่สามารถ reverse กลับเป็น token ได้
     *
     * @param  string  $token  JWT token string
     * @return string  16-char hex fingerprint (เช่น "a1b2c3d4e5f6a7b8")
     */
    public function fingerprint(string $token): string
    {
        return substr(hash('sha256', $token), 0, 16);
    }

    /**
     * Raw decode JWT token สำหรับ debug
     *
     * ⚠️ ไม่ verify signature — ใช้สำหรับ debug เท่านั้น
     *
     * @param  string  $token  JWT token string
     * @return array{header: array, payload: array, signature: string}|null
     */
    public function rawDecode(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (! is_array($header) || ! is_array($payload)) {
            return null;
        }

        // แปลง timestamp → readable date สำหรับ debug
        foreach (['exp', 'nbf', 'iat'] as $field) {
            if (isset($payload[$field]) && is_int($payload[$field])) {
                $payload["_{$field}_human"] = date('Y-m-d H:i:s', $payload[$field]);
            }
        }

        return [
            'header' => $header,
            'payload' => $payload,
            'signature' => $parts[2],
        ];
    }

    /**
     * ตรวจว่า token มี scope ที่ต้องการหรือไม่
     *
     * @param  string  $token  JWT token string
     * @param  string|string[]  $requiredScopes  scope ที่ต้องมี (string = ตรวจ 1 scope, array = ตรวจทุก scope)
     * @return bool  true ถ้ามีครบทุก scope ที่ต้องการ
     */
    public function hasScope(string $token, string|array $requiredScopes): bool
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return false;
        }

        $tokenScopes = $parsed->claims()->get('scopes');

        if (! is_array($tokenScopes)) {
            return false;
        }

        $required = is_string($requiredScopes) ? [$requiredScopes] : $requiredScopes;

        return array_diff($required, $tokenScopes) === [];
    }

    /**
     * ตรวจว่า token มี scope อย่างน้อย 1 ตัวจากที่กำหนด
     *
     * @param  string  $token  JWT token string
     * @param  string[]  $anyScopes  scope ที่ต้องมีอย่างน้อย 1
     * @return bool  true ถ้ามีอย่างน้อย 1 scope
     */
    public function hasAnyScope(string $token, array $anyScopes): bool
    {
        $parsed = $this->parseUnvalidated($token);

        if ($parsed === null) {
            return false;
        }

        $tokenScopes = $parsed->claims()->get('scopes');

        if (! is_array($tokenScopes)) {
            return false;
        }

        return array_intersect($anyScopes, $tokenScopes) !== [];
    }

    // ═══════════════════════════════════════════════════════════
    //  Configuration Getters
    // ═══════════════════════════════════════════════════════════

    /**
     * ดู algorithm ที่ใช้อยู่
     */
    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    /**
     * ดู access token TTL (วินาที)
     */
    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    /**
     * ดู refresh token TTL (วินาที)
     */
    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    /**
     * ดู issuer ที่ใช้
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }

    /**
     * ดู audience ที่ใช้
     */
    public function getAudience(): string
    {
        return $this->audience;
    }

    /**
     * ตรวจว่าใช้ asymmetric algorithm (RSA) หรือไม่
     */
    public function isAsymmetric(): bool
    {
        return str_starts_with($this->algorithm, 'RS');
    }

    // ─── Private: Token Building ────────────────────────────────

    private function buildToken(int $userId, string $type, int $ttl, array $claims = []): string
    {
        $now = new DateTimeImmutable();
        $jti = Str::orderedUuid()->toString();

        $builder = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy($jti)
            ->issuedAt($now)
            ->canOnlyBeUsedAfter($now)
            ->expiresAt($now->modify("+{$ttl} seconds"))
            ->relatedTo((string) $userId)
            ->withClaim('user_id', $userId)
            ->withClaim('type', $type);

        foreach ($claims as $key => $value) {
            $builder = $builder->withClaim($key, $value);
        }

        return $builder
            ->getToken($this->config->signer(), $this->config->signingKey())
            ->toString();
    }

    // ─── Private: Configuration ─────────────────────────────────

    private function configureRsa(): Configuration
    {
        $privateKey = (string) config('passport.private_key', '');
        $publicKey = (string) config('passport.public_key', '');

        $signer = match ($this->algorithm) {
            'RS384' => new RS384(),
            'RS512' => new RS512(),
            default => new RS256(),
        };

        return Configuration::forAsymmetricSigner(
            $signer,
            InMemory::plainText($privateKey),
            InMemory::plainText($publicKey),
        );
    }

    private function configureHmac(): Configuration
    {
        $key = (string) (config('crypto.jwt.secret') ?: config('app.key', ''));

        $signer = match ($this->algorithm) {
            'HS384' => new HS384(),
            'HS512' => new HS512(),
            default => new HS256(),
        };

        return Configuration::forSymmetricSigner(
            $signer,
            InMemory::plainText($key),
        );
    }

    // ─── Private: Utility ───────────────────────────────────────

    /**
     * Base64 URL-safe decode (JWT uses URL-safe base64 without padding)
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;

        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
