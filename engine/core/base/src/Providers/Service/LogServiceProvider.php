<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Log\AuditService;
use Core\Base\Services\Log\Contracts\AuditServiceInterface;
use Core\Base\Support\Helpers\Logs\AppLogger;
use Core\Base\Support\Helpers\Logs\Contracts\AppLoggerInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * LogServiceProvider — ลงทะเบียน Service สำหรับ Logging
 *
 * ลงทะเบียน:
 * - AppLogger — singleton สำหรับจัดการ log กลางของระบบ
 *
 * ใช้ DeferrableProvider เพื่อโหลด service เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class LogServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected const string PACKAGE_NAME = 'ppp-base-log';

    /**
     * ลงทะเบียน services เข้า IoC container
     * (ปรับให้ยึด Interface เป็นหลักเพื่อความยืดหยุ่นในการสลับ Driver ในอนาคต)
     */
    public function register(): void
    {
        // ── 1. Bind Interfaces to Concretes ───────────────────────
        $this->app->singleton(AppLoggerInterface::class, AppLogger::class);
        $this->app->singleton(AuditServiceInterface::class, AuditService::class);

        // ลงทะเบียน LogParserService เพิ่มเติม (ไม่มี Interface)
        $this->app->singleton(\Core\Base\Services\Log\LogParserService::class);

        // ── 2. Register Short Aliases (Facades/Helpers) ───────────
        $this->app->alias(AppLoggerInterface::class, 'core.logger');
        $this->app->alias(AuditServiceInterface::class, 'core.audit');
        $this->app->alias(\Core\Base\Services\Log\LogParserService::class, 'core.log.parser');

        // ── 3. Backward Compatibility Aliases ─────────────────────
        // เผื่อมีโค้ดบางส่วนไป type-hint คลาสตรงๆ จะได้ singleton ตัวเดียวกันกับ Interface
        $this->app->alias(AppLoggerInterface::class, AppLogger::class);
        $this->app->alias(AuditServiceInterface::class, AuditService::class);
    }

    /**
     * คืนรายชื่อ services ที่ provider นี้ provide (DeferrableProvider)
     * Laravel จะ defer การโหลด provider จนกว่าจะมีการร้องขอ service เหล่านี้
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            // Interfaces
            AppLoggerInterface::class,
            AuditServiceInterface::class,

            // Concretes
            AppLogger::class,
            AuditService::class,
            \Core\Base\Services\Log\LogParserService::class,

            // Short Aliases
            'core.logger',
            'core.audit',
            'core.log.parser',
        ];
    }
}
