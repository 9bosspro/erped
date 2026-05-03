<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Slave\Contracts\Master\MasterClientInterface;
use Core\Base\Support\Helpers\Crypto\SodiumHelper;
use Core\Base\Support\Helpers\Crypto\JwtHelper;


class MasterClientService implements MasterClientInterface
{
    public function __construct(
        private readonly string $masterUrl,
        private readonly string $clientId,
        private readonly string $clientSecret,
        //   private readonly SodiumHelper $sodiumHelper,
        // private readonly JwtHelper $jwtHelper,
    ) {}

    public function get(string $endpoint, array $query = []): array
    {
        $response = $this->withTokenRetry(
            fn() => $this->client()->get($endpoint, $query)
        );

        if ($response->failed()) {
            throw new \RuntimeException(
                "GET {$endpoint} failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    public function post(string $endpoint, array $data = []): array
    {
        $response = $this->withTokenRetry(
            fn() => $this->client()->post($endpoint, $data)
        );

        if ($response->failed()) {
            throw new \RuntimeException(
                "POST {$endpoint} failed [{$response->status()}]: {$response->body()}"
            );
        }

        return $response->json() ?? [];
    }

    public function getBaseUrl(): string
    {
        return $this->masterUrl;
    }

    public function ping(): bool
    {
        try {
            return $this->client()->get('/api/slave/ping')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getLicence(): ?array
    {
        return cache()->remember(
            "licence:{$this->clientId}",
            now()->addHour(),
            function () {
                $response = $this->client()->get('/api/v1/licence/check');
                return $response->successful() ? $response->json() : null;
            }
        );
    }

    public function getFiles(): array
    {
        $response = $this->withTokenRetry(
            fn() => $this->client()->get('/api/files')
        );

        if ($response->failed()) {
            throw new \RuntimeException(
                "Failed to fetch files [{$response->status()}]"
            );
        }

        return $response->json() ?? [];
    }

    public function clearToken(string $scope = ''): void
    {
        cache()->forget($this->cacheKey($scope));
    }

    // ✅ retry เฉพาะ 401 — clear token แล้วขอใหม่ครั้งเดียว
    private function withTokenRetry(callable $request): Response
    {
        $response = $request();

        if ($response->status() === 401) {
            $this->clearToken();
            $response = $request();
        }

        return $response;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->masterUrl)
            ->withToken($this->getAccessToken())
            ->acceptJson()
            ->timeout(15)
            ->retry(2, 100);
    }

    // ✅ private — ไม่ expose ออกไป
    // ✅ ใช้ get()/put() แทน remember(null) — TTL ถูกต้อง
    public function getAccessToken(string $scope = ''): string
    {
        $key   = $this->cacheKey($scope);
        $token = cache()->get($key);

        if ($token !== null) {
            return $token;
        }

        return $this->fetchAccessToken($scope);
    }

    private function fetchAccessToken(string $scope = ''): string
    {
        $sodiumHelper = app('core.crypto.sodium');

        $playload = [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $scope,
        ];

        $header = [
            'X-Timestamp' => now()->toIso8601String(),
            'X-Client-ID' => $this->clientId,
        ];

        $data = array_merge($playload, $header);
        //
        $signature_seed = config("slave::client.signature_seed");
        $generateSignatureKeyPair = $sodiumHelper->generateSignatureKeyPair($signature_seed);
        $privatekeysign = $generateSignatureKeyPair['secret'];
        //
        /*    $box_seed = config("slave::client.box_seed");
        $generateBoxKeyPair = $sodiumHelper->generateBoxKeyPair($box_seed);
        dd($generateSignatureKeyPair); */
        $public_box = config("slave::client.public_box");
        $hybridEncrypt = $sodiumHelper->hybridEncrypt($playload, $public_box);
        // dd($hybridEncrypt);
        //
        $signature = $sodiumHelper->sign($data, $privatekeysign);
        $header['X-Signature'] = $signature;
        $header['X-Encrypt'] = $hybridEncrypt;


        try {
            $response = Http::asForm()
                ->withHeaders($header)
                ->post("{$this->masterUrl}/oauth/token", []);

            //  dd($response->body());

            if ($response->failed()) {
                Log::error('Master Auth Failed', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                throw new \RuntimeException('Could not authenticate with Master Server.');
            }

            $data      = $response->json();
            $expiresIn = $data['expires_in'] ?? 3600;
            $cacheTtl  = max(60, $expiresIn - 300); // buffer 5 นาที
            //    dd($data);

            // ✅ put() โดยตรง — ไม่มี remember() มา overwrite TTL
            cache()->put($this->cacheKey($scope), $data['access_token'], $cacheTtl);

            return $data['access_token'];
        } catch (\Throwable $e) {
            Log::critical('Master Connection Error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    private function cacheKey(string $scope = ''): string
    {
        return "master_token:{$this->clientId}:" . md5($scope);
    }
}
