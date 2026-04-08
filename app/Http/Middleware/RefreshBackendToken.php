<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\BackendApi\BackendAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * RefreshBackendToken — Auto-refresh Backend API token ก่อนทุก proxy call
 *
 * ตรวจสอบว่า token ใกล้หมดอายุหรือยัง:
 *  - ถ้าใกล้หมด และมี refresh_token → refresh อัตโนมัติ
 *  - ถ้า refresh ไม่ได้ → logout และส่ง 401
 *  - ถ้าไม่รู้ expiry (token เก่า) → ปล่อยผ่านแต่ log warning
 */
class RefreshBackendToken
{
    public function __construct(
        private readonly BackendAuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->authService->isTokenExpired()) {
            return $next($request);
        }

        $refreshToken = $this->authService->getRefreshToken();

        if (! $refreshToken) {
            Log::warning('Backend token expired — no refresh token available', [
                'user_id' => $request->user()?->id,
                'path'    => $request->path(),
            ]);

            // ไม่มี refresh token — ปล่อยผ่าน backend จะส่ง 401 เอง
            return $next($request);
        }

        $result = $this->authService->refresh($refreshToken);

        if (! $result['success']) {
            Log::warning('Backend token refresh failed — forcing logout', [
                'user_id' => $request->user()?->id,
            ]);

            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();

            return response()->json([
                'success' => false,
                'message' => 'หมดเวลาเซสชัน กรุณาเข้าสู่ระบบใหม่',
            ], 401);
        }

        return $next($request);
    }
}
