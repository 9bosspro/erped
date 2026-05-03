<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Contracts\Http\RateLimiting\RateLimiterConfiguratorInterface;
use Core\Base\Contracts\Http\RateLimiting\RequestFingerprinterInterface;
use Core\Base\Http\RateLimiting\RateLimitConfigurator;
use Core\Base\Http\RateLimiting\RequestFingerprinter;
use Illuminate\Support\ServiceProvider;

/**
 * RateLimitingServiceProvider — ลงทะเบียนและตั้งค่า Rate Limiting services
 *
 * รวมศูนย์การจัดการ Rate Limiting ไว้ที่เดียวใน Core\Base เพื่อให้:
 *   - ย้ายหรือ swap implementation ได้โดยแก้ที่ provider นี้ที่เดียว
 *   - mock ใน test ได้ง่ายผ่าน interface binding
 *   - ลด coupling ระหว่าง Master package กับ rate limiting internals
 *   - Core จัดการ boot ตัวเองได้โดยไม่ต้องพึ่ง Master
 */
class RateLimitingServiceProvider extends ServiceProvider
{
    /**
     * ลงทะเบียน Rate Limiting services เข้า container
     */
    public function register(): void
    {
        $this->app->singleton(RequestFingerprinterInterface::class, RequestFingerprinter::class);
        $this->app->singleton(RateLimiterConfiguratorInterface::class, RateLimitConfigurator::class);
    }

    /**
     * Boot — เรียก configure() เพื่อตั้งค่า Rate Limiter ทั้งหมด
     * ทำงานอัตโนมัติใน Core โดยไม่ต้องพึ่ง Master
     */
    public function boot(RateLimiterConfiguratorInterface $configurator): void
    {
        $configurator->configure();
    }
}
