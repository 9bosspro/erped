<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Cache\CacheService;
use Core\Base\Support\Helpers\Cache\CacheManager;
use Core\Base\Support\Helpers\Cache\Contracts\CacheManagerInterface;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * CacheServiceProvider — ลงทะเบียน Service สำหรับ Cache
 *
 * ลงทะเบียน:
 * - CacheService   — singleton สำหรับจัดการ cache กลาง
 * - CacheManager   — singleton wrapper ครอบ cache driver
 *
 * ใช้ DeferrableProvider เพื่อโหลด service เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class CacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected const string PACKAGE_NAME = 'ppp-base-cache';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        $this->app->singleton(CacheService::class);
        $this->app->alias(CacheService::class, 'core.base.cache');

        $this->app->singleton(CacheManager::class);
        $this->app->alias(CacheManager::class, 'core.cache');
        $this->app->alias(CacheManager::class, CacheManagerInterface::class);
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
            CacheService::class,
            'core.base.cache',
            CacheManager::class,
            CacheManagerInterface::class,
            'core.cache',
        ];
    }
}
