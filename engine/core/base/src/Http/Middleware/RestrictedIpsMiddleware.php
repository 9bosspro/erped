<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * RestrictedIpsMiddleware — บล็อก IP ที่อยู่ใน blacklist
 *
 * อ่าน IP ที่ถูกบล็อกจาก config('security.restricted_ips')
 * ตอบ 403 ทันทีเมื่อ IP ของ client ตรงกับรายการ
 */
class RestrictedIpsMiddleware
{
    /**
     * จัดการ request ที่เข้ามา
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $restrictedIps */
        $restrictedIps = (array) config('security.restricted_ips', []);

        if (in_array($request->ip(), $restrictedIps, strict: true)) {
            abort(403, 'Access denied – Your IP address is blocked.');
        }

        return $next($request);
    }
}
