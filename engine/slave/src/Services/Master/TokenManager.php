<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Slave\Contracts\Master\TokenFlow;
use Slave\Contracts\Master\TokenStorageInterface;
use Slave\Services\Master\Storage\CacheTokenStorage;
use Slave\Services\Master\Storage\SessionTokenStorage;
use Slave\Traits\TokenExpiryTrait;
use Throwable;

/**
 * TokenManager — รับผิดชอบการจัดการ Token lifecycle กับ Master Server
 *
 * ทำหน้าที่เป็น orchestrator เท่านั้น:
 *  - ดึง / บันทึก / ล้าง token ผ่าน TokenStorageInterface
 *  - ขอ token ใหม่จาก Master ด้วย Sodium Signature
 *  - ตรวจสอบวันหมดอายุผ่าน TokenExpiryTrait
 *
 * Storage ถูก resolve แบบ lazy ผ่าน storage() — สลับ driver ได้ด้วย withTokenStore()
 */
class TokenManager
{
    use TokenExpiryTrait;

    private const int MIN_CACHE_TTL = 300;

    private const int CACHE_TTL_BUFFER = 300;

    private const int DEFAULT_EXPIRES = 43200;  // 12 ชั่วโมง

    private const int MANIFEST_TTL = 2592000; // 30 วัน

    private ?string $username = null;

    private ?string $password = null;

    private ?array $bodytoken = null;

    public function __construct(
        private readonly string $masterUrl,
        private string $clientId,
        private string $clientSecret,
        private readonly SodiumHelper $sodium,
        private readonly string $signatureSeed,
        private readonly string $publicBox,
        private ?string $tokenStoreName = null,
        private string $cacheSuffix = '',
    ) {}

    // ─── Fluent Builders (Immutable Clone Pattern) ────────────────────────────

    public function withCacheSuffix(string $suffix): static
    {
        $clone = clone $this;
        $clone->cacheSuffix = trim($suffix);

        return $clone;
    }

    public function withTokenStore(?string $storeName): static
    {
        $clone = clone $this;
        $clone->tokenStoreName = $storeName;

        return $clone;
    }

    public function withBody(?array $bodytoken = null): static
    {
        $clone = clone $this;
        $clone->bodytoken = $bodytoken;

        return $clone;
    }

    public function withCredentials(string $clientId, string $clientSecret): static
    {
        $clone = clone $this;
        $clone->clientId = $clientId;
        $clone->clientSecret = $clientSecret;

        return $clone;
    }

    public function withUserPassword(string $username, string $password): static
    {
        $clone = clone $this;
        $clone->username = $username;
        $clone->password = $password;

        return $clone;
    }

    // ─── Token Access ─────────────────────────────────────────────────────────

    public function getToken(TokenFlow $flow, string $scope): string
    {
        $cached = $this->getFromStorage($this->cacheKey($flow, $scope));

        if (\is_array($cached) && isset($cached['access_token'])) {
            return (string) $cached['access_token'];
        }

        if (\is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheToken($flow, $scope);
    }

    public function getRefreshToken(TokenFlow $flow, string $scope): ?string
    {
        $cached = $this->getFromStorage($this->cacheKey($flow, $scope));

        if (\is_array($cached) && isset($cached['refresh_token'])) {
            return (string) $cached['refresh_token'];
        }

        return null;
    }

    public function getTokenFromTokensStore(TokenFlow $flow, string $scope): mixed
    {
        return $this->storage()->get($this->cacheKey($flow, $scope));
    }

    public function isExpired(TokenFlow $flow, string $scope): bool
    {
        $cached = $this->storage()->get($this->cacheKey($flow, $scope));

        return $this->isExpiredData($cached, self::CACHE_TTL_BUFFER);
    }

    // ─── Token Persistence ────────────────────────────────────────────────────

    public function store(array $data, TokenFlow $flow, string $scope = ''): string
    {
        $accessToken = $data['access_token'] ?? null;

        if (! \is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException("Token storage security violation: missing 'access_token'.");
        }

        $rawExpires = $data['expires_in'] ?? self::DEFAULT_EXPIRES;
        $expiresIn = \is_int($rawExpires) ? $rawExpires : self::DEFAULT_EXPIRES;
        $cacheTtl = max(self::MIN_CACHE_TTL, $expiresIn - self::CACHE_TTL_BUFFER);

        $this->storeToStorage(
            $this->cacheKey($flow, $scope),
            [
                'access_token' => $accessToken,
                'token_type' => $data['token_type'] ?? 'Bearer',
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_in' => $expiresIn,
                'expires_at' => now()->addSeconds($expiresIn)->toIso8601String(),
            ],
            $cacheTtl,
        );

        return $accessToken;
    }

    // ─── Token Invalidation ───────────────────────────────────────────────────

    public function clear(TokenFlow $flow, string $scope): void
    {
        $this->forgetFromStorage($this->cacheKey($flow, $scope));
    }

    public function clearByKey(string $key): void
    {
        if (\is_string($key) && $key !== '') {
            $this->forgetFromStorage($key);
        }
    }

    public function clearAll(): void
    {
        $manifestKey = $this->manifestKey();
        $keys = $this->storage()->get($manifestKey, []);

        if (! \is_array($keys)) {
            return;
        }

        foreach ($keys as $key) {
            if (\is_string($key) && $key !== '') {
                $this->storage()->forget($key);
            }
        }

        $this->storage()->forget($manifestKey);
    }

    // ─── Debug & Introspection ────────────────────────────────────────────────

    /**
     * @return array<int, string>
     */
    public function getManifestKeys(): array
    {
        $keys = $this->storage()->get($this->manifestKey(), []);

        return \is_array($keys) ? $keys : [];
    }

    /**
     * @return array<string, mixed>
     */
    public function debugAllTokens(): array
    {
        return collect($this->getManifestKeys())
            ->filter(fn($key) => \is_string($key) && $key !== '')
            ->mapWithKeys(fn($key) => [$key => $this->getFromStorage($key)])
            ->all();
    }

    // ─── Private: Token Fetching ──────────────────────────────────────────────

    private function fetchAndCacheToken(TokenFlow $flow, string $scope): string
    {
        // บังคับ การเก็บข้อมูล หากต้องการ เก็บแบบ Session
        $isSessionToken = $flow->isSessionToken();
        if ($isSessionToken) {
            //  $this->withTokenStore('session');
            $this->tokenStoreName = 'session';
            //   dd('dfsd');
        }
        $payload = $this->buildGrantPayload($flow, $scope);
        $headers = $this->buildSignedHeaders($payload);
        $body = $this->requestTokenFromMaster($flow, $payload, $headers);

        return $this->store($flow->unwrapBody($body), $flow, $scope);
    }

    private function buildGrantPayload(TokenFlow $flow, string $scope): array
    {
        $payload = [
            'grant_type' => $flow->grantType(),
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope' => $scope,
        ];

        if (! empty($this->username) && ! empty($this->password)) {
            $payload['grant_type'] = 'password';
            $payload['username'] = $this->username;
            $payload['password'] = $this->password;
        }

        if ($flow->isNeedBodyToken() && ! empty($this->bodytoken)) {
            $payload = [...$payload, ...$this->bodytoken];
        }

        return $payload;
    }

    private function buildSignedHeaders(array $payload): array
    {
        $headers = [
            'X-Timestamp' => now()->toIso8601String(),
            'X-Client-ID' => $this->clientId,
        ];

        $keyPair = $this->sodium->generateSignatureKeyPair($this->signatureSeed);
        $privateKey = $keyPair['secret'] ?? '';

        if ($privateKey === '') {
            throw new RuntimeException('Invalid signature keypair: missing secret.');
        }

        $headers['X-Signature'] = $this->sodium->sign([...$payload, ...$headers], $privateKey);

        \sodium_memzero($privateKey);
        if (\is_string($keyPair['secret'] ?? null)) {
            \sodium_memzero($keyPair['secret']);
        }

        return $headers;
    }

    private function requestTokenFromMaster(TokenFlow $flow, array $payload, array $headers): array
    {
        try {
            $encryptedPayload = $this->sodium->hybridEncrypt($payload, $this->publicBox);

            $response = Http::asForm()
                ->withHeaders($headers)
                ->post(
                    "{$this->masterUrl}{$flow->endpoint()}",
                    ['encrypted_payload' => $encryptedPayload],
                );
        } catch (Throwable $e) {
            Log::critical('Master Token Fetch Error: Connection Failed', [
                'client_id' => $this->clientId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        if ($response->failed()) {
            Log::error('Master Auth Failed', [
                'client_id' => $this->clientId,
                'flow' => $flow->value,
                'status' => $response->status(),
            ]);

            throw new RuntimeException('ไม่สามารถยืนยันตัวตนกับ Master Server ได้');
        }

        $body = $response->json();

        if (! \is_array($body)) {
            throw new RuntimeException('Master Server returned invalid JSON response.');
        }

        return $body;
    }

    // ─── Private: Storage Abstraction ────────────────────────────────────────

    private function storage(): TokenStorageInterface
    {
        return match ($this->tokenStoreName) {
            'session' => new SessionTokenStorage,
            default => new CacheTokenStorage($this->tokenStoreName),
        };
    }

    private function getFromStorage(string $key): mixed
    {
        $cached = $this->storage()->get($key);

        if ($cached !== null && $this->isExpiredData($cached, self::CACHE_TTL_BUFFER)) {
            $this->storage()->forget($key);

            return null;
        }

        return $cached;
    }

    private function storeToStorage(string $key, array $payload, int $ttl): void
    {
        $this->storage()->put($key, $payload, $ttl);
        $this->recordToManifest($key);
    }

    private function forgetFromStorage(string $key, bool $updateManifest = true): void
    {
        $this->storage()->forget($key);

        if ($updateManifest && $key !== $this->manifestKey()) {
            $this->removeFromManifest($key);
        }
    }

    // ─── Private: Manifest Tracking ──────────────────────────────────────────

    private function manifestKey(): string
    {
        return "master_manifest:{$this->clientId}";
    }

    private function recordToManifest(string $key): void
    {
        $manifestKey = $this->manifestKey();
        $keys = $this->storage()->get($manifestKey, []);
        $keys = \is_array($keys) ? $keys : [];

        if (! \in_array($key, $keys, true)) {
            $keys[] = $key;
            $this->storage()->put($manifestKey, $keys, self::MANIFEST_TTL);
        }
    }

    private function removeFromManifest(string $key): void
    {
        $manifestKey = $this->manifestKey();
        $keys = $this->storage()->get($manifestKey, []);
        $keys = \is_array($keys) ? $keys : [];

        $filtered = array_values(array_filter($keys, fn($k) => $k !== $key));
        $this->storage()->put($manifestKey, $filtered, self::MANIFEST_TTL);
    }

    // ─── Private: Cache Key ───────────────────────────────────────────────────

    private function cacheKey(TokenFlow $flow, string $scope): string
    {
        $prefix = $flow->cachePrefix();
        $isSessionToken = $flow->isSessionToken();
        $fingerprint = '';

        if ($isSessionToken && ! app()->runningInConsole() && request()->userAgent() !== null) {
            $sessionDevice = app('core.session.device_fingerprint');
            // $fingerprint = ':' . $sessionDevice->fingerprint();
            $fingerprint = ':' . $sessionDevice->fingerprintWithAgent(session()->getId());
        }

        $base = "{$prefix}:{$this->clientId}{$fingerprint}:" . md5($scope);
        $base = hash('sha384', $base);

        return $this->cacheSuffix === ''
            ? $base
            : "{$base}:{$this->cacheSuffix}";
    }
}
