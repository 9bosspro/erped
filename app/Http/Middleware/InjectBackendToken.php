<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * InjectBackendToken — เพิ่ม Backend access token เข้า Inertia shared props
 *
 * ให้ React components เข้าถึง backend token สำหรับ client-side API calls
 * Token จะถูกส่งผ่าน Inertia shared props (ไม่ exposed ใน URL/cookie)
 */
class InjectBackendToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // เก็บ backend token ไว้ใน request attributes
        // สำหรับ HandleInertiaRequests middleware ดึงไปใส่ shared props
        $request->attributes->set('backend_token', session('backend_access_token'));

        return $next($request);
    }
}
