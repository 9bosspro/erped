<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * BackendApiClient — HTTP Client สำหรับเรียก Backend API (pppportal)
 *
 * ใช้สำหรับ server-to-server communication ระหว่าง Frontend (erped) กับ Backend (pppportal)
 * ที่อยู่คนละเครื่อง/คนละ network
 *
 * Features:
 *  - Bearer token authentication (Passport)
 *  - Automatic retry on failure (5xx)
 *  - Request/response logging
 *  - Configurable timeout
 */
class BackendApiClient
{
    private string $baseUrl;
    private int $timeout;
    private int $retryTimes;
    private int $retryDelay;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('backend.api_url'), '/');
        $this->timeout = (int) config('backend.timeout', 30);
        $this->retryTimes = (int) config('backend.retry_times', 2);
        $this->retryDelay = (int) config('backend.retry_delay', 500);
    }

    /**
     * สร้าง HTTP client พร้อม Bearer token
     */
    public function withToken(string $token): PendingRequest
    {
        return $this->makeClient()
            ->withToken($token);
    }

    /**
     * สร้าง HTTP client แบบไม่มี token (สำหรับ public endpoints เช่น login)
     */
    public function withoutToken(): PendingRequest
    {
        return $this->makeClient();
    }

    // ─── Auth Endpoints ────────────────────────────────────────────

    /**
     * Login — เรียก Backend API เพื่อขอ Passport token
     */
    public function login(string $email, string $password): Response
    {
        return $this->withoutToken()
            ->post('/api/v1/auth/login', [
                'email'    => $email,
                'password' => $password,
            ]);
    }

    /**
     * Refresh Token — ต่ออายุ access token
     */
    public function refreshToken(string $refreshToken): Response
    {
        return $this->withoutToken()
            ->post('/api/v1/auth/refresh', [
                'refresh_token' => $refreshToken,
            ]);
    }

    /**
     * Logout — revoke token บน Backend
     */
    public function logout(string $token): Response
    {
        return $this->withToken($token)
            ->post('/api/v1/auth/logout');
    }

    /**
     * Me — ดึงข้อมูล user ปัจจุบัน
     */
    public function me(string $token): Response
    {
        return $this->withToken($token)
            ->post('/api/v1/auth/me');
    }

    // ─── Generic Methods ───────────────────────────────────────────

    /**
     * GET request ไปยัง Backend API
     */
    public function get(string $token, string $endpoint, array $query = []): Response
    {
        return $this->withToken($token)
            ->get($endpoint, $query);
    }

    /**
     * POST request ไปยัง Backend API
     */
    public function post(string $token, string $endpoint, array $data = []): Response
    {
        return $this->withToken($token)
            ->post($endpoint, $data);
    }

    /**
     * PUT request ไปยัง Backend API
     */
    public function put(string $token, string $endpoint, array $data = []): Response
    {
        return $this->withToken($token)
            ->put($endpoint, $data);
    }

    /**
     * DELETE request ไปยัง Backend API
     */
    public function delete(string $token, string $endpoint, array $data = []): Response
    {
        return $this->withToken($token)
            ->delete($endpoint, $data);
    }

    // ─── Internal ──────────────────────────────────────────────────

    /**
     * สร้าง base HTTP client พร้อม config ทั้งหมด
     */
    private function makeClient(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->retry($this->retryTimes, $this->retryDelay, function (\Exception $e) {
                // Retry เฉพาะ server errors (5xx) เท่านั้น
                return $e instanceof \Illuminate\Http\Client\RequestException
                    && $e->response?->serverError();
            })
            ->acceptJson()
            ->withHeaders([
                'X-Platform'   => 'erped-frontend',
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ]);
    }
}
