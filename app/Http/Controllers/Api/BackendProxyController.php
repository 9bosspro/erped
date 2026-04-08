<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackendApi\BackendApiClient;
use Illuminate\Http\Client\Response as BackendResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * BackendProxyController — BFF Proxy สำหรับเรียก Backend API
 *
 * React เรียก erped API → erped server เรียก pppportal API
 * Token อยู่ใน session (server-side) ไม่ expose ไปยัง client
 *
 * Features:
 *  - Standardized error responses (ไม่ expose raw backend errors)
 *  - Request/response logging พร้อม duration
 *  - Token missing → 401 ทันที
 *  - Backend 5xx → 503 พร้อม generic message
 */
class BackendProxyController extends Controller
{
    public function __construct(
        private readonly BackendApiClient $apiClient,
    ) {}

    /**
     * GET /api/v1/proxy/{endpoint}
     */
    public function get(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return $this->unauthenticated();
        }

        $start = microtime(true);
        $response = $this->apiClient->get($token, "/api/v1/{$endpoint}", $request->query());
        $this->logProxyCall($request, 'GET', $endpoint, $response->status(), $start);

        return $this->standardizeResponse($response, $endpoint);
    }

    /**
     * POST /api/v1/proxy/{endpoint}
     */
    public function post(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return $this->unauthenticated();
        }

        $start = microtime(true);
        $response = $this->apiClient->post($token, "/api/v1/{$endpoint}", $request->all());
        $this->logProxyCall($request, 'POST', $endpoint, $response->status(), $start);

        return $this->standardizeResponse($response, $endpoint);
    }

    /**
     * PUT /api/v1/proxy/{endpoint}
     */
    public function put(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return $this->unauthenticated();
        }

        $start = microtime(true);
        $response = $this->apiClient->put($token, "/api/v1/{$endpoint}", $request->all());
        $this->logProxyCall($request, 'PUT', $endpoint, $response->status(), $start);

        return $this->standardizeResponse($response, $endpoint);
    }

    /**
     * DELETE /api/v1/proxy/{endpoint}
     */
    public function delete(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return $this->unauthenticated();
        }

        $start = microtime(true);
        $response = $this->apiClient->delete($token, "/api/v1/{$endpoint}", $request->all());
        $this->logProxyCall($request, 'DELETE', $endpoint, $response->status(), $start);

        return $this->standardizeResponse($response, $endpoint);
    }

    // ─── Helpers ───────────────────────────────────────────────────

    private function getToken(): ?string
    {
        return session('backend_access_token');
    }

    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated',
        ], 401);
    }

    /**
     * แปลง backend response เป็น standardized JSON response
     *
     * - 5xx → 503 พร้อม generic message (ไม่ expose internal error)
     * - 401 จาก backend → forward พร้อม Thai message
     * - อื่น ๆ → pass-through ตาม status เดิม
     */
    private function standardizeResponse(BackendResponse $backendResponse, string $endpoint): JsonResponse
    {
        if ($backendResponse->serverError()) {
            Log::error('Backend API server error via proxy', [
                'endpoint' => $endpoint,
                'status'   => $backendResponse->status(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'เกิดข้อผิดพลาดที่เซิร์ฟเวอร์ กรุณาลองใหม่ภายหลัง',
            ], 503);
        }

        if ($backendResponse->status() === 401) {
            return response()->json([
                'success' => false,
                'message' => 'หมดเวลาเซสชัน กรุณาเข้าสู่ระบบใหม่',
            ], 401);
        }

        return response()->json($backendResponse->json(), $backendResponse->status());
    }

    /**
     * Log รายละเอียดของ proxy call ทุกครั้ง
     */
    private function logProxyCall(Request $request, string $method, string $endpoint, int $status, float $start): void
    {
        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('daily')->info('BFF Proxy call', [
            'method'      => $method,
            'endpoint'    => $endpoint,
            'status'      => $status,
            'duration_ms' => $duration,
            'user_id'     => $request->user()?->id,
            'ip'          => $request->ip(),
        ]);
    }
}
