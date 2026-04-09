<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Contracts\Http\RateLimiting\RateLimiterConfiguratorInterface;
use Core\Base\Contracts\Http\RateLimiting\RequestFingerprinterInterface;
use Core\Base\Http\RateLimiting\RateLimitConfigurator;
use Core\Base\Http\RateLimiting\RequestFingerprinter;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * RateLimitingServiceProvider — ลงทะเบียน Rate Limiting services เข้า DI container
 *
 * รวมศูนย์การจัดการ Rate Limiting ไว้ที่เดียวใน Core\Base เพื่อให้:
 *   - ย้ายหรือ swap implementation ได้โดยแก้ที่ provider นี้ที่เดียว
 *   - mock ใน test ได้ง่ายผ่าน interface binding
 *   - ลด coupling ระหว่าง Master package กับ rate limiting internals
 *
 * การ call configure() ยังคงอยู่ใน MasterServiceProvider::boot()
 * เพื่อให้แน่ใจว่า oauth2 config ถูก load ก่อน
 */
class RateLimitingServiceProvider extends ServiceProvider implements DeferrableProvider
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
     * คืนรายชื่อ services ที่ provider นี้ provide
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            RequestFingerprinterInterface::class,
            RateLimiterConfiguratorInterface::class,
        ];
    }
}
