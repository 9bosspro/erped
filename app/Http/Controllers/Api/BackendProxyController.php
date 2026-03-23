<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BackendApi\BackendApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * BackendProxyController — BFF Proxy สำหรับเรียก Backend API
 *
 * React เรียก erped API → erped server เรียก pppportal API
 * Token อยู่ใน session (server-side) ไม่ expose ไปยัง client
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
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = $this->apiClient->get($token, "/api/v1/{$endpoint}", $request->query());

        return response()->json($response->json(), $response->status());
    }

    /**
     * POST /api/v1/proxy/{endpoint}
     */
    public function post(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = $this->apiClient->post($token, "/api/v1/{$endpoint}", $request->all());

        return response()->json($response->json(), $response->status());
    }

    /**
     * PUT /api/v1/proxy/{endpoint}
     */
    public function put(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = $this->apiClient->put($token, "/api/v1/{$endpoint}", $request->all());

        return response()->json($response->json(), $response->status());
    }

    /**
     * DELETE /api/v1/proxy/{endpoint}
     */
    public function delete(Request $request, string $endpoint): JsonResponse
    {
        $token = $this->getToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $response = $this->apiClient->delete($token, "/api/v1/{$endpoint}", $request->all());

        return response()->json($response->json(), $response->status());
    }

    private function getToken(): ?string
    {
        return session('backend_access_token');
    }
}
