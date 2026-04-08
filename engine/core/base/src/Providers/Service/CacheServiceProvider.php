<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Cache\CacheService;
use Core\Base\Support\Helpers\Cache\CacheManager;
use Core\Base\Support\Helpers\Cache\Contracts\CacheManagerInterface;
use Core\Base\Traits\LoadAndPublishDataTrait;
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
class CacheServiceProvider extends ServiceProvider implements DeferrableProvider
{
    //  use LoadAndPublishDataTrait;
    protected const PACKAGE_NAME = 'ppp-base-cache';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        // $this->setNamespace('Core\Base');
        $this->app->singleton(CacheService::class);
        $this->app->alias(CacheService::class, 'core.base.cache');
        // ── Cache ──────────────────────────────────────────────────
        $this->app->singleton(CacheManager::class);
        $this->app->alias(CacheManager::class, 'core.cache');
        $this->app->alias(CacheManager::class, CacheManagerInterface::class);
        // Imgproxy
        //  $this->app->alias(ImgproxyService::class, ImgproxyServiceInterface::class);
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
        ];
    }
}
