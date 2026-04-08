<?php

declare(strict_types=1);

namespace Core\Themes\Services;

use Hexadog\ThemesManager\Facades\ThemesManager;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Nwidart\Modules\Facades\Module;

/**
 * ModuleContextService — ตรวจหา module ปัจจุบันและจัดการ theme
 *
 * Responsibilities:
 *   1. Module Detection  — ตรวจหา module จาก Route (controller / name / uri)
 *   2. Theme Activation  — ตั้งค่า theme และ register view paths
 *
 * State management:
 *   - $moduleCache       : cache module ต่อ request (ป้องกัน query ซ้ำ)
 *   - $activatedThemes   : themes ที่ activate แล้ว (ป้องกัน activate ซ้ำ)
 *   - $processedModules  : modules ที่ register view paths แล้ว
 *   - $allModules        : cache Module::all() ต่อ request
 */
final class ModuleContextService
{
    /** cache current module ต่อ request — false = ยังไม่ได้ resolve */
    private string|null|false $moduleCache = false;

    /** themes ที่ ThemesManager::set() แล้ว */
    private array $activatedThemes = [];

    /** modules ที่ register view paths แล้ว */
    private array $processedModules = [];

    /** cache Module::all() ต่อ request */
    private ?array $allModules = null;

    // ─────────────────────────────────────────────────────────────────
    //  Module Detection
    // ─────────────────────────────────────────────────────────────────

    /**
     * ดึงชื่อ module ปัจจุบันจาก Route
     *
     * ลำดับการตรวจ: Controller namespace → Route name → URI segment
     * ผลลัพธ์ถูก cache ต่อ request เพื่อป้องกัน Module::find() ซ้ำ
     *
     * @return string|null ชื่อ module หรือ null ถ้าไม่พบ
     */
    public function getCurrentModule(): ?string
    {
        if ($this->moduleCache !== false) {
            return $this->moduleCache;
        }

        return $this->moduleCache = $this->resolveCurrentModule();
    }

    /**
     * ตั้งค่าชื่อ module ปัจจุบันด้วยตนเอง (Manual Override)
     */
    public function setCurrentModule(?string $moduleName): void
    {
        $this->moduleCache = $moduleName;
    }

    /**
     * ตรวจสอบว่า module ที่ระบุเป็น module ปัจจุบันหรือไม่
     *
     * @param  string  $moduleName  ชื่อ module ที่ต้องการเปรียบเทียบ
     */
    public function isCurrentModule(string $moduleName): bool
    {
        return $this->getCurrentModule() === $moduleName;
    }

    /**
     * ดึง metadata ของ module ปัจจุบัน
     *
     * @return array{name: string, path: string, enabled: bool}|null
     */
    public function getCurrentModuleInfo(): ?array
    {
        $moduleName = $this->getCurrentModule();

        if (! $moduleName) {
            return null;
        }

        $module = Module::find($moduleName);

        if (! $module) {
            return null;
        }

        return [
            'name' => $module->getName(),
            'path' => $module->getPath(),
            'enabled' => $module->isEnabled(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Theme Activation
    // ─────────────────────────────────────────────────────────────────

    /**
     * ตั้งค่า theme และ register view paths ของ module ปัจจุบัน
     *
     * Flow:
     *   1. Validate inputs
     *   2. ตรวจว่า theme folder มีอยู่จริง
     *   3. Activate theme (ถ้ายังไม่ได้ activate)
     *   4. Register module-specific view paths (ยกเว้น core)
     *
     * @param  string|null  $themeName  ชื่อ theme (เช่น 'system')
     * @param  string|null  $type  ประเภท theme (เช่น 'frontend', 'backend')
     * @return bool true ถ้า register view paths สำเร็จ
     */
    public function setThemes(?string $themeName, ?string $type = 'frontend'): bool
    {
        if (! $themeName || ! $type) {
            return false;
        }

        if (! istheme_paths($themeName, $type)) {
            return false;
        }

        $themePath = $type.'/'.$themeName;
        $moduleName = strtolower($this->getCurrentModule() ?? 'core');

        // Activate theme เพียงครั้งเดียว ต่อ theme path
        if (! in_array($themePath, $this->activatedThemes, true)) {
            $this->activateTheme($themePath);
            $this->activatedThemes[] = $themePath;
        }

        // core ไม่ต้อง register module-specific view paths
        if ($moduleName === 'core') {
            return false;
        }

        // ป้องกัน register ซ้ำสำหรับ module เดิม
        if (in_array($moduleName, $this->processedModules, true)) {
            return false;
        }

        $this->registerViewPaths($moduleName);
        $this->processedModules[] = $moduleName;

        return true;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private — Module Detection
    // ─────────────────────────────────────────────────────────────────

    /**
     * resolve module จาก Route ตามลำดับ priority
     */
    private function resolveCurrentModule(): ?string
    {
        $route = Route::current();

        if (! $route) {
            return null;
        }

        // 1. ตรวจจาก Controller namespace
        $action = $route->getAction('controller');
        if ($action) {
            $name = $this->detectFromController($action);
            if ($name && $this->isModuleEnabled($name)) {
                return $name;
            }
        }

        // 2. ตรวจจาก Route name
        $routeName = $route->getName();
        if ($routeName) {
            $name = $this->detectFromRouteName($routeName);
            if ($name && $this->isModuleEnabled($name)) {
                return $name;
            }
        }

        // 3. ตรวจจาก URI segment
        $name = $this->detectFromUri($route->uri());
        if ($name && $this->isModuleEnabled($name)) {
            return $name;
        }

        return null;
    }

    /**
     * แยกชื่อ module จาก Controller namespace
     * รองรับรูปแบบ nwidart: Modules\{Name}\...
     *
     * @param  string  $controller  เช่น Modules\Blog\Http\Controllers\PostController
     */
    private function detectFromController(string $controller): ?string
    {
        if (preg_match('/Modules\\\\([^\\\\]+)\\\\/', $controller, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * แยกชื่อ module จาก Route name
     * รองรับรูปแบบ: {module}.{action} เช่น blog.posts.index
     *
     * @param  string  $routeName  ชื่อ route
     */
    private function detectFromRouteName(string $routeName): ?string
    {
        foreach ($this->getModules() as $module) {
            $prefix = strtolower($module->getName()).'.';
            if (str_starts_with($routeName, $prefix)) {
                return $module->getName();
            }
        }

        return null;
    }

    /**
     * แยกชื่อ module จาก URI segment แรก
     * เช่น URI = 'blog/posts' → 'Blog'
     *
     * @param  string  $uri  route URI
     */
    private function detectFromUri(string $uri): ?string
    {
        $segments = explode('/', strtolower($uri));

        if (empty($segments)) {
            return null;
        }

        foreach ($this->getModules() as $module) {
            if (in_array(strtolower($module->getName()), $segments, true)) {
                return $module->getName();
            }
        }

        return null;
    }

    /**
     * ตรวจสอบว่า module มีอยู่และถูก enable แล้ว
     *
     * @param  string  $moduleName  ชื่อ module
     */
    private function isModuleEnabled(string $moduleName): bool
    {
        $module = Module::find($moduleName);

        return $module !== null && $module->isEnabled();
    }

    /**
     * ดึงรายการ modules ทั้งหมด (cache ต่อ request)
     *
     * @return array<\Nwidart\Modules\Laravel\Module>
     */
    private function getModules(): array
    {
        return $this->allModules ??= Module::all();
    }

    // ─────────────────────────────────────────────────────────────────
    //  Private — Theme Activation
    // ─────────────────────────────────────────────────────────────────

    /**
     * Activate theme: set ThemesManager, register pages location, load helpers
     *
     * @param  string  $themePath  เช่น 'frontend/system'
     */
    private function activateTheme(string $themePath): void
    {
        ThemesManager::set($themePath);

        $pagesPath = normalize_path(theme()->getPath().'resources/views/vendor/pages');
        View::prependLocation($pagesPath);

        $helpersFile = theme()->getPath().'functions/helpers.php';
        if (is_file($helpersFile)) {
            require_once normalize_path($helpersFile);
        }
    }

    /**
     * Register view paths ของ module ใน theme
     *
     * Namespaces ที่ register:
     *   {module}:: → resources/views/                        (global theme views)
     *   {module}:: → resources/views/vendor/modules/{module} (module-specific overrides)
     *
     * @param  string  $moduleName  ชื่อ module (lowercase)
     */
    private function registerViewPaths(string $moduleName): void
    {
        $basePath = theme()->getPath();
        $viewsPath = normalize_path($basePath.'resources/views');
        $modulePath = normalize_path($basePath.'resources/views/vendor/modules/'.$moduleName);

        View::prependNamespace($moduleName, $viewsPath);
        View::prependNamespace($moduleName, $modulePath);
    }
}
