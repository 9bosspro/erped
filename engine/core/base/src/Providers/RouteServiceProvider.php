<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * RouteServiceProvider — โหลด route files ของ Core\Base package
 *
 * ค้นหา routes จาก engine/core/base/src/routes/ อัตโนมัติ
 * ถ้าไม่มีไฟล์ route จะ skip อย่างเงียบๆ โดยไม่ throw error
 *
 * รูปแบบไฟล์ที่รองรับ:
 *  - src/routes/api.php   → group ด้วย prefix 'api/' + middleware 'api'
 *  - src/routes/web.php   → ไม่มี prefix (ใช้ web middleware group)
 */
class RouteServiceProvider extends ServiceProvider
{
    /**
     * Directory ที่เก็บ route files
     */
    private string $routePath = __DIR__.'/../routes';

    /**
     * โหลด route files ถ้ามีอยู่
     */
    public function boot(): void
    {
        $apiRoute = $this->routePath.'/api.php';
        $webRoute = $this->routePath.'/web.php';

        if (file_exists($apiRoute)) {
            $this->loadRoutesFrom($apiRoute);
        }

        if (file_exists($webRoute)) {
            $this->loadRoutesFrom($webRoute);
        }
    }
}
