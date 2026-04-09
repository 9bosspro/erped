<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;

use App\Http\Middleware\LogRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Support\Facades\Response;

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
        $middleware->trustProxies(
            at: explode(',', env('TRUSTED_PROXIES', '172.17.0.0/24')),
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
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(function (Throwable $e) {
            $request = request();

            return [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
            ];
        });

        $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
            return $request->is('api', 'api/*') || $request->expectsJson();
        });
        // 401 – Unauthenticated
        $exceptions->render(function (Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $data = app()->hasDebugModeEnabled()
                    ? ['full_url' => urldecode($request->fullUrl())]
                    : [];
                $message = 'API route not authorized: ไม่ได้รับอนุญาตเข้าถึงเส้นทาง API ' . $request->decodedPath();

                return Response::apiErrorResponse($message, 401, $data);
            }
        });

        // 403 – Forbidden
        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();

                return Response::apiErrorResponse('คุณไม่มีสิทธิ์เข้าถึง', 403, ['reason' => 'Forbidden'], $headers);
            }
        });

        // 404 – Not Found
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();
                $data = app()->hasDebugModeEnabled()
                    ? ['full_url' => urldecode($request->fullUrl())]
                    : [];
                $message = 'API route not found: ไม่พบเส้นทาง API ' . $request->decodedPath();

                return Response::apiErrorResponse($message, 404, $data, $headers);
            }
        });

        // 405 – Method Not Allowed
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();
                $data = [
                    'requested_path' => $request->decodedPath(),
                    'method_used' => $request->method(),
                    'allowed_methods' => $headers['Allow'] ?? 'GET, HEAD',
                ];

                return Response::apiErrorResponse('HTTP Method ไม่รองรับสำหรับเส้นทางนี้', 405, $data, $headers);
            }
        });

        // 429 – Too Many Requests
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();
                $data = [
                    'reset_in' => $headers['Retry-After'] ?? 60,
                    'limit' => $headers['X-RateLimit-Limit'] ?? null,
                    'remaining' => $headers['X-RateLimit-Remaining'] ?? 0,
                ];

                return Response::apiErrorResponse('Too many requests: กรุณารอสักครู่', 429, $data, $headers);
            }
        });

        // 500 – Internal Server Error (จับทุก Exception ที่เหลือ)
        $exceptions->render(function (Throwable $e, Request $request) {
            // ปล่อย ValidationException ให้ Laravel จัดการเอง → คืน 422 JSON format มาตรฐาน
            if ($e instanceof Illuminate\Validation\ValidationException) {
                return;
            }

            if ($request->is('api', 'api/*') || $request->expectsJson()) {
                $status = $e instanceof HttpException
                    ? $e->getStatusCode()
                    : 500;

                $message = app()->hasDebugModeEnabled()
                    ? $e->getMessage()
                    : 'เกิดข้อผิดพลาดภายในระบบ';

                $data = app()->hasDebugModeEnabled() ? [
                    'type' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'code' => $e->getCode(),
                ] : [];

                return Response::apiErrorResponse($message, $status, $data);
            }
        });
    })->create();
