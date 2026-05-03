<?php

declare(strict_types=1);

namespace Core\Themes\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Core\Themes\Services\Contracts\ModuleContextServiceInterface;
use Core\Themes\Services\ModuleContextService;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * ThemeServiceProvider — ลงทะเบียน module context และ theme helpers
 */
class ThemeServiceProvider extends ServiceProvider implements DeferrableProvider
{
    use LoadAndPublishDataTrait;

    /**
     * ลงทะเบียน services เข้า DI container
     */
    public function register(): void
    {
        $this->setNamespace('Core\Themes');

        $this->app->singleton(ModuleContextService::class, fn () => new ModuleContextService);
        $this->app->alias(ModuleContextService::class, ModuleContextServiceInterface::class);
        $this->app->alias(ModuleContextService::class, 'module.context');
    }

    /**
     * รายการ bindings ที่ provider นี้จัดการ
     *
     * @return array<int, string>
     */
    public function provides(): array
    {
        return [
            ModuleContextService::class,
            ModuleContextServiceInterface::class,
            'module.context',
        ];
    }

    /**
     * Bootstrap services
     */
    public function boot(): void
    {
        $this->loadHelpers(['Theme']);
        $this->loadAndPublishThemeViews();
        $this->loadAndPublishViews();
        $this->loadAnonymousComponents();
    }
}
