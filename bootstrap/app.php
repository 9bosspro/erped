<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRequests;
use Core\Base\Http\Middleware\AuthenticateJwt;
use Slave\Http\Middleware\SecurityHeaders;
use Core\Base\Http\Middleware\SetLanguage;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (Nginx, Load Balancer, Docker)
        // เพื่อให้ request()->ip() ได้ IP จริงของ client
        // ใช้ env() เพราะ config() ยังไม่พร้อมใช้ใน withMiddleware()
        $middleware->trustProxies(
            at: array_filter(array_map('trim', explode(',', env('TRUSTED_PROXIES', '172.17.0.0/24')))),
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_AWS_ELB,
        );
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // ── Global middleware (ทุก request) ───────────────────
        $middleware->append(SecurityHeaders::class);
        $middleware->append(LogRequests::class);

        $middleware->web(append: [
            SetLanguage::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // ── Middleware aliases ─────────────────────────────────
        // jwt.auth — ใช้สำหรับ API route ที่ต้องการ JWT Bearer token
        // ตัวอย่าง: Route::middleware('jwt.auth')->group(...)
        $middleware->alias([
            'jwt.auth' => AuthenticateJwt::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // เพิ่ม context สำหรับ error logging — ใช้ request() helper เพราะไม่ต้องการข้อมูลจาก $e
        $exceptions->context(function (Throwable $_e): array {
            $request = request();

            return [
                'url'     => $request->fullUrl(),
                'method'  => $request->method(),
                'user_id' => $request->user()?->id,
                'ip'      => $request->ip(),
            ];
        });

        // บังคับ JSON response เมื่อ request เป็น API หรือ expectsJson
        $exceptions->shouldRenderJsonWhen(
            fn(Request $request, Throwable $_e): bool => $request->is('api', 'api/*') || $request->expectsJson()
        );

        // 401 – Unauthenticated
        $exceptions->render(function (AuthenticationException $_e, Request $request): mixed {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            $data    = app()->hasDebugModeEnabled() ? ['full_url' => urldecode($request->fullUrl())] : [];
            $message = 'API route not authorized: ไม่ได้รับอนุญาตเข้าถึงเส้นทาง API ' . $request->decodedPath();

            return Response::apiErrorResponse($message, 401, $data);
        });

        // 403 – Forbidden
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request): mixed {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            return Response::apiErrorResponse('คุณไม่มีสิทธิ์เข้าถึง', 403, ['reason' => 'Forbidden'], $e->getHeaders());
        });

        // 404 – Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request): mixed {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            $data    = app()->hasDebugModeEnabled() ? ['full_url' => urldecode($request->fullUrl())] : [];
            $message = 'API route not found: ไม่พบเส้นทาง API ' . $request->decodedPath();

            return Response::apiErrorResponse($message, 404, $data, $e->getHeaders());
        });

        // 405 – Method Not Allowed
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): mixed {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            $headers = $e->getHeaders();
            $data    = [
                'requested_path'  => $request->decodedPath(),
                'method_used'     => $request->method(),
                'allowed_methods' => $headers['Allow'] ?? 'GET, HEAD',
            ];

            return Response::apiErrorResponse('HTTP Method ไม่รองรับสำหรับเส้นทางนี้', 405, $data, $headers);
        });

        // 429 – Too Many Requests
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request): mixed {
            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            $headers = $e->getHeaders();
            $data    = [
                'reset_in'  => $headers['Retry-After'] ?? 60,
                'limit'     => $headers['X-RateLimit-Limit'] ?? null,
                'remaining' => $headers['X-RateLimit-Remaining'] ?? 0,
            ];

            return Response::apiErrorResponse('Too many requests: กรุณารอสักครู่', 429, $data, $headers);
        });

        // 500 – Internal Server Error (จับทุก Exception ที่เหลือ)
        $exceptions->render(function (Throwable $e, Request $request): mixed {
            // ปล่อย ValidationException ให้ Laravel จัดการเอง → คืน 422 JSON format มาตรฐาน
            if ($e instanceof ValidationException) {
                return null;
            }

            if (! $request->is('api', 'api/*') && ! $request->expectsJson()) {
                return null;
            }

            $status  = $e instanceof HttpException ? $e->getStatusCode() : 500;
            $message = app()->hasDebugModeEnabled() ? $e->getMessage() : 'เกิดข้อผิดพลาดภายในระบบ';
            $data    = app()->hasDebugModeEnabled() ? [
                'type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'code' => $e->getCode(),
            ] : [];

            return Response::apiErrorResponse($message, $status, $data);
        });
    })->create();
