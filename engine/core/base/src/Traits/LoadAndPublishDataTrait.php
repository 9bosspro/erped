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
 * Trait LoadAndPublishDataTrait
 *
 * ทะเบียนและเผยแพร่ทรัพยากร (Resources) สำหรับ Module ต่างๆ อย่างเป็นระบบ
 * ผสานแนวคิด High Performance (ลด I/O operation), Secure & Maintainable
 *
 * ความสามารถครอบคลุม:
 * - Configurations, Constants, Helpers (ใช้ glob และ native stat-cache เพื่อความเร็วสูงสุด)
 * - Translations (PHP, JSON), Views, Components
 * - Routes, Migrations, Factories, Seeders
 * - Environment Security (Force SSL, PHP INI tuning)
 */
trait LoadAndPublishDataTrait
{
    use PathResolverTrait;

    // ─────────────────────────────────────────────────────────────────
    //  Translations (ภาษา)
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและเตรียม Publish ไฟล์แปลภาษา (PHP Array)
     *
     * @param  string  $tag  ชื่อ Tag สำหรับ artisan vendor:publish (ค่าเริ่มต้น: 'lang')
     */
    public function loadAndPublishTranslations(string $tag = 'lang'): static
    {
        $path = $this->getTranslationsPath();

        // ระดับ Production: ใช้ is_dir() ตรงๆ แทน File::isDirectory() เพื่อดึงพลังการ Caching ของ PHP Native
        if (! is_dir($path)) {
            return $this;
        }

        $namespace = $this->getDashedNamespace();
        $this->loadTranslationsFrom($path, $namespace);

        // ตรวจสอบ Console mode เพื่อข้ามการ Publish ตอน HTTP Request (Performance)
        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$path => lang_path('vendor'.DIRECTORY_SEPARATOR.$namespace)],
                $namespace.'::'.$tag,
            );
        }

        return $this;
    }

    /**
     * โหลดไฟล์แปลภาษาแบบ JSON (สำหรับระบบรองรับหลายภาษาแบบรวดเร็ว)
     */
    public function loadAndPublishTranslationsJson(): static
    {
        $path = $this->getTranslationsPath();

        if (is_dir($path)) {
            $this->loadJsonTranslationsFrom($path);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Configurations (การตั้งค่า)
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและใช้งาน Configuration files ของ Module
     * พร้อมรองรับการ Publish ไปยัง config หลักของโปรเจ็กต์
     *
     * @param  array<int, string>  $fileNames  รายชื่อไฟล์ config ไม่ต้องใส่ .php (หากปล่อยว่างจะโหลดทั้งหมด)
     * @param  string  $tag  ชื่อ Tag สำหรับ artisan vendor:publish (ค่าเริ่มต้น: 'config')
     */
    public function loadAndPublishConfigurations(array $fileNames = [], string $tag = 'config'): static
    {
        $configPath = $this->getConfigPath();

        if (! is_dir($configPath)) {
            return $this;
        }

        $filesToLoad = empty($fileNames) ? $this->getPhpFileNames($configPath) : $fileNames;
        $dottedNamespace = $this->getDottedNamespace();

        // Batch publish array ลดการเรียก function publishes ถี่เกินไป
        $publishList = [];

        foreach ($filesToLoad as $file) {
            $fullPath = $configPath.Str::finish($file, '.php');

            if (! is_file($fullPath)) {
                continue;
            }

            $relativeName = Str::beforeLast($this->getRelativePathFromModule($fullPath, 'config'), '.php');
            $configKey = $relativeName === 'config'
                ? $dottedNamespace
                : $dottedNamespace.'::'.str_replace(DIRECTORY_SEPARATOR, '.', $relativeName);
            $this->mergeConfigFrom($fullPath, $configKey);

            if ($this->app->runningInConsole()) {
                $publishDest = config_path($dottedNamespace.DIRECTORY_SEPARATOR.$relativeName.'.php');
                $publishList[$fullPath] = $publishDest;
            }
        }

        if (! empty($publishList)) {
            $this->publishes($publishList, $dottedNamespace.'::'.$tag);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Constants & Helpers (ค่าคงที่และฟังก์ชันช่วยเหลือ)
    // ─────────────────────────────────────────────────────────────────

    /**
     * ดึงไฟล์ Constants (PHP) เข้าสู่ระบบ (Performance Optimized)
     *
     * @param  array<int, string>  $files  รายชื่อไฟล์ ไม่ต้องใส่ .php
     */
    public function loadConstants(array $files = []): static
    {
        return $this->loadFilesFromDirectory($this->getConstantsPath(), $files, '.php', 'constant');
    }

    /**
     * ดึงไฟล์ Helper Functions (PHP) เข้าสู่ระบบ
     *
     * @param  array<int, string>  $files  รายชื่อไฟล์ prefix (เช่น 'Common' เพื่อโหลด CommonHelper.php)
     */
    public function loadHelpers(array $files = []): static
    {
        return $this->loadFilesFromDirectory($this->getHelpersPath(), $files, 'Helper.php', 'helper');
    }

    // ─────────────────────────────────────────────────────────────────
    //  Views & Components (หน้าแสดงผลและคอมโพเนนต์)
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดและจัดเตรียม Views จาก Module
     * รองรับการ Override จาก Theme อัตโนมัติเมื่อทำการ Publish
     *
     * @param  string  $tag  ชื่อ Tag สำหรับ artisan vendor:publish
     */
    public function loadAndPublishViews(string $tag = 'views'): static
    {
        $viewsPath = $this->getViewsPath();

        if (! is_dir($viewsPath)) {
            return $this;
        }

        $namespace = $this->getDashedNamespace();
        $this->loadViewsFrom($viewsPath, $namespace);

        if ($this->app->runningInConsole()) {
            $this->publishes(
                [$viewsPath => resource_path('views'.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.$namespace)],
                $namespace.'::'.$tag,
            );
        }

        return $this;
    }

    /**
     * ทำให้ Views ของ Module มีความสำคัญลำดับแรกสุด รองรับการทำ Theme overriding
     */
    public function loadAndPublishThemeViews(): static
    {
        $viewsPath = $this->getViewsPath();

        if (is_dir($viewsPath)) {
            View::prependLocation($viewsPath);
        }

        return $this;
    }

    /**
     * ใช้งาน Blade Anonymous Components ที่ฝังอยู่ใน Module
     */
    public function loadAnonymousComponents(): static
    {
        $componentsPath = $this->getViewsPath().DIRECTORY_SEPARATOR.'components';

        if (is_dir($componentsPath)) {
            $this->app['blade.compiler']->anonymousComponentPath(
                $componentsPath,
                $this->getDashedNamespace(),
            );
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Routes (เส้นทาง API/Web)
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดไฟล์ Route ของ Module อย่างปลอดภัย
     *
     * @param  array<int, string>|string  $fileNames  ชื่อไฟล์ route เช่น 'web', 'api'
     * @param  array<int, string>  $middleware  กลุ่ม middleware พื้นฐานที่นำมาครอบ
     *
     * @throws RuntimeException หากระบุ route แล้วไม่พบไฟล์
     */
    public function loadRoutes(array|string $fileNames = ['web'], array $middleware = []): static
    {
        foreach ((array) $fileNames as $fileName) {
            $filePath = $this->getRouteFilePath($fileName);

            if (! is_file($filePath)) {
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
    //  Database Resources (ฐานข้อมูล)
    // ─────────────────────────────────────────────────────────────────

    /**
     * โหลดโครงสร้างฐานข้อมูล (Migrations)
     */
    public function loadMigrations(): static
    {
        $path = $this->getMigrationsPath();

        if (is_dir($path)) {
            $this->loadMigrationsFrom($path);
        }

        return $this;
    }

    /**
     * โหลดการจำลองข้อมูลสำหรับ Database (Factories)
     */
    /*  public function loadFactories(): static
     {
         $path = $this->getFactoriesPath();

         if (is_dir($path)) {
             $this->loadFactoriesFrom($path);
         }

         return $this;
     } */

    /**
     * โหลดชุดตัวอย่างข้อมูลเริ่มต้น (Seeders)
     */
    public function loadSeeders(): static
    {
        $path = $this->getSeedersPath();

        if (is_dir($path)) {
            $this->loadSeedersFrom($path);
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Assets, Commands & Security (ทรัพยากรอื่นๆ และความปลอดภัย)
    // ─────────────────────────────────────────────────────────────────

    /**
     * เปิดสิทธิ์ให้ย้าย (Publish) Static Assets (CSS, JS, Images) เข้าไปยัง public folder
     *
     * @param  string  $tag  ชื่อ Tag
     */
    public function publishAssets(string $tag = 'public'): static
    {
        if (! $this->app->runningInConsole()) {
            return $this;
        }

        $assetsPath = $this->getAssetsPath();
        if (is_dir($assetsPath)) {
            $namespace = $this->getDashedNamespace();
            $this->publishes(
                [$assetsPath => public_path('vendor'.DIRECTORY_SEPARATOR.'core'.DIRECTORY_SEPARATOR.$namespace)],
                $namespace.'-'.$tag,
            );
        }

        return $this;
    }

    /**
     * บรรจุ Artisan Commands เฉพาะเมื่อรันผ่าน Console
     *
     * @param  array<int, class-string>  $commands  คลาส Commands ย่อย
     */
    public function loadCommandsAndSchedules(array $commands): static
    {
        if ($this->app->runningInConsole() && ! empty($commands)) {
            $this->commands($commands);
        }

        return $this;
    }

    /**
     * บังคับให้ระบบส่งออกเป็น HTTPS
     * (ช่วยเพิ่ม Secure Layer เมื่อทำงานผ่าน Proxy/LoadBalancer)
     */
    public function forceSSL(): static
    {
        $shouldForce = config('core.base.myapp.force_ssl')
            || (app()->environment(['production', 'staging']) && ! app()->runningUnitTests());

        if ($shouldForce) {
            URL::forceScheme('https');
        }

        return $this;
    }

    /**
     * ควบคุมและจูนสภาพแวดล้อม PHP อย่างชาญฉลาด (PHP INI Tuning)
     * ทำหน้าที่เพิ่ม Limits ให้เพียงพอต่อ Business Logic ภายใน Module (ห้ามลด)
     */
    public function configureIni(): static
    {
        $helper = new PhpConfigManager;
        $baseConfig = $this->app['config']->get('core.base.general', []);

        $this->applyMemoryLimit($helper, $baseConfig);
        $this->applyMaxExecutionTime($helper, $baseConfig);

        return $this;
    }

    /**
     * Core logic สำหรับโหลดไฟล์ PHP เข้าสู่ Runtime ป้องกันการเขียน Source code ซ้ำซ้อน (DRY)
     *
     * @param  string  $path  พาร์ทโฟลเดอร์ของไฟล์
     * @param  array<string>  $files  ชื่อไฟล์ต่างๆ
     * @param  string  $suffix  นามสกุลไฟล์หรือ prefix (เช่น .php หรือ Helper.php)
     * @param  string  $type  ประเภทไฟล์ (สำหรับการล็อกเก็บประวัติ Error)
     */
    private function loadFilesFromDirectory(string $path, array $files, string $suffix, string $type): static
    {
        if (! is_dir($path)) {
            return $this;
        }

        $filesToLoad = empty($files) ? $this->getPhpFileNames($path, $suffix) : $files;

        foreach ($filesToLoad as $file) {
            $fullPath = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.Str::finish($file, $suffix);

            if (is_file($fullPath)) {
                try {
                    require_once $fullPath;
                } catch (Throwable $e) {
                    logger()->error("Failed to load {$type}: {$fullPath} — ".$e->getMessage());
                }
            }
        }

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private Methods (ระบบกลไกภายใน)
    // ─────────────────────────────────────────────────────────────────

    /**
     * ตรวจสอบและขยายหน่วยความจำ (Memory Limit) อย่างปลอดภัย
     */
    /** @param array<string, mixed> $config */
    private function applyMemoryLimit(PhpConfigManager $helper, array $config): void
    {
        $current = (string) @ini_get('memory_limit');
        $currentBytes = $helper->convertHrToBytes($current);

        $configVal = Arr::get($config, 'memory_limit');
        $configured = is_scalar($configVal) ? (string) $configVal : ($helper->isIniValueChangeable('memory_limit') ? '1024M' : $current);
        $configBytes = $helper->convertHrToBytes($configured);

        if ($currentBytes !== -1 && ($configBytes === -1 || $configBytes > $currentBytes)) {
            $helper->iniSet('memory_limit', $configured);
        }
    }

    /**
     * ปรับเพิ่มขีดจำกัดการประมวลผล (Max Execution Time) กรณีที่มีขนาดใหญ่
     */
    /** @param array<string, mixed> $config */
    private function applyMaxExecutionTime(PhpConfigManager $helper, array $config): void
    {
        $current = (int) @ini_get('max_execution_time');
        $confVal = Arr::get($config, 'max_execution_time', 0);
        $configured = is_scalar($confVal) ? (int) $confVal : 0;

        if ($current > 0 && $configured > $current) {
            $helper->iniSet('max_execution_time', (string) $configured);
        }
    }

    /**
     * ค้นหาไฟล์ .php (หรือระบุ suffix) ด้วย glob ซึ่งทำงานได้เร็วกว่าและประหยัด Resource I/O
     * และป้องกัน I/O Overhead จากการใช้ File::files() (Symfony Finder wrapper)
     *
     * @param  string  $directory  พาธหลัก
     * @param  string  $suffix  นามสกุลไฟล์ที่ต้องการ (default: .php)
     * @return array<int, string> คืนรายชื่อไฟล์แบบไม่มี suffix
     */
    private function getPhpFileNames(string $directory, string $suffix = '.php'): array
    {
        $files = glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*'.$suffix);

        if ($files === false) {
            return [];
        }

        $names = [];
        $suffixLength = strlen($suffix);

        foreach ($files as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                $names[] = substr($basename, 0, -$suffixLength);
            }
        }

        return $names;
    }
}
