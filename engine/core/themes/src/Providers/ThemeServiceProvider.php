<?php

declare(strict_types=1);

namespace Core\Themes\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Core\Themes\Services\ModuleContextService;
use Illuminate\Support\ServiceProvider;

/**
 * ThemeServiceProvider — ลงทะเบียน module context และ theme helpers
 */
class ThemeServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    /**
     * ลงทะเบียน services เข้า DI container
     */
    public function register(): void
    {
        $this->setNamespace('Core\Themes');

        $this->app->singleton('module.context', fn () => new ModuleContextService);
        $this->app->alias('module.context', ModuleContextService::class);
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
