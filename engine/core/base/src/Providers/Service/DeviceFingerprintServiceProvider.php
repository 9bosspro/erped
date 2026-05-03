<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Session\Contracts\DeviceFingerprintServiceInterface;
use Core\Base\Services\Session\DeviceFingerprintService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * DeviceFingerprintServiceProvider — ลงทะเบียน Service สำหรับ Device Fingerprint
 *
 * ลงทะเบียนเฉพาะ Service layer ที่เป็น transient (bind):
 * - DeviceFingerprintService    — orchestrator หลักสำหรับ device fingerprint (มี per-request state)
 *
 * ใช้ DeferrableProvider เพื่อโหลด services เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class DeviceFingerprintServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        // ── Session / Device ───────────────────────────────────────────────
        $this->app->bind(DeviceFingerprintService::class);
        $this->app->alias(DeviceFingerprintService::class, 'core.session.device_fingerprint');
        $this->app->alias(DeviceFingerprintService::class, DeviceFingerprintServiceInterface::class);
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * ต้องระบุ **ทุก** binding ที่ลงทะเบียนใน register() รวมถึง alias
     * เพื่อให้ DeferrableProvider ทำงานได้ถูกต้องทุกเส้นทางการ resolve
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            DeviceFingerprintService::class,
            DeviceFingerprintServiceInterface::class,
            'core.session.device_fingerprint',
        ];
    }
}
