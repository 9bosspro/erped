<?php

declare(strict_types=1);

namespace Slave\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;


/**
 * MasterServiceProvider — bootstrap OAuth2 และ Rate Limiting
 *
 * ทำหน้าที่เป็น thin orchestrator เท่านั้น — ไม่มี business logic
 * bind interfaces กับ implementations เพื่อรองรับ Dependency Inversion Principle
 * ทำให้ mock ใน test ได้และสลับ implementation ได้โดยไม่แก้ code
 *
 * Configurators ที่จัดการ:
 *   - PassportConfiguratorInterface    → token expiration + custom models
 *   - RateLimiterConfiguratorInterface → rate limiting ทุก endpoint
 */
class SlaveServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    /**
     * ชื่อ package สำหรับ publish assets
     */
    protected const PACKAGE_NAME = 'ampol-slave';

    /**
     * ลงทะเบียน services เข้า DI container
     *
     * bind interfaces กับ implementations — ช่วยให้ swap implementation
     * หรือ mock ใน test ได้โดยไม่แก้ code ส่วนอื่น
     */
    public function register(): void
    {
        $this->setNamespace('Slave');
    }

    /**
     * Bootstrap services หลัง register เสร็จสิ้น
     *
     * เรียก configure() ผ่าน interface เพื่อไม่ผูกกับ concrete class
     */
    public function boot(): void
    {
        // $this->loadConstants(['constants']);
        // $this->loadAndPublishConfigurations(['oauth2', 'passport']);
        // $this->loadHelpers(['App']);
    }
}
