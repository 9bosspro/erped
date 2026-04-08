<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto;

use Core\Base\Exceptions\InvalidTokenException;
use Core\Base\Support\Helpers\Crypto\Concerns\ParsesEncryptionKey;
use Core\Base\Support\Helpers\Crypto\Contracts\JwtHelperInterface;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lcobucci\Clock\SystemClock;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Eddsa;
use Lcobucci\JWT\Signer\Hmac\Sha256 as HS256;
use Lcobucci\JWT\Signer\Hmac\Sha384 as HS384;
use Lcobucci\JWT\Signer\Hmac\Sha512 as HS512;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256 as RS256;
use Lcobucci\JWT\Signer\Rsa\Sha384 as RS384;
use Lcobucci\JWT\Signer\Rsa\Sha512 as RS512;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use RuntimeException;
use Throwable;

/**
 * JwtHelper — JWT Token Helper ที่สมบูรณ์ครบวงจร (Security Namespace)
 *
 * ใช้ lcobucci/jwt v5.x เป็น library หลัก
 */
final class JwtHelper implements JwtHelperInterface
{
    use ParsesEncryptionKey;

    private const int PARSE_CACHE_MAX = 8;

    private Configuration $config;

    private int $accessTtl;

    private int $refreshTtl;

    private string $issuer;

    private string $audience;

    private string $algorithm;

    /** Cache ของ parsed unvalidated tokens (keyed by sha256 ของ token, max 8 entries) */
    private array $parseCache = [];

    public function __construct()
    {
        $this->algorithm = (string) config('core.base::jwt.algorithm', 'EdDSA');
        $this->issuer = (string) config('core.base::jwt.issuer', config('app.url', 'http://localhost'));
        $this->audience = (string) config('core.base::jwt.audience', config('app.url', 'http://localhost'));
        $this->accessTtl = (int) config('core.base::jwt.access_ttl', 3600);
        $this->refreshTtl = (int) config('core.base::jwt.refresh_ttl', 2592000);

        $this->config = $this->switchJwt($this->algorithm);
    }

    /**
     * สร้างคู่กุญแจสำหรับ Digital Signatures (Ed25519)
     *
     * @return array{public: string, secret: string} Base64
     */
    public static function generateSignatureKeyPairforjwt(bool $useBase64 = false): array
    {
        $kp = \sodium_crypto_sign_keypair();
        $result = [
            'public' => $useBase64 ? self::encodeb64(\sodium_crypto_sign_publickey($kp)) : \sodium_crypto_sign_publickey($kp),
            'secret' => $useBase64 ? self::encodeb64(\sodium_crypto_sign_secretkey($kp)) : \sodium_crypto_sign_secretkey($kp),
            'keypair' => $useBase64 ? self::encodeb64($kp) : $kp,
        ];
        \sodium_memzero($kp);

        return $result;
    }

    public function switchJwt(
        ?string $algorithm = null,
        ?string $issuer = null,
        ?string $audience = null,
        ?string $privateKey = null,
        ?string $publicKey = null,
        ?string $secretKey = null,
    ): Configuration {
        if ($algorithm !== null) {
            $this->algorithm = $algorithm;
        }
        if ($issuer !== null) {
            $this->issuer = $issuer;
        }
        if ($audience !== null) {
            $this->audience = $audience;
        }

        $config = match (true) {
            $this->algorithm === 'EdDSA' => $this->configureEddsa($privateKey, $publicKey),
            str_starts_with($this->algorithm, 'RS') => $this->configureRsa($privateKey, $publicKey),
            default => $this->configureHmac($secretKey),
        };

        return $this->configjwt($config);
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Creation
    // ═══════════════════════════════════════════════════════════

    public function createAccessToken(int $userId, array $claims = []): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId ต้องมีค่ามากกว่า 0');
        }

        return $this->buildToken($userId, 'access', $this->accessTtl, $claims);
    }

    public function createRefreshToken(int $userId): string
    {
        if ($userId <= 0) {
            throw new InvalidArgumentException('userId ต้องมีค่ามากกว่า 0');
        }

        return $this->buildToken($userId, 'refresh', $this->refreshTtl);
    }

    public function issueTokenPair(int $userId, array $claims = []): array
    {
        return [
            'access_token' => $this->createAccessToken($userId, $claims),
            'refresh_token' => $this->createRefreshToken($userId),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
        ];
    }

    public function buildCustomToken(mixed $data, int $ttl = 3600, array $claims = []): string
    {
        $now = new DateTimeImmutable;
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

    public function createScopedToken(int $userId, array $scopes, ?int $ttl = null, array $claims = []): string
    {
        $claims['scopes'] = array_values(array_unique($scopes));

        return $this->buildToken($userId, 'access', $ttl ?? $this->accessTtl, $claims);
    }

    public function createOneTimeToken(mixed $data, int $ttl = 900, string $purpose = 'one_time'): array
    {
        $now = new DateTimeImmutable;
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
            'expires_at' => $now->modify("+{$ttl} seconds")->getTimestamp(),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Parsing & Validation
    // ═══════════════════════════════════════════════════════════

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
        } catch (Exception $e) {
            throw new InvalidTokenException($e->getMessage(), 0, $e);
        }
    }

    /**
     * ตรวจ signature เท่านั้น — **ไม่ตรวจ expiry, nbf, issuer, audience**
     *
     * ⚠️ token หมดอายุก็ผ่าน method นี้ได้
     * สำหรับ authentication จริง ใช้ parse() หรือ validateToken() แทน
     * เหมาะสำหรับ: ตรวจว่า token ถูก forge หรือไม่ก่อนทำ refresh flow
     */
    public function validateSignatureOnly(string $token): bool
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            if (! $parsed instanceof Plain) {
                return false;
            }

            return $this->config->validator()->validate(
                $parsed,
                new SignedWith($this->config->signer(), $this->config->verificationKey()),
            );
        } catch (Exception) {
            return false;
        }
    }

    /**
     * ดึง claim 'data' จาก token — ตรวจ signature แต่**ไม่ตรวจ expiry**
     *
     * ⚠️ token หมดอายุก็สามารถดึงข้อมูลได้ — ใช้สำหรับ one-time token / custom token
     * ที่ต้องการ decode payload ก่อน expire check เท่านั้น
     * สำหรับ authentication จริง ใช้ parse() แทน
     */
    public function parsedata(string $token): mixed
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            if (! $parsed instanceof Plain) {
                throw new InvalidTokenException('Token format ไม่ถูกต้อง');
            }

            if (! $this->config->validator()->validate(
                $parsed,
                new SignedWith($this->config->signer(), $this->config->verificationKey()),
            )) {
                throw new InvalidTokenException('ลายเซ็นไม่ถูกต้อง! ข้อมูลอาจถูกปลอมแปลง');
            }

            return $parsed->claims()->get('data');
        } catch (InvalidTokenException $e) {
            throw $e;
        } catch (Exception $e) {
            throw new InvalidTokenException($e->getMessage(), 0, $e);
        }
    }

    public function parseSafe(string $token): ?Plain
    {
        try {
            return $this->parse($token);
        } catch (Throwable) {
            return null;
        }
    }

    public function parseUnvalidated(string $token): ?Plain
    {
        $cacheKey = \hash('sha256', $token);

        if (isset($this->parseCache[$cacheKey])) {
            return $this->parseCache[$cacheKey];
        }

        try {
            $parsed = $this->config->parser()->parse($token);
            $result = $parsed instanceof Plain ? $parsed : null;
        } catch (Exception) {
            return null;
        }

        if ($result !== null) {
            if (\count($this->parseCache) >= self::PARSE_CACHE_MAX) {
                \array_shift($this->parseCache);
            }
            $this->parseCache[$cacheKey] = $result;
        }

        return $result;
    }

    public function verifySignatureOnly(string $token): ?Plain
    {
        try {
            $parsed = $this->config->parser()->parse($token);

            if (! $parsed instanceof Plain) {
                return null;
            }

            $signatureConstraint = new SignedWith(
                $this->config->signer(),
                $this->config->verificationKey(),
            );

            if (! $this->config->validator()->validate($parsed, $signatureConstraint)) {
                return null;
            }

            return $parsed;
        } catch (Exception) {
            return null;
        }
    }

    public function validateToken(string $token): bool
    {
        return $this->parseSafe($token) !== null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Refresh
    // ═══════════════════════════════════════════════════════════

    public function refreshTokenPair(string $refreshToken, array $claims = []): array
    {
        $parsed = $this->parse($refreshToken);
        $type = $parsed->claims()->get('type');

        if ($type !== 'refresh') {
            throw new InvalidTokenException('ต้องใช้ refresh token เท่านั้น');
        }

        $userId = $parsed->claims()->get('user_id');
        if (! \is_int($userId) || $userId <= 0) {
            throw new InvalidTokenException('Refresh token ไม่มี user_id ที่ถูกต้อง');
        }

        return $this->issueTokenPair($userId, $claims);
    }

    // ═══════════════════════════════════════════════════════════
    //  Claim Accessors
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน payload ทั้งหมดจาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ใช้เพื่อ debug / log เท่านั้น — ห้ามนำไปใช้ตัดสินใจด้าน auth/authz
     */
    public function getPayload(string $token): ?array
    {
        $parsed = $this->parseUnvalidated($token);
        if ($parsed === null) {
            return null;
        }

        $claims = $parsed->claims()->all();

        return array_map(
            static fn (mixed $v) => $v instanceof DateTimeImmutable ? $v->getTimestamp() : $v,
            $claims,
        );
    }

    /**
     * ดึง claim จาก validated token (ตรวจ signature + expiry)
     */
    public function getClaim(string $token, string $claimName): mixed
    {
        return $this->parseSafe($token)?->claims()->get($claimName);
    }

    /**
     * ดึง claim จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ข้อมูลที่ได้อาจถูก tampered — ใช้เฉพาะกรณีที่ต้องการอ่านก่อน validate เท่านั้น
     */
    public function getClaimUnvalidated(string $token, string $claimName): mixed
    {
        return $this->parseUnvalidated($token)?->claims()->get($claimName);
    }

    /**
     * ดึง user_id จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ห้ามใช้เพื่อ authenticate ผู้ใช้ — ใช้ parse() + claims()->get('user_id') แทน
     * เหมาะสำหรับ: อ่าน userId จาก expired token เพื่อ refresh flow เท่านั้น
     */
    public function getUserId(string $token): ?int
    {
        $id = $this->getClaimUnvalidated($token, 'user_id');

        return \is_int($id) && $id > 0 ? $id : null;
    }

    /**
     * ดึงประเภท token (access/refresh) **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ค่าที่ได้อาจถูก forge — ใช้เพื่อ routing ก่อน validate เท่านั้น
     */
    public function getTokenType(string $token): ?string
    {
        return $this->getClaimUnvalidated($token, 'type');
    }

    /**
     * ดึง JWT ID (jti) จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ใช้สำหรับ blacklist lookup เบื้องต้นเท่านั้น — ต้อง validate token ก่อนเชื่อ jti
     */
    public function getJti(string $token): ?string
    {
        return $this->getClaimUnvalidated($token, 'jti');
    }

    /**
     * ดึง scopes จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ห้ามใช้ตัดสิน permission โดยตรง — ต้อง validate token ก่อนเสมอ
     */
    public function getScopes(string $token): array
    {
        $scopes = $this->getClaimUnvalidated($token, 'scopes');

        return \is_array($scopes) ? $scopes : [];
    }

    /**
     * ดึง subject (sub) จาก token **โดยไม่ตรวจ signature/expiry**
     *
     * ⚠️ ค่าที่ได้อาจถูก tampered — validate token ก่อนนำไปใช้งาน
     */
    public function getSubject(string $token): ?string
    {
        return $this->getClaimUnvalidated($token, 'sub');
    }

    public function getRegisteredClaims(string $token): array
    {
        $parsed = $this->parseUnvalidated($token);
        if ($parsed === null) {
            return [];
        }

        $claims = $parsed->claims();
        $toTs = static fn (mixed $v) => $v instanceof DateTimeImmutable ? $v->getTimestamp() : null;

        return [
            'iss' => $claims->get('iss'),
            'sub' => $claims->get('sub'),
            'aud' => $claims->get('aud'),
            'exp' => $toTs($claims->get('exp')),
            'nbf' => $toTs($claims->get('nbf')),
            'iat' => $toTs($claims->get('iat')),
            'jti' => $claims->get('jti'),
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Scope Checking
    // ═══════════════════════════════════════════════════════════

    public function hasScope(string $token, string|array $requiredScopes): bool
    {
        $parsed = $this->parseSafe($token);
        if ($parsed === null) {
            return false;
        }

        $tokenScopes = $parsed->claims()->get('scopes') ?? [];
        $required = \is_string($requiredScopes) ? [$requiredScopes] : $requiredScopes;

        return array_diff($required, $tokenScopes) === [];
    }

    public function hasAnyScope(string $token, array $anyScopes): bool
    {
        $parsed = $this->parseSafe($token);
        if ($parsed === null) {
            return false;
        }

        $tokenScopes = $parsed->claims()->get('scopes') ?? [];

        return \count(\array_intersect($anyScopes, $tokenScopes)) > 0;
    }

    // ═══════════════════════════════════════════════════════════
    //  Token Metadata & Type
    // ═══════════════════════════════════════════════════════════

    public function isAccessToken(string $token): bool
    {
        return $this->getTokenType($token) === 'access';
    }

    public function isRefreshToken(string $token): bool
    {
        return $this->getTokenType($token) === 'refresh';
    }

    public function isOneTimeToken(string $token): bool
    {
        return $this->getClaimUnvalidated($token, 'one_time') === true;
    }

    /**
     * ตรวจว่า token หมดอายุหรือไม่ **โดยไม่ตรวจ signature**
     *
     * ⚠️ attacker อาจแก้ exp ใน token ปลอมได้ — ใช้เพื่อ UI hint เท่านั้น
     * สำหรับ auth จริง: ใช้ parse() ซึ่งตรวจ StrictValidAt พร้อม signature
     */
    public function isExpired(string $token): bool
    {
        $exp = $this->getExpirationTime($token);

        return $exp === null || $exp < new DateTimeImmutable;
    }

    /**
     * คืนเวลาคงเหลือ (วินาที) ของ token **โดยไม่ตรวจ signature**
     *
     * ⚠️ ค่าที่ได้อาจไม่น่าเชื่อถือถ้า token ถูก tampered
     * คืน -1 ถ้าไม่มี exp claim
     */
    public function getRemainingTtl(string $token): int
    {
        $exp = $this->getExpirationTime($token);

        return $exp ? max(0, $exp->getTimestamp() - time()) : -1;
    }

    public function getExpirationTime(string $token): ?DateTimeImmutable
    {
        $exp = $this->getClaimUnvalidated($token, 'exp');

        return $exp instanceof DateTimeImmutable ? $exp : null;
    }

    public function getIssuedAt(string $token): ?DateTimeImmutable
    {
        $iat = $this->getClaimUnvalidated($token, 'iat');

        return $iat instanceof DateTimeImmutable ? $iat : null;
    }

    public function getHeader(string $token): ?array
    {
        return $this->parseUnvalidated($token)?->headers()->all();
    }

    public function fingerprint(string $token): string
    {
        $secret = (string) config('app.key', '');
        if (\str_starts_with($secret, 'base64:')) {
            $secret = (string) \base64_decode(\substr($secret, 7));
        }

        // HMAC-SHA256 ด้วย app.key — attacker ที่ไม่รู้ secret ไม่สามารถ compute ได้
        return \hash_hmac('sha256', $token, $secret);
    }

    public function rawDecode(string $token): ?array
    {
        $parts = \explode('.', $token);
        if (\count($parts) !== 3) {
            return null;
        }

        // base64url → binary — คืน null ถ้า decode ล้มเหลว
        $b64url = static function (string $s): ?string {
            $padded = \str_pad(\strtr($s, '-_', '+/'), \strlen($s) + (4 - \strlen($s) % 4) % 4, '=');
            $decoded = \base64_decode($padded, true);

            return $decoded === false ? null : $decoded;
        };

        $header = $b64url($parts[0]);
        $payload = $b64url($parts[1]);

        if ($header === null || $payload === null) {
            return null;
        }

        return [
            'header' => \json_decode($header, true),
            'payload' => \json_decode($payload, true),
            'signature' => $parts[2],
        ];
    }

    // ═══════════════════════════════════════════════════════════
    //  Config Getters
    // ═══════════════════════════════════════════════════════════

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function getAccessTtl(): int
    {
        return $this->accessTtl;
    }

    public function getRefreshTtl(): int
    {
        return $this->refreshTtl;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getAudience(): string
    {
        return $this->audience;
    }

    public function isAsymmetric(): bool
    {
        return $this->algorithm === 'EdDSA' || str_starts_with($this->algorithm, 'RS');
    }

    private function configjwt(Configuration $config): Configuration
    {
        $timezone = new DateTimeZone((string) config('app.timezone', 'Asia/Bangkok'));
        $leeway = (int) config('core.base::jwt.leeway', 60);
        $this->config = $config->withValidationConstraints(
            new Constraint\IssuedBy($this->issuer),
            new Constraint\PermittedFor($this->audience),
            new Constraint\StrictValidAt(new SystemClock($timezone), new DateInterval("PT{$leeway}S")),
            new SignedWith(
                $config->signer(),
                $config->verificationKey(),
            ),
        );

        return $this->config;
    }

    // ─── Private Logic ──────────────────────────────────────────

    private function buildToken(int $userId, string $type, int $ttl, array $claims = []): string
    {
        $now = new DateTimeImmutable;
        $builder = $this->config->builder()
            ->issuedBy($this->issuer)
            ->permittedFor($this->audience)
            ->identifiedBy(Str::orderedUuid()->toString())
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

    private function configureHmac(?string $secretKey = null): Configuration
    {
        $signer = match ($this->algorithm) {
            'HS384' => new HS384,
            'HS512' => new HS512,
            default => new HS256,
        };

        $key = $secretKey ?? (string) config('app.key', '');

        if (\str_starts_with($key, 'base64:')) {
            $decoded = \base64_decode(\substr($key, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('HMAC key: base64 decode ล้มเหลว — ตรวจสอบ APP_KEY ใน .env');
            }
            $key = $decoded;
        }

        // HMAC-SHA256 ต้องการ key อย่างน้อย 32 bytes เพื่อความปลอดภัย
        if (\strlen($key) < 32) {
            throw new RuntimeException(
                'HMAC key ต้องมีความยาวอย่างน้อย 32 bytes — ปัจจุบัน '.\strlen($key).' bytes',
            );
        }

        return Configuration::forSymmetricSigner($signer, InMemory::plainText($key));
    }

    private function configureRsa(?string $priv = null, ?string $pub = null): Configuration
    {
        $signer = match ($this->algorithm) {
            'RS384' => new RS384,
            'RS512' => new RS512,
            default => new RS256,
        };

        $privateKey = $priv ?? (string) config('core.base::crypto.rsa.private_key', '');
        $publicKey = $pub ?? (string) config('core.base::crypto.rsa.public_key', '');
        $passphrase = (string) config('core.base::crypto.rsa.passphrase', '');

        $signingKey = is_file($privateKey) ? InMemory::file($privateKey, $passphrase) : InMemory::plainText($privateKey, $passphrase);
        $verificationKey = is_file($publicKey) ? InMemory::file($publicKey) : InMemory::plainText($publicKey);

        return Configuration::forAsymmetricSigner($signer, $signingKey, $verificationKey);
    }

    private function configureEddsa(?string $priv = null, ?string $pub = null): Configuration
    {
        $privateKey = $priv ?? (string) config('core.base::jwt.privatekey', '');
        $publicKey = $pub ?? (string) config('core.base::jwt.publickey', '');

        // Decode Base64 / Base64URL เฉพาะเมื่อไม่ใช่ PEM format
        // PEM keys ขึ้นต้นด้วย "-----BEGIN..." — ห้ามนำไป decode ซ้ำ
        if ($privateKey !== '' && ! \str_starts_with($privateKey, '-----')) {
            $decoded = \base64_decode(\rtrim(\strtr($privateKey, '-_', '+/'), '='), true);
            if ($decoded === false) {
                throw new RuntimeException('EdDSA private key: base64 decode ล้มเหลว — ตรวจสอบ config key');
            }
            $privateKey = $decoded;
        }

        if ($publicKey !== '' && ! \str_starts_with($publicKey, '-----')) {
            $decoded = \base64_decode(\rtrim(\strtr($publicKey, '-_', '+/'), '='), true);
            if ($decoded === false) {
                throw new RuntimeException('EdDSA public key: base64 decode ล้มเหลว — ตรวจสอบ config key');
            }
            $publicKey = $decoded;
        }

        if ($privateKey === '') {
            throw new RuntimeException('EdDSA private key ไม่ได้ตั้งค่า — ตรวจสอบ config core.base::jwt.privatekey');
        }

        if ($publicKey === '') {
            throw new RuntimeException('EdDSA public key ไม่ได้ตั้งค่า — ตรวจสอบ config core.base::jwt.publickey');
        }

        return Configuration::forAsymmetricSigner(
            new Eddsa,
            InMemory::plainText($privateKey),
            InMemory::plainText($publicKey),
        );
    }
}
