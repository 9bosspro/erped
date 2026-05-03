<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ForceJsonResponse — บังคับให้ API request/response เป็น JSON เสมอ
 *
 * ใช้ใน API middleware group เพื่อ:
 *  - ตั้ง Accept: application/json ใน request (ให้ Laravel return JSON errors)
 *  - ตั้ง Content-Type: application/json ใน response
 */
class ForceJsonResponse
{
    /**
     * จัดการ request ที่เข้ามา
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        $response = $next($request);

        if (! $response->headers->has('Content-Type')) {
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
