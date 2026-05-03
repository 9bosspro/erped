<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'database'    => $this->checkDatabase(),
            'cache'       => $this->checkCache(),
            'backend_api' => $this->checkBackendApi(),
        ];

        $healthy = ! \in_array(false, array_column($checks, 'ok'), true);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    /** @return array{ok: bool, error?: string} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, error?: string} */
    private function checkCache(): array
    {
        try {
            Cache::put('health_check', true, 10);
            $value = Cache::get('health_check');
            Cache::forget('health_check');

            return ['ok' => $value === true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** @return array{ok: bool, error?: string} */
    private function checkBackendApi(): array
    {
        try {
            $apiUrl = config('backend.api_url');
            $url    = (\is_string($apiUrl) ? $apiUrl : '') . '/api/health';

            $response = Http::timeout(5)->acceptJson()->get($url);

            return ['ok' => $response->successful()];
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'Backend API unreachable'];
        }
    }
}
