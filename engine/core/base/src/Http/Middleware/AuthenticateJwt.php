<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use App\Models\User;
use Closure;
use Core\Base\Services\Security\TokenBlacklistService;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * AuthenticateJwt — Middleware ตรวจสอบ JWT token จาก Bearer header
 *
 * ลำดับการตรวจสอบ:
 * 1. ดึง Bearer token จาก Authorization header
 * 2. Parse + validate token (signature, exp, nbf, iss, aud)
 * 3. ตรวจสอบว่า token ถูก revoke หรือไม่ (via TokenBlacklistService)
 * 4. ตรวจสอบว่า token type = 'access' (ไม่ใช่ refresh)
 * 5. ดึง User จาก database ตาม user_id ใน token
 * 6. Set user ใน request (เหมือน auth('api')->user())
 *
 * Usage ใน routes:
 * ```php
 * Route::middleware('jwt.auth')->group(function () {
 *     Route::get('/me', [UserController::class, 'me']);
 * });
 * ```
 *
 * ลงทะเบียน middleware ใน bootstrap/app.php:
 * ```php
 * ->withMiddleware(function (Middleware $middleware) {
 *     $middleware->alias(['jwt.auth' => AuthenticateJwt::class]);
 * })
 * ```
 */
class AuthenticateJwt
{
    public function __construct(
        private readonly JwtHelper $jwtService,
        private readonly TokenBlacklistService $blacklist,
    ) {}

    /**
     * Handle incoming request
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return $this->unauthorized('กรุณาส่ง Bearer token');
        }

        // 1. Parse + validate (signature, expiration, issuer, audience)
        try {
            $parsed = $this->jwtService->parse($token);
        } catch (Throwable $e) {
            Log::debug('JWT_AUTH_FAILED', ['reason' => $e->getMessage()]);

            return $this->unauthorized('Token ไม่ถูกต้องหรือหมดอายุ');
        }

        // 2. ตรวจ blacklist
        $jti = $parsed->claims()->get('jti');
        if (is_string($jti) && $this->blacklist->isRevoked($jti)) {
            return $this->unauthorized('Token ถูกเพิกถอนแล้ว');
        }

        // 3. ตรวจ token type = access
        $type = $parsed->claims()->get('type');
        if ($type !== 'access') {
            return $this->unauthorized('ประเภท token ไม่ถูกต้อง');
        }

        // 4. ดึง user
        $userId = $parsed->claims()->get('user_id');
        if (empty($userId)) {
            return $this->unauthorized('Token ไม่มี user_id');
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->unauthorized('ไม่พบผู้ใช้งาน');
        }

        // 5. Set user ใน request
        $request->setUserResolver(fn () => $user);

        // 6. แนบ parsed token ใน request attributes (สำหรับ controller ที่ต้องการ)
        $request->attributes->set('jwt.token', $parsed);
        $request->attributes->set('jwt.jti', $jti);

        return $next($request);
    }

    /**
     * สร้าง 401 Unauthorized response
     */
    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
        ], 401, [], JSON_UNESCAPED_UNICODE);
    }
}
