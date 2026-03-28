<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Core\Base\Support\Helpers\File\PhpConfigManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * LoadAndPublishDataTrait — โหลดและเผยแพร่ resources ของ module
 *
 * ครอบคลุม:
 *  - Configurations, Constants, Helpers
 *  - Translations (PHP + JSON)
 *  - Views, Anonymous Components, Theme Views
 *  - Routes, Migrations, Factories, Seeders
 *  - Assets (public), Console Commands
 *  - Bootstrap: forceSSL, PHP INI
 *
 * ใช้ PathResolverTrait สำหรับ path resolution ทั้งหมด
 * ทุก method คืน static เพื่อรองรับ method chaining
 */
trait LoadAndPublishDataTrait
{
    use PathResolverTrait;

    // ─────────────────────────────────────────────────────────────────
    //  Translations
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและ publish translations (PHP files)
     *
     * @param  string  $tag  tag สำหรับ artisan vendor:publish
     */
    public function loadAndPublishTranslations(string $tag = 'lang'): static
    {
        $path = $this->getTranslationsPath();
        $namespace = $this->getDashedNamespace();

        if (! File::isDirectory($path)) {
            return $this;
        }

        $this->loadTranslationsFrom($path, $namespace);
        $this->publishes(
            [$path => lang_path('vendor'.DIRECTORY_SEPARATOR.$namespace)],
            $namespace.'-'.$tag,
        );

        return $this;
    }

    /**
     * โหลด JSON translations (สำหรับภาษาไทยและ multi-locale)
     */
    public function loadAndPublishTranslationsjson(): static
    {
        $path = $this->getTranslationsPath();

        if (File::isDirectory($path)) {
            $this->loadJsonTranslationsFrom($path);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Configurations
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและ publish configuration files
     *
     * @param  array<int, string>  $fileNames  ชื่อไฟล์ (ไม่ต้องใส่ .php)
     *                                         ถ้าว่างจะโหลดทั้ง config folder
     * @param  string  $tag  tag สำหรับ artisan vendor:publish
     */
    protected function loadAndPublishConfigurations(array $fileNames = [], string $tag = 'config'): static
    {
        $configPath = $this->getConfigPath();

        if (empty($fileNames)) {
            $fileNames = File::isDirectory($configPath)
                ? $this->getPhpFileNames($configPath)
                : [];
        }

        foreach ($fileNames as $file) {
            $fullPath = $configPath.Str::finish($file, '.php');

            if (! File::exists($fullPath)) {
                continue;
            }

            $relativeName = Str::beforeLast(
                $this->getRelativePathFromModule($fullPath, 'config'),
                '.php',
            );

            $configKey = $relativeName === 'config'
                ? $this->getDottedNamespace()
                : $this->getDottedNamespace().'.'.str_replace(DIRECTORY_SEPARATOR, '.', $relativeName);

            $this->mergeConfigFrom($fullPath, $configKey);

            if ($this->app->runningInConsole()) {
                $this->publishes([
                    $fullPath => config_path(
                        $this->getDottedNamespace().DIRECTORY_SEPARATOR.$relativeName.'.php',
                    ),
                ], $this->getDottedNamespace().'-'.$tag);
            }
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Constants
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลด PHP constant files
     *
     * @param  array<int, string>  $files  ชื่อไฟล์ (ไม่ต้องใส่ .php)
     *                                     ถ้าว่างโหลดทั้ง constants folder
     */
    protected function loadConstants(array $files = []): static
    {
        $constantsPath = $this->getConstantsPath();

        if (empty($files)) {
            $files = File::isDirectory($constantsPath)
                ? $this->getPhpFileNames($constantsPath)
                : [];
        }

        foreach ($files as $file) {
            $fullPath = $constantsPath.Str::finish($file, '.php');

            if (! File::exists($fullPath)) {
                continue;
            }

            try {
                require_once $fullPath;
            } catch (Throwable $e) {
                logger()->error("Failed to load constant: {$fullPath} — ".$e->getMessage());
            }
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลด helper files (ลงท้ายด้วย Helper.php)
     *
     * @param  array<int, string>  $files  prefix ของไฟล์ เช่น 'Common' → CommonHelper.php
     *                                     ถ้าว่างโหลดทุกไฟล์ใน helpers folder
     */
    protected function loadHelpers(array $files = []): static
    {
        $helpersPath = $this->getHelpersPath();

        if (empty($files)) {
            $files = File::isDirectory($helpersPath)
                ? $this->getPhpFileNames($helpersPath)
                : [];
        }

        foreach ($files as $file) {
            $fullPath = $helpersPath.Str::finish($file, 'Helper.php');

            if (! File::exists($fullPath)) {
                continue;
            }

            try {
                require_once $fullPath;
            } catch (Throwable $e) {
                logger()->error("Failed to load helper: {$fullPath} — ".$e->getMessage());
            }
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Views
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและ publish views
     *
     * @param  string  $tag  tag สำหรับ artisan vendor:publish
     */
    protected function loadAndPublishViews(string $tag = 'views'): static
    {
        $viewsPath = $this->getViewsPath();
        $namespace = $this->getDashedNamespace();

        if (! File::isDirectory($viewsPath)) {
            return $this;
        }

        $this->loadViewsFrom($viewsPath, $namespace);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$viewsPath => resource_path('views'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$namespace)],
                $namespace.'-'.$tag,
            );
        }

        return $this;
    }

    /**
     * Prepend view path สำหรับ theme override
     * ทำให้ module views มี priority สูงกว่า vendor default
     */
    protected function loadAndPublishThemeViews(): static
    {
        $viewsPath = $this->getViewsPath();

        if (File::isDirectory($viewsPath)) {
            View::prependLocation($viewsPath);
        }

        return $this;
    }

    /**
     * โหลด Blade anonymous components
     */
    protected function loadAnonymousComponents(): static
    {
        $componentsPath = $this->getViewsPath().DIRECTORY_SEPARATOR.'components';

        if (File::isDirectory($componentsPath)) {
            $this->app['blade.compiler']->anonymousComponentPath(
                $componentsPath,
                $this->getDashedNamespace(),
            );
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Routes
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลด route files
     *
     * @param  array<int, string>|string  $fileNames  ชื่อไฟล์ route (ไม่ต้องใส่ .php)
     * @param  array<int, string>  $middleware  middleware สำหรับ route group (optional)
     *
     * @throws RuntimeException ถ้าไม่พบ route file
     */
    protected function loadRoutes(array|string $fileNames = ['web'], array $middleware = []): static
    {
        foreach ((array) $fileNames as $fileName) {
            $filePath = $this->getRouteFilePath($fileName);

            if (! File::exists($filePath)) {
                throw new RuntimeException("Route file not found: {$filePath}");
            }

            if (! empty($middleware)) {
                $this->app['router']->middlewareGroup($this->getDashedNamespace(), $middleware);
            }

            $this->loadRoutesFrom($filePath);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Database
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลด database migrations
     */
    protected function loadMigrations(): static
    {
        $path = $this->getMigrationsPath();

        if (File::isDirectory($path)) {
            $this->loadMigrationsFrom($path);
        }

        return $this;
    }

    /**
     * โหลด model factories
     */
    protected function loadFactories(): static
    {
        $path = $this->getFactoriesPath();

        if (File::isDirectory($path)) {
            $this->loadFactoriesFrom($path);
        }

        return $this;
    }

    /**
     * โหลด database seeders
     */
    protected function loadSeeders(): static
    {
        $path = $this->getSeedersPath();

        if (File::isDirectory($path)) {
            $this->loadSeedersFrom($path);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Assets & Commands
    // ─────────────────────────────────────────────────────────────────

    /**
     * Publish static assets ไปยัง public/vendor/core/{namespace}
     *
     * @param  string  $tag  tag สำหรับ artisan vendor:publish
     */
    protected function publishAssets(string $tag = 'public'): static
    {
        $assetsPath = $this->getAssetsPath();
        $namespace = $this->getDashedNamespace();

        if (File::isDirectory($assetsPath)) {
            $this->publishes(
                [$assetsPath => public_path('vendor'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.$namespace)],
                $namespace.'-'.$tag,
            );
        }

        return $this;
    }

    /**
     * ลงทะเบียน console commands (เฉพาะ console context เท่านั้น)
     *
     * @param  array<int, class-string>  $commands
     */
    protected function loadCommandsAndSchedules(array $commands): static
    {
        if ($this->app->runningInConsole()) {
            $this->commands($commands);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Bootstrap: SSL + PHP INI
    // ─────────────────────────────────────────────────────────────────

    /**
     * Force HTTPS ใน production/staging หรือเมื่อ config กำหนด
     *
     * ตรวจสอบ 2 เงื่อนไข:
     *  1. config('core.base.myapp.force_ssl') = true
     *  2. environment เป็น production/staging และไม่ได้รัน unit tests
     */
    protected function forceSSL(): static
    {
        $shouldForce = config('core.base.myapp.force_ssl')
            || (app()->environment(['production', 'staging']) && ! app()->runningUnitTests());

        if ($shouldForce) {
            URL::forceScheme('https');
        }

        return $this;
    }

    /**
     * ปรับ memory_limit และ max_execution_time จาก config
     *
     * Logic: เพิ่มได้อย่างเดียว ไม่ลดลงจากค่าปัจจุบัน
     * เพื่อป้องกันกรณีที่ server ตั้งค่าไว้สูงกว่า config
     */
    protected function configureIni(): static
    {
        $helper = new PhpConfigManager;
        $baseConfig = $this->app['config']->get('core.base.general', []);

        $this->applyMemoryLimit($helper, $baseConfig);
        $this->applyMaxExecutionTime($helper, $baseConfig);

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private Helpers
    // ─────────────────────────────────────────────────────────────────

    /**
     * ปรับ memory_limit — เพิ่มได้อย่างเดียว ไม่ลด
     *
     * @param  array<string, mixed>  $config
     */
    private function applyMemoryLimit(PhpConfigManager $helper, array $config): void
    {
        $current = (string) @ini_get('memory_limit');
        $currentBytes = $helper->convertHrToBytes($current);

        $configured = Arr::get($config, 'memory_limit')
            ?? ($helper->isIniValueChangeable('memory_limit') ? '1024M' : $current);
        $configBytes = $helper->convertHrToBytes($configured);

        if ($currentBytes !== -1 && ($configBytes === -1 || $configBytes > $currentBytes)) {
            $helper->iniSet('memory_limit', $configured);
        }
    }

    /**
     * ปรับ max_execution_time — เพิ่มได้อย่างเดียว ไม่ลด
     *
     * @param  array<string, mixed>  $config
     */
    private function applyMaxExecutionTime(PhpConfigManager $helper, array $config): void
    {
        $current = (int) @ini_get('max_execution_time');
        $configured = (int) Arr::get($config, 'max_execution_time', 0);

        if ($current > 0 && $configured > $current) {
            $helper->iniSet('max_execution_time', (string) $configured);
        }
    }

    /**
     * คืนรายชื่อไฟล์ .php ใน directory (ไม่ recursive, ไม่รวมนามสกุล)
     *
     * ใช้ File::files() (Laravel) แทน scandir/RecursiveIterator (raw PHP)
     * เพื่อ consistency กับ framework และ testability
     *
     * @return array<int, string>
     */
    private function getPhpFileNames(string $directory): array
    {
        return collect(File::files($directory))
            ->filter(fn ($f) => $f->getExtension() === 'php')
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->values()
            ->all();
    }
}
