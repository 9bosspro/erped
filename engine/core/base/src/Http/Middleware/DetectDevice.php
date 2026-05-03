<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use Closure;
use DeviceDetector\DeviceDetector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DetectDevice — ตรวจจับอุปกรณ์และ browser จาก User-Agent
 *
 * แปะข้อมูลอุปกรณ์เข้า request attributes เพื่อให้ controller นำไปใช้ต่อ
 * โดยไม่ต้อง parse User-Agent ซ้ำ
 */
class DetectDevice
{
    /**
     * จัดการ request ที่เข้ามา
     */
    public function handle(Request $request, Closure $next): Response
    {
        $dd = new DeviceDetector((string) $request->userAgent());
        $dd->parse();

        $request->attributes->set('device', $dd->getDeviceName());
        $request->attributes->set('browser', $dd->getClient('name'));

        return $next($request);
    }
}
