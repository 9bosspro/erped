<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Slave\Contracts\Master\MasterClientInterface;

/**
 * MasterClientService — HTTP client หลักสำหรับติดต่อ Master Server
 *
 * รวม flow ของการขอ token ทั้ง OAuth2 และ JWT ไว้ในโครงสร้างเดียว
 * โดยแยกหน้าที่ตามหลัก SOLID:
 *  - dispatchJson()        ส่ง JSON request พร้อม retry token เมื่อ 401
 *  - resolveToken()        ดึง token จาก cache หรือเรียก fetchAndCacheToken()
 *  - fetchAndCacheToken()  authenticate กับ Master ตาม flow ที่ระบุ และ cache ผลลัพธ์
 */
final class MasterClientService implements MasterClientInterface
{
    /** TTL ขั้นต่ำของ cache token (วินาที) */
    private const int MIN_CACHE_TTL = 300;

    /** Buffer ที่ตัดออกจาก expires_in ก่อนใช้เป็น TTL (วินาที) */
    private const int CACHE_TTL_BUFFER = 300;

    /** Default expires_in หาก Master ไม่ส่งกลับมา (12 ชั่วโมง) */
    private const int DEFAULT_EXPIRES_IN = 43200;

    /** Timeout ของ HTTP client หลัก (วินาที) */
    private const int HTTP_TIMEOUT = 15;

    private const int HTTP_RETRY_TIMES = 2;

    private const int HTTP_RETRY_SLEEP_MS = 100;

    /** Endpoint ของ flow OAuth2 */
    private const string FLOW_OAUTH_ENDPOINT = '/oauth/token';

    private const string FLOW_OAUTH_GRANT = 'client_credentials';

    private const string FLOW_OAUTH_PREFIX = 'master_token';

    /** Endpoint ของ flow JWT */
    private const string FLOW_JWT_ENDPOINT = '/api/v1/jwt/token';

    private const string FLOW_JWT_GRANT = 'client_credentials_jwt';

    private const string FLOW_JWT_PREFIX = 'master_tokenjwt';

    public function __construct(
        private readonly string $masterUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {}

    /**
     * ส่ง GET request ไปยัง Master API
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $endpoint, array $query = []): array
    {
        return $this->dispatchJson(
            'GET',
            $endpoint,
            fn(PendingRequest $client): Response => $client->get($endpoint, $query),
        );
    }

    /**
     * ส่ง POST request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson(
            'POST',
            $endpoint,
            fn(PendingRequest $client): Response => $client->post($endpoint, $data),
        );
    }

    /**
     * ส่ง PUT request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function put(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson(
            'PUT',
            $endpoint,
            fn(PendingRequest $client): Response => $client->put($endpoint, $data),
        );
    }

    /**
     * ส่ง PATCH request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function patch(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson(
            'PATCH',
            $endpoint,
            fn(PendingRequest $client): Response => $client->patch($endpoint, $data),
        );
    }

    /**
     * ส่ง DELETE request ไปยัง Master API
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function delete(string $endpoint, array $data = []): array
    {
        return $this->dispatchJson(
            'DELETE',
            $endpoint,
            fn(PendingRequest $client): Response => $client->delete($endpoint, $data),
        );
    }

    public function getBaseUrl(): string
    {
        return $this->masterUrl;
    }

    /**
     * ตรวจสอบว่า Master Server ตอบสนองได้ปกติหรือไม่
     */
    public function ping(): bool
    {

        try {
            $response = $this->withTokenRetry(
                fn(): Response => $this->client()->post('/api/v1/clients/ping'),
            );

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ดึงข้อมูล licence (cache 1 ชั่วโมง)
     *
     * @return array<string, mixed>|null
     */
    public function getLicence(): ?array
    {
        $cacheKey = "licence:{$this->clientId}";
        $cached   = $this->cache()->get($cacheKey);

        if (\is_array($cached)) {
            /** @var array<string, mixed> $cached */
            return $cached;
        }

        $response = $this->withTokenRetry(
            fn(): Response => $this->client()->get('/api/v1/licence/check'),
        );

        if (! $response->successful()) {
            return null;
        }

        $data = $this->extractJsonArray($response);
        $this->cache()->put($cacheKey, $data, now()->addHour());

        return $data;
    }

    /**
     * ดึงรายการไฟล์จาก Master
     *
     * @return array<string, mixed>
     */
    public function getFiles(): array
    {
        $response = $this->withTokenRetry(
            fn(): Response => $this->client()->get('/api/files'),
        );

        if ($response->failed()) {
            throw new \RuntimeException(
                "Failed to fetch files [{$response->status()}]",
            );
        }

        return $this->extractJsonArray($response);
    }

    /**
     * คืนค่า OAuth2 access token (ดึงจาก cache หรือขอใหม่)
     */
    public function getAccessToken(string $scope = ''): string
    {
        return $this->resolveToken(
            self::FLOW_OAUTH_ENDPOINT,
            self::FLOW_OAUTH_GRANT,
            self::FLOW_OAUTH_PREFIX,
            $scope,
        );
    }

    /**
     * คืนค่า JWT access token (ดึงจาก cache หรือขอใหม่)
     */
    public function getAccessTokenJwt(string $scope = ''): string
    {
        return $this->resolveToken(
            self::FLOW_JWT_ENDPOINT,
            self::FLOW_JWT_GRANT,
            self::FLOW_JWT_PREFIX,
            $scope,
        );
    }

    /**
     * ล้าง OAuth2 access token ออกจาก cache
     */
    public function clearToken(string $scope = ''): void
    {
        $this->cache()->forget($this->cacheKey(self::FLOW_OAUTH_PREFIX, $scope));
    }

    /**
     * ล้าง JWT access token ออกจาก cache
     */
    public function clearTokenJwt(string $scope = ''): void
    {
        $this->cache()->forget($this->cacheKey(self::FLOW_JWT_PREFIX, $scope));
    }

    /**
     * ส่ง request ผ่าน HTTP client หลัก แล้วคืนผลในรูป JSON array
     *
     * @param  callable(PendingRequest): Response  $callback
     * @return array<string, mixed>
     */
    private function dispatchJson(string $method, string $endpoint, callable $callback): array
    {
        $response = $this->withTokenRetry(
            fn(): Response => $callback($this->client()),
        );

        if ($response->failed()) {
            throw new \RuntimeException(
                "{$method} {$endpoint} failed [{$response->status()}]: {$response->body()}",
            );
        }

        return $this->extractJsonArray($response);
    }

    /**
     * ดึง JSON body จาก Response แล้วคืนเป็น array (กันกรณี response ไม่ใช่ object/array)
     *
     * @return array<string, mixed>
     */
    private function extractJsonArray(Response $response): array
    {
        $data = $response->json();

        return \is_array($data) ? $data : [];
    }

    /**
     * เรียก request — หาก response = 401 ให้ทิ้ง token เก่าแล้วลองใหม่หนึ่งครั้ง
     *
     * @param  callable(): Response  $request
     */
    private function withTokenRetry(callable $request): Response
    {
        $response = $request();

        if ($response->status() === 401) {
            $this->clearToken();
            $response = $request();
        }

        return $response;
    }

    /**
     * สร้าง HTTP client หลัก (แนบ OAuth2 token + retry policy)
     */
    private function client(): PendingRequest
    {
        return Http::baseUrl($this->masterUrl)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(self::HTTP_TIMEOUT)
            ->retry(self::HTTP_RETRY_TIMES, self::HTTP_RETRY_SLEEP_MS);
    }

    /**
     * คืน token จาก cache หรือเรียก fetchAndCacheToken() ถ้าไม่มี
     */
    private function resolveToken(
        string $endpoint,
        string $grantType,
        string $cachePrefix,
        string $scope,
    ): string {
        $cached = $this->cache()->get($this->cacheKey($cachePrefix, $scope));

        if (\is_string($cached) && $cached !== '') {
            return $cached;
        }

        return $this->fetchAndCacheToken($endpoint, $grantType, $cachePrefix, $scope);
    }

    /**
     * ขอ access token ใหม่จาก Master ตาม flow ที่ระบุ พร้อม cache ผลลัพธ์
     */
    private function fetchAndCacheToken(
        string $endpoint,
        string $grantType,
        string $cachePrefix,
        string $scope,
    ): string {
        $sodium = $this->sodiumHelper();

        $payload = [
            'grant_type'    => $grantType,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $scope,
        ];

        $headers = [
            'X-Timestamp' => now()->toIso8601String(),
            'X-Client-ID' => $this->clientId,
        ];

        $signatureSeed    = $this->configString('slave::client.signature_seed');
        $publicBox        = $this->configString('slave::client.public_box');
        $signatureKeyPair = $sodium->generateSignatureKeyPair($signatureSeed);

        $privateSignKey = $signatureKeyPair['secret'] ?? '';
        if ($privateSignKey === '') {
            throw new \RuntimeException('Invalid signature keypair: missing secret.');
        }

        $encryptedPayload       = $sodium->hybridEncrypt($payload, $publicBox);
        $headers['X-Signature'] = $sodium->sign([...$payload, ...$headers], $privateSignKey);

        \sodium_memzero($privateSignKey);

        try {
            $response = Http::asForm()
                ->withHeaders($headers)
                ->post(
                    "{$this->masterUrl}{$endpoint}",
                    ['encrypted_payload' => $encryptedPayload],
                );
        } catch (\Throwable $e) {
            Log::critical('Master Connection Error', ['error' => $e->getMessage()]);
            throw $e;
        }
        //     dd($response->body());

        if ($response->failed()) {
            Log::error('Master Auth Failed', [
                'flow'   => $cachePrefix,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \RuntimeException('Could not authenticate with Master Server.');
        }

        $data = $response->json();
        if (! \is_array($data)) {
            throw new \RuntimeException('Master Server returned invalid JSON response.');
        }
        //fix response jwt grant
        if ($grantType === self::FLOW_JWT_GRANT) {
            $data = $data['data'];
        }
        //     dd($data);

        $accessToken = $data['access_token'] ?? null;
        if (! \is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Master Server response missing access_token.');
        }

        $rawExpires = $data['expires_in'] ?? self::DEFAULT_EXPIRES_IN;
        $expiresIn  = \is_int($rawExpires) ? $rawExpires : self::DEFAULT_EXPIRES_IN;
        $cacheTtl   = max(self::MIN_CACHE_TTL, $expiresIn - self::CACHE_TTL_BUFFER);

        $this->cache()->put(
            $this->cacheKey($cachePrefix, $scope),
            $accessToken,
            $cacheTtl,
        );

        return $accessToken;
    }

    private function cacheKey(string $prefix, string $scope): string
    {
        return "{$prefix}:{$this->clientId}:" . md5($scope);
    }

    private function cache(): CacheRepository
    {
        return Cache::store();
    }

    private function configString(string $key): string
    {
        $value = config($key, '');

        return \is_string($value) ? $value : '';
    }

    private function sodiumHelper(): SodiumHelper
    {
        /** @var SodiumHelper $helper */
        $helper = app('core.crypto.sodium');

        return $helper;
    }
}
