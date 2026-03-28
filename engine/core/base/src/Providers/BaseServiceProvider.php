<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Support\ServiceProvider;

/**
 * BaseServiceProvider — Entry point ของ Core\Base package
 *
 * หน้าที่:
 *  1. ลงทะเบียน sub-providers ผ่าน $providers / $webProviders array
 *  2. Bootstrap runtime settings (forceSSL, PHP INI)
 *
 * การเพิ่ม Provider ใหม่:
 *  - เพิ่มใน $providers สำหรับ API + Web
 *  - เพิ่มใน $webProviders สำหรับ Web เท่านั้น
 *  ไม่ต้อง override registerProviders()
 */
class BaseServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected const PACKAGE_NAME = 'ppp-base';

    /**
     * Providers ที่โหลดเสมอ (API + Web)
     *
     * @var array<int, class-string>
     */
    protected array $providers = [
        CoreServiceProvider::class,
        CoreEventServiceProvider::class,
        \Core\Master\Providers\MasterServiceProvider::class,
        StorageServiceProvider::class,
    ];

    /**
     * Providers ที่โหลดเฉพาะ Web request (ไม่ใช่ API)
     *
     * @var array<int, class-string>
     */
    protected array $webProviders = [
        \Core\Themes\Providers\ThemeServiceProvider::class,
    ];

    /**
     * ลงทะเบียน services เข้า container
     * register() รันก่อน boot() เสมอ
     */
    public function register(): void
    {
        $this->setNamespace('Core\Base');
        $this->registerProviders();
    }

    /**
     * Bootstrap services
     * รันหลัง register() ของทุก provider เสร็จสิ้น
     */
    public function boot(): void
    {
        $this->forceSSL();
        $this->configureIni();
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * ลงทะเบียน sub-providers ตาม request context
     *
     * Override method นี้ใน subclass ถ้าต้องการ logic พิเศษ
     * ปกติเพียงเพิ่มใน $providers / $webProviders arrays เท่านั้น
     */
    protected function registerProviders(): void
    {
        foreach ($this->providers as $provider) {
            $this->app->register($provider);
        }

        if (! is_api_request()) {
            foreach ($this->webProviders as $provider) {
                $this->app->register($provider);
            }
        }
    }
}
