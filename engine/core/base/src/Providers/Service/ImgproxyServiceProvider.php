<?php

declare(strict_types=1);

namespace Core\Base\Providers\Service;

use Core\Base\Services\Imgproxy\Contracts\ImgproxyServiceInterface;
use Core\Base\Services\Imgproxy\ImgproxyService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * ImgproxyServiceProvider — ลงทะเบียน Service สำหรับ Image Proxy
 *
 * ลงทะเบียน:
 * - ImgproxyService — singleton สำหรับสร้าง URL และประมวลผลรูปภาพผ่าน Imgproxy
 *
 * ใช้ DeferrableProvider เพื่อโหลด service เฉพาะเมื่อถูกร้องขอจริงๆ
 */
class ImgproxyServiceProvider extends ServiceProvider implements DeferrableProvider
{
    protected const string PACKAGE_NAME = 'ppp-base-imgproxy';

    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        $this->app->singleton(ImgproxyService::class);
        $this->app->alias(ImgproxyService::class, 'core.imgproxy');
        $this->app->alias(ImgproxyService::class, ImgproxyServiceInterface::class);
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
            ImgproxyService::class,
            ImgproxyServiceInterface::class,
            'core.imgproxy',
        ];
    }
}
