<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Support\Helpers\Logs\AppLogger;
use Core\Base\Support\Helpers\Logs\Contracts\AppLoggerInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * CacheServiceProvider — ลงทะเบียน Service สำหรับ Cache
 *
 * ลงทะเบียน:
 * - CacheService — singleton สำหรับจัดการ cache กลาง
 *
 * ใช้ DeferrableProvider เพื่อโหลด service เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class LogServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected const PACKAGE_NAME = 'ppp-base-log';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        // ── Logger ────────────────────────────────────────────────
        $this->app->singleton(AppLogger::class);
        $this->app->alias(AppLogger::class, 'core.logger');
        $this->app->alias(AppLogger::class, AppLoggerInterface::class);
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
            AppLogger::class,
            AppLoggerInterface::class,
            'core.logger',
        ];
    }
}
