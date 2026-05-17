<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Contracts\Master\TokenFlow;
use Throwable;

/**
 * MasterClientService — HTTP client หลักสำหรับติดต่อ Master Server
 *
 * แยกการบริหารจัดการ Transport layer ออกจาก Token Management ตามหลัก SRP
 * รับผิดชอบเฉพาะ:
 *  - สร้าง HTTP Request พร้อม config & headers มาตรฐาน
 *  - ดึง token จาก TokenManager เพื่อแนบใน request
 *  - จัดการ fallback และ automatic retries
 */
final class MasterClientService implements MasterClientInterface
{
    private string $masterUrl;

    private string $clientId;

    private string $clientSecret;

    private int $timeout;

    private int $retryTimes;

    private int $retryDelay;

    /** Flow ที่ใช้แนบ token เมื่อส่ง request (เปลี่ยนด้วย withFlow()) */
    private TokenFlow $activeFlow = TokenFlow::OAuth;

    /** Scope ของ token ที่ใช้แนบ request (เปลี่ยนด้วย withScope()) */
    private string $activeScope;

    /** HTTP headers เพิ่มเติมที่แนบไปกับทุก request (เปลี่ยนด้วย withHeaders()) */
    private array $extraHeaders = [];

    /** ควบคุมการแนบ Access Token ไปกับ Request (เปลี่ยนด้วย withoutToken()) */
    private bool $attachToken = true;

    /** โทเคนแบบระบุเจาะจงด้วยมือ (เปลี่ยนด้วย withToken()) - null คือให้ Auto-Manager จัดการ */
    private ?string $explicitToken = null;

    /** ระยะเวลาแคชผลลัพธ์ (วินาที) - 0 คือปิดการใช้แคช */
    private int $cacheTtl = 0;

    /** ชื่อช่องทางจัดเก็บแคช เช่น 'redis', 'session' - null คือค่าดีฟอลต์ */
    private ?string $explicitCacheStore = null;

    /** Suffix พิเศษสำหรับต่อท้ายกุญแจแคชเพื่อแยก Context */
    private string $cacheSuffix = '';

    public function __construct(
        string $masterUrl,
        string $clientId,
        string $clientSecret,
        private TokenManager $tokenManager,
        string $defaultScope = '',
    ) {
        $this->masterUrl = rtrim($masterUrl, '/');
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->activeScope = self::normalizeScope($defaultScope);

        // Load configurations from config like BackendApiClient pattern
        $this->timeout = (int) config('slave::client.timeout', 15);
        $this->retryTimes = (int) config('slave::client.retry_times', 2);
        $this->retryDelay = (int) config('slave::client.retry_delay', 100);
    }

    /**
     * Normalize scope string ให้เป็น canonical form
     */
    private static function normalizeScope(string $scope): string
    {
        if ($scope === '') {
            return '';
        }

        $parts = explode(' ', Str::squish($scope));
        $parts = array_values(array_unique(array_filter($parts)));
        sort($parts);

        return implode(' ', $parts);
    }

    // ─── Fluent Builders ────────────────────────────────────────────────────

    /**
     * คืน instance ใหม่ที่ใช้ token flow ที่ระบุ
     */
    public function withFlow(TokenFlow $flow): static
    {
        $clone = clone $this;
        $clone->activeFlow = $flow;

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ขอ token ด้วย scope ที่ระบุ
     */
    public function withScope(string $scope): static
    {
        $clone = clone $this;
        $clone->activeScope = self::normalizeScope($scope);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ตั้งค่า body สำหรับ token request
     */
    public function withBody(?array $bodytoken = null): static
    {
        $clone = clone $this;
        $clone->tokenManager = $this->tokenManager->withBody($bodytoken);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ใช้ credentials ที่ระบุ (รวมทั้งอัปเดตไปยัง TokenManager ด้วย)
     */
    public function withCredentials(string $clientId, string $clientSecret): static
    {
        $clone = clone $this;
        $clone->clientId = $clientId;
        $clone->clientSecret = $clientSecret;

        // สำคัญ: ส่งต่อ credentials ชุดใหม่ไปยัง TokenManager cloned instance ด้วย
        $clone->tokenManager = $this->tokenManager->withCredentials($clientId, $clientSecret);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ตั้งค่า username และ password สำหรับการขอ token
     */
    public function withUserPassword(string $username, string $password): static
    {
        $clone = clone $this;
        $clone->tokenManager = $this->tokenManager->withUserPassword($username, $password);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่บังคับใช้ Bearer token ตามที่ระบุโดยตรง
     */
    public function withToken(string $token): static
    {
        $clone = clone $this;
        $clone->explicitToken = $token;
        $clone->attachToken = true; // รับรองว่ามีการแนบแน่ๆ

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ไม่แนบ access token ใดๆ (Alias of withoutToken)
     */
    public function disableToken(): static
    {
        return $this->withoutToken();
    }

    /**
     * คืน instance ใหม่ที่แนบ HTTP headers เพิ่มเติมไปกับทุก request
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->extraHeaders = [...$this->extraHeaders, ...$headers];

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่จะไม่แนบ access token ไปกับ request (สำหรับ public endpoints)
     */
    public function withoutToken(): static
    {
        $clone = clone $this;
        $clone->attachToken = false;
        $clone->explicitToken = null; // 🛡️ เคลียร์ manual token เผื่อทิ้งไว้ เพื่อความปลอดภัยสูงสุด

        return $clone;
    }

    /**
     * เปิดใช้งานการแคชผลลัพธ์สำหรับ request นี้ (รองรับเฉพาะ GET เท่านั้นเพื่อความปลอดภัย)
     */
    public function cache(int $seconds, ?string $store = null): static
    {
        $clone = clone $this;
        $clone->cacheTtl = $seconds;
        $clone->explicitCacheStore = $store;

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ปรับการกำหนดค่า Token Store ใน TokenManager
     *
     * @param  string|null  $store  เช่น  session หรือ redis
     */
    public function withTokenStore(?string $store): static
    {
        $clone = clone $this;
        $clone->tokenManager = $this->tokenManager->withTokenStore($store);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่เพิ่ม suffix เพื่อแยก cache context ระหว่างสถานการณ์ต่างๆ
     */
    public function withCacheSuffix(string $suffix): static
    {
        $clone = clone $this;
        $clone->cacheSuffix = trim($suffix);
        $clone->tokenManager = $this->tokenManager->withCacheSuffix($suffix);

        return $clone;
    }

    /**
     * คืน instance ใหม่ที่ใช้ Device Fingerprint เป็น cache suffix
     * เพื่อแยก token cache ตาม device/browser โดยอัตโนมัติ
     *
     * Safety: ตรวจว่ามี HTTP request จริงก่อน resolve fingerprint
     * — ป้องกันปัญหาใน CLI / Queue / Schedule ที่ไม่มี User-Agent
     *
     * ตัวอย่างการใช้:
     *   $client->withFlow(TokenFlow::Personal)
     *          ->withTokenStore('session')
     *          ->withDeviceFingerprint()     ← แยก token ตาม device อัตโนมัติ
     *          ->sendRequest('POST', '/api/...');
     */
    //  public function withDeviceFingerprint(): static
    //   {
    // Guard: ไม่ apply fingerprint หากไม่มี HTTP request (CLI/Queue context)
    //   if (! app()->runningInConsole() && request()->userAgent() !== null) {
    //       /** @var \Core\Base\Services\Session\DeviceFingerprintServiceInterface $fpService */
    /*   $fpService = app('core.session.device_fingerprint');
      $fingerprint = $fpService->fingerprint(request());

      return $this->withCacheSuffix($fingerprint);
        }

        return $this; */
    //   }

    /**
     * ดึงข้อมูล Token จาก Token Store โดยตรง (ไม่เพิ่มเติมหรือ refresh)
     */
    public function getTokenFromTokensStore(?TokenFlow $flow = null, ?string $scope = null): mixed
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;
        $this->getToken($flow, $scope);

        return $this->tokenManager->getTokenFromTokensStore($flow, $scope);
    }

    /**
     * ส่ง HTTP request และคืนค่าดิบ Response object โดยตรง
     */
    public function sendRequest(string $method, string $endpoint, array $options = []): Response
    {
        $method = strtoupper($method);

        return match ($method) {
            'GET' => $this->client()->get($endpoint, $options),
            'POST' => $this->client()->post($endpoint, $options),
            'PUT' => $this->client()->put($endpoint, $options),
            'PATCH' => $this->client()->patch($endpoint, $options),
            'DELETE' => $this->client()->delete($endpoint, $options),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };
    }
    //

    public function get(string $endpoint, array $query = []): array
    {
        return $this->dispatchJson('GET', $endpoint, $query);
    }

    public function post(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson('POST', $endpoint, $data);
    }

    public function put(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson('PUT', $endpoint, $data);
    }

    public function patch(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson('PATCH', $endpoint, $data);
    }

    public function delete(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson('DELETE', $endpoint, $data);
    }

    // ─── File Transfer & Binary Operations ────────────────────────────

    public function upload(
        string $endpoint,
        string $name,
        mixed $contents,
        string $filename = '',
        string $mimeType = '',
        array $fields = [],
    ): array {
        $response = $this->withTokenRetry(
            fn(): Response => $this->client()
                ->attach(
                    $name,
                    $contents,
                    $filename !== '' ? $filename : null,
                    $mimeType !== '' ? ['Content-Type' => $mimeType] : [],
                )
                ->post($endpoint, $fields),
        );

        if ($response->failed()) {
            throw new RuntimeException(
                "POST {$endpoint} failed [{$response->status()}]: {$response->body()}",
            );
        }

        return $this->extractJsonArray($response);
    }

    public function uploadMany(
        string $endpoint,
        array $files,
        array $fields = [],
    ): array {
        $response = $this->withTokenRetry(
            function () use ($endpoint, $files, $fields): Response {
                $client = $this->client();

                foreach ($files as $file) {
                    $mimeType = $file['mimeType'] ?? '';
                    $client->attach(
                        $file['name'],
                        $file['contents'],
                        $file['filename'] ?? null,
                        $mimeType !== '' ? ['Content-Type' => $mimeType] : [],
                    );
                }

                return $client->post($endpoint, $fields);
            },
        );

        if ($response->failed()) {
            throw new RuntimeException(
                "POST {$endpoint} failed [{$response->status()}]: {$response->body()}",
            );
        }

        return $this->extractJsonArray($response);
    }

    public function uploadStream(
        string $endpoint,
        mixed $stream,
        string $mimeType = 'application/octet-stream',
        string $method = 'PUT',
    ): array {
        $method = strtoupper($method);
        $response = $this->withTokenRetry(
            function () use ($endpoint, $stream, $mimeType, $method): Response {
                $client = $this->client()->withBody($stream, $mimeType);

                return match ($method) {
                    'POST' => $client->post($endpoint),
                    default => $client->put($endpoint),
                };
            },
        );

        if ($response->failed()) {
            throw new RuntimeException(
                "{$method} {$endpoint} failed [{$response->status()}]: {$response->body()}",
            );
        }

        return $this->extractJsonArray($response);
    }

    // ─── Utility & Domain Methods ───────────────────────────────────────────

    public function getBaseUrl(): string
    {
        return $this->masterUrl;
    }

    /**
     * ได้ full url ของ master server
     * - ถ้าไม่ระบุ token flow จะใช้ token flow ปัจจุบัน
     * - ถ้าไม่ระบุ endpoint จะใช้ endpoint ของ token flow
     */
    public function fullUrl(?TokenFlow $flow = null, ?string $endpoint = null): string
    {
        $flow ??= $this->activeFlow;
        $endpoint ??= $flow->endpoint();

        return "{$this->masterUrl}/" . ltrim($endpoint, '/');
    }

    public function ping(): bool
    {
        try {
            $response = $this->withTokenRetry(
                fn(): Response => $this->client()->post('/api/v1/clients/ping'),
            );

            return $response->successful();
        } catch (Throwable) {
            return false;
        }
    }

    public function getLicence(): ?array
    {
        try {
            // 🚀 REFACTOR DRY: นำระบบ Unified Cache ที่สร้างไว้มาประยุกต์ใช้ซ้ำ ลดความซับซ้อนและเพิ่มความปลอดภัย
            return $this->cache(3600)->get('/api/v1/licence/check');
        } catch (Throwable) {
            return null; // รักษาสัญญา Contract เดิม คืน null เมื่อล้มเหลว
        }
    }

    public function getFiles(): array
    {
        // 🚀 REFACTOR DRY: ใช้ Wrapper สำเร็จรูปที่จัดการการยิงและแกะ JSON ให้อยู่แล้ว
        return $this->get('/api/files');
    }

    // ─── Token Management Delegation ──────────────────────────────────────────
    // ทุก Method ด้านล่าง หากรับค่า null จะดึงค่าจาก Active State ของ Client ณ ขณะนั้นมาใช้โดยอัตโนมัติครับ!

    public function getToken(?TokenFlow $flow = null, ?string $scope = null): string
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;

        return $this->tokenManager->getToken($flow, $scope);
    }

    public function generateSignedHeaders(array $payload): array
    {
        //
        $sodium = app('core.crypto.sodium');
        $headers = [
            'X-Timestamp' => now()->toIso8601String(),
            'X-Client-ID' => $this->clientId,
        ];
        $signatureSeed = config('slave::client.signature_seed', '');
        $signatureKeyPair = $sodium->generateSignatureKeyPair($signatureSeed);
        $privateSignKey = $signatureKeyPair['secret'] ?? '';

        if ($privateSignKey === '') {
            throw new RuntimeException('Invalid signature keypair: missing secret.');
        }

        $headers['X-Signature'] = $sodium->sign([...$payload, ...$headers], $privateSignKey);

        \sodium_memzero($privateSignKey);
        if (\is_string($signatureKeyPair['secret'] ?? null)) {
            \sodium_memzero($signatureKeyPair['secret']);
        }

        return $headers;
        // return $this->tokenManager->buildSignedHeaders($payload);
    }

    public function encryptedpayload(array $payload): string
    {
        $sodium = app('core.crypto.sodium');
        $publicBox = config('slave::client.public_box');

        return $sodium->hybridEncrypt($payload, $publicBox);
    }

    public function clearToken(?TokenFlow $flow = null, ?string $scope = null): void
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;

        $this->tokenManager->clear($flow, $scope);
    }

    public function clearAllWithSession(): void
    {
        // 🚀 ต้องเรียก withTokenStore เพื่อสลับเป็น clone session ก่อน แล้วค่อยสั่ง clear
        $this->withTokenStore('session')->clearAllTokens();
    }

    public function clearAllTokens(): void
    {
        $this->tokenManager->clearAll();
    }

    public function clearAllWithSessionAndRedis(): void
    {
        // 1. ลบโทเคนใน Default Cache Store ปัจจุบัน
        $this->clearAllTokens();

        // 2. สลับช่องทางไปลบใน Session โดยตรง
        $this->withTokenStore('session')->clearAllTokens();

        // 3. สลับช่องทางไปลบใน Redis โดยตรง (เผื่อกรณีที่ Default ไม่ใช่ Redis)
        $this->withTokenStore('redis')->clearAllTokens();
    }

    public function clearTokenByKey(string $key): void
    {
        $this->tokenManager->clearByKey($key);
    }

    public function storeToken(array $data, ?TokenFlow $flow = null, ?string $scope = null): void
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;

        $this->tokenManager->store($data, $flow, $scope);
    }

    public function getRefreshToken(?TokenFlow $flow = null, ?string $scope = null): ?string
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;

        return $this->tokenManager->getRefreshToken($flow, $scope);
    }

    public function isExpired(?TokenFlow $flow = null, ?string $scope = null): bool
    {
        $flow ??= $this->activeFlow;
        $scope = $scope !== null ? self::normalizeScope($scope) : $this->activeScope;

        return $this->tokenManager->isExpired($flow, $scope);
    }

    public function debugCachedTokens(): array
    {
        return $this->tokenManager->debugAllTokens();
    }

    public function getCachedManifest(): array
    {
        return $this->tokenManager->getManifestKeys();
    }

    // ─── HTTP Methods ────────────────────────────────────────────────────────
    private function client(): PendingRequest
    {
        $sessionDevice = app('core.session.device_fingerprint');

        $client = Http::baseUrl($this->masterUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay, function (Throwable $e): bool {
                return $e instanceof \Illuminate\Http\Client\RequestException
                    && $e->response->serverError();
            })
            ->acceptJson()
            ->withHeaders([
                //  'X-App-id' => config('services.sso.client_id'),
                'X-Session-Fingerprint' => $sessionDevice->getRequestId(),
                'X-Platform' => 'erped-frontend',
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]);

        if ($this->attachToken) {
            // ลำดับความสำคัญ: 1. Token ที่ป้อนมาตรงๆ (Explicit) 2. Token ที่ดึงจาก TokenManager
            $token = $this->explicitToken ?? $this->tokenManager->getToken($this->activeFlow, $this->activeScope);
            $client->withToken($token);
        }

        return $this->extraHeaders !== []
            ? $client->withHeaders($this->extraHeaders)
            : $client;
    }

    // ─── Private: Core Infrastructure ────────────────────────────────────────

    private function dispatchJson(string $method, string $endpoint, array $payload): array
    {
        // ตรวจสอบสถานะการทำ Cache (จำกัดเฉพาะ GET Request)
        $isCacheable = $method === 'GET' && $this->cacheTtl > 0;
        $store = $this->explicitCacheStore !== null ? Cache::store($this->explicitCacheStore) : Cache::store();
        $cacheKey = '';

        if ($isCacheable) {
            $cacheKey = $this->generateCacheKey($method, $endpoint, $payload);
            $cachedResult = $store->get($cacheKey);

            if (\is_array($cachedResult)) {
                return $cachedResult; // ⚡ Return Early if HIT cache
            }
        }

        // 🚀 DRY REFACTOR: รวบยอดการยิง Request ผ่านช่องทางหลัก sendRequest() เพื่อรวมศูนย์ logic ทั้งหมดไว้จุดเดียว
        $response = $this->sendRequest($method, $endpoint, $payload);

        if ($response->failed()) {
            throw new RuntimeException(
                "{$method} {$endpoint} failed [{$response->status()}]: {$response->body()}",
            );
        }

        $data = $this->extractJsonArray($response);

        // เก็บลง Cache หากเงื่อนไขครบถ้วน
        if ($isCacheable) {
            $store->put($cacheKey, $data, now()->addSeconds($this->cacheTtl));
        }

        return $data;
    }

    /**
     * สร้าง Unique identifier Key สำหรับแคชแต่ละ request
     */
    private function generateCacheKey(string $method, string $endpoint, array $params = []): string
    {
        $baseIdentifier = "master_api_res:{$this->clientId}:{$method}:" . trim($endpoint, '/');

        // 🌟 สร้างสำเนาและจัดเรียงคีย์ (Canonical Sorting) เพื่อให้ Cache Hits เสถียรที่สุดแม้ส่งพารามิเตอร์สลับลำดับกัน
        $safeParams = $params;
        ksort($safeParams);

        // สร้าง State signature เพื่อแยกความแตกต่างของ Context, Flow, Params และ Auth state อย่างละเอียดอ่อน
        $stateSignature = md5(json_encode([
            'flow' => $this->activeFlow->value,
            'scope' => $this->activeScope,
            'headers' => $this->extraHeaders,
            'auth' => $this->attachToken,
            'suffix' => $this->cacheSuffix,
            'params' => $safeParams,
        ]));

        return "{$baseIdentifier}:{$stateSignature}";
    }

    private function extractJsonArray(Response $response): array
    {
        $data = $response->json();

        return \is_array($data) ? $data : [];
    }

    private function withTokenRetry(callable $request): Response
    {
        $response = $request();

        // อนุญาตให้ทำ Retry อัตโนมัติเฉพาะเคสที่ปล่อยให้ Manager จัดการ Token เองเท่านั้น
        // (หากเป็น Token ที่ยัดมาเองด้วยมือ เราไม่สามารถสั่ง Refresh ได้)
        $isAutoManaged = $this->attachToken && $this->explicitToken === null;

        if ($isAutoManaged && $response->status() === 401) {
            $this->tokenManager->clear($this->activeFlow, $this->activeScope);
            $response = $request();
        }

        return $response;
    }
    //

}
