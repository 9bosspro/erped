<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogRequests — บันทึก request log สำหรับ monitoring
 *
 * Performance:
 *   - ข้าม health check route `/up` เพื่อลด log noise จาก load balancer probe
 *   - ข้าม HEAD requests ที่ไม่มีประโยชน์ในการ debug
 *   - ใช้ lazy evaluation: microtime() เรียกเฉพาะเมื่อจะ log จริง
 */
class LogRequests
{
    /**
     * Paths ที่ไม่ต้อง log — cache ระดับ static
     *
     * @var array<int, string>
     */
    private const array SKIP_PATHS = ['up', '_debugbar', '_ignition'];

    /**
     * จัดการ request ที่เข้ามา
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ข้าม HEAD requests และ health/tool routes ที่ probe บ่อย
        if ($request->isMethod('HEAD') || $request->is(self::SKIP_PATHS)) {
            return $next($request);
        }

        $start = microtime(true);

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);

        Log::channel('daily')->info('Request handled', [
            'method'      => $request->method(),
            'path'        => $request->path(),
            'status'      => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id'     => $request->user()?->id,
            'ip'          => $request->ip(),
        ]);

        return $response;
    }
}
