<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Http;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HttpClient — HTTP client wrapper รอบ Laravel Http facade
 *
 * ความรับผิดชอบ:
 * - ส่ง HTTP request (GET, POST, PUT, PATCH, DELETE)
 * - แนบ Bearer token อัตโนมัติ
 * - กำหนด base URL สำหรับ API client
 *
 * ออกแบบให้ stateless: ทุก method รับ config ผ่าน parameter
 * ไม่เก็บ token หรือ base_url ใน property → ปลอดภัยและ testable
 *
 * หมายเหตุ: ไม่ใช้ final เพราะใช้ anonymous class extends ใน withBearer/withBaseUrl
 */
class HttpClient
{
    /**
     * ส่ง GET request
     *
     * @param  string  $url  URL เป้าหมาย
     * @param  array  $query  Query parameters
     * @param  array  $headers  HTTP headers เพิ่มเติม
     * @param  int  $timeout  Timeout (วินาที)
     */
    public function get(
        string $url,
        array $query = [],
        array $headers = [],
        int $timeout = 30,
    ): Response {
        return $this->request('GET', $url, $query, $headers, $timeout);
    }

    /**
     * ส่ง POST request
     */
    public function post(
        string $url,
        array $body = [],
        array $headers = [],
        int $timeout = 30,
    ): Response {
        return $this->request('POST', $url, $body, $headers, $timeout);
    }

    /**
     * ส่ง PUT request
     */
    public function put(
        string $url,
        array $body = [],
        array $headers = [],
        int $timeout = 30,
    ): Response {
        return $this->request('PUT', $url, $body, $headers, $timeout);
    }

    /**
     * ส่ง PATCH request
     */
    public function patch(
        string $url,
        array $body = [],
        array $headers = [],
        int $timeout = 30,
    ): Response {
        return $this->request('PATCH', $url, $body, $headers, $timeout);
    }

    /**
     * ส่ง DELETE request
     */
    public function delete(
        string $url,
        array $body = [],
        array $headers = [],
        int $timeout = 30,
    ): Response {
        return $this->request('DELETE', $url, $body, $headers, $timeout);
    }

    /**
     * สร้าง instance ใหม่ที่มี Bearer token แนบไปด้วยทุก request
     *
     * @param  string  $token  Bearer token
     */
    public function withBearer(string $token): static
    {
        return new class($token) extends HttpClient
        {
            public function __construct(private readonly string $bearerToken) {}

            protected function buildPendingRequest(array $headers, int $timeout)
            {
                return Http::withToken($this->bearerToken)
                    ->withHeaders(array_merge(['Accept' => 'application/json'], $headers))
                    ->timeout($timeout);
            }
        };
    }

    /**
     * สร้าง instance ใหม่ที่มี base URL (สำหรับ API client)
     *
     * @param  string  $baseUrl  Base URL เช่น "https://api.example.com"
     */
    public function withBaseUrl(string $baseUrl): static
    {
        return new class($baseUrl) extends HttpClient
        {
            public function __construct(private readonly string $baseUrl) {}

            public function get(string $url, array $query = [], array $headers = [], int $timeout = 30): Response
            {
                return parent::get($this->baseUrl.$url, $query, $headers, $timeout);
            }

            public function post(string $url, array $body = [], array $headers = [], int $timeout = 30): Response
            {
                return parent::post($this->baseUrl.$url, $body, $headers, $timeout);
            }

            public function put(string $url, array $body = [], array $headers = [], int $timeout = 30): Response
            {
                return parent::put($this->baseUrl.$url, $body, $headers, $timeout);
            }

            public function delete(string $url, array $body = [], array $headers = [], int $timeout = 30): Response
            {
                return parent::delete($this->baseUrl.$url, $body, $headers, $timeout);
            }
        };
    }

    /**
     * ดำเนินการ request จริง
     */
    protected function request(
        string $method,
        string $url,
        array $data,
        array $headers,
        int $timeout,
    ): Response {
        $pending = $this->buildPendingRequest($headers, $timeout);

        return match (strtoupper($method)) {
            'GET' => $pending->get($url, $data),
            'POST' => $pending->post($url, $data),
            'PUT' => $pending->put($url, $data),
            'PATCH' => $pending->patch($url, $data),
            'DELETE' => $pending->delete($url, $data),
            default => throw new RuntimeException("Unsupported HTTP method: {$method}"),
        };
    }

    /**
     * สร้าง PendingRequest พร้อม headers และ timeout
     */
    protected function buildPendingRequest(array $headers, int $timeout)
    {
        return Http::withHeaders(array_merge(
            ['Accept' => 'application/json'],
            $headers,
        ))->timeout($timeout);
    }
}
