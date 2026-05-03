<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Module;

use Core\Base\Support\Helpers\Module\Contracts\ModuleHelperInterface;
use Illuminate\Support\Facades\Route;
use InvalidArgumentException;
use LogicException;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as ModuleInstance;
use Throwable;

/**
 * ModuleHelper — Nwidart\Modules Helper ที่ครบครัน ปลอดภัย และยืดหยุ่น
 *
 * ═══════════════════════════════════════════════════════════════
 *  Discovery (ค้นหา module)
 * ═══════════════════════════════════════════════════════════════
 *  find($name)           — หา module instance หรือ null
 *  findOrFail($name)     — หา module หรือ throw InvalidArgumentException
 *  all()                 — module ทั้งหมด (enabled + disabled)
 *  allEnabled()          — เฉพาะที่ enable อยู่
 *  allDisabled()         — เฉพาะที่ disable อยู่
 *  has($name)            — ตรวจว่ามี module นี้หรือไม่
 *  count()               — จำนวน module ทั้งหมด
 *  names()               — ชื่อ module ทั้งหมด ['Auth', 'Demo', ...]
 *  enabledNames()        — ชื่อเฉพาะที่ enabled
 *
 * ═══════════════════════════════════════════════════════════════
 *  Status (เปิด/ปิด module)
 * ═══════════════════════════════════════════════════════════════
 *  isEnabled($name)      — ตรวจว่า module ถูก enable หรือไม่
 *  isDisabled($name)     — ตรวจว่า module ถูก disable หรือไม่
 *  enable($name)         — เปิดใช้งาน module
 *  disable($name)        — ปิดใช้งาน module
 *
 * ═══════════════════════════════════════════════════════════════
 *  Path Utilities (path ภายใน module)
 * ═══════════════════════════════════════════════════════════════
 *  path($name, $sub)         — module root path (หรือ sub-path)
 *  appPath($name, $sub)      — module/app/...
 *  configPath($name, $sub)   — module/config/...
 *  viewsPath($name, $sub)    — module/resources/views/...
 *  langPath($name, $sub)     — module/lang/...
 *  routesPath($name, $sub)   — module/routes/...
 *  databasePath($name, $sub) — module/database/...
 *  assetPath($name, $sub)    — module/resources/assets/...
 *  testsPath($name, $sub)    — module/tests/...
 *
 * ═══════════════════════════════════════════════════════════════
 *  Context Detection (ตรวจหา module ปัจจุบัน)
 * ═══════════════════════════════════════════════════════════════
 *  current()             — ตรวจจาก route → backtrace namespace → file path
 *  currentName()         — ชื่อ module ปัจจุบัน หรือ null
 *  fromRoute()           — ตรวจจาก Route ปัจจุบัน (เร็วสุด, ไม่ใช้ backtrace)
 *  fromClass($class)     — ตรวจจาก FQCN (Engine\Modules\Demo\...)
 *  fromNamespace($ns)    — ตรวจจาก namespace string
 *  fromPath($filePath)   — ตรวจจาก file path
 *
 * ═══════════════════════════════════════════════════════════════
 *  Module Info (ข้อมูล metadata)
 * ═══════════════════════════════════════════════════════════════
 *  namespace($name)      — full namespace (Engine\Modules\Demo)
 *  version($name)        — version จาก module.json
 *  description($name)    — description จาก module.json
 *  composerAttr($name, $key) — attribute จาก module composer.json
 *
 * ═══════════════════════════════════════════════════════════════
 *  Collection Helpers
 * ═══════════════════════════════════════════════════════════════
 *  filter(callable)      — filter modules (คืน array ที่กรองแล้ว)
 *  map(callable)         — map ผ่าน modules ทั้งหมด
 *  each(callable)        — iterate modules
 *  only(string[])        — ดึงเฉพาะ modules ที่ระบุชื่อ
 *
 * ═══════════════════════════════════════════════════════════════
 *  Validation (ตรวจสอบก่อนใช้งาน)
 * ═══════════════════════════════════════════════════════════════
 *  assertExists($name)   — throw ถ้า module ไม่มีอยู่
 *  assertEnabled($name)  — throw ถ้า module ไม่ได้ enable
 *
 * ═══════════════════════════════════════════════════════════════
 *  Utility
 * ═══════════════════════════════════════════════════════════════
 *  flushCache()          — ล้าง resolution cache (ใช้ใน testing)
 *
 * ─── Context Detection ลำดับความสำคัญ ───────────────────────
 *  current() ตรวจตามลำดับ:
 *   1. fromRoute()      — controller namespace → route name → URI (ไม่ใช้ backtrace)
 *   2. backtrace class  — ค้นหา Engine\Modules\{Name} ใน call stack
 *   3. backtrace file   — เทียบ file path กับ module paths
 *
 * ─── Namespace Configuration ─────────────────────────────────
 *  อ่าน namespace จาก config('modules.namespace') อัตโนมัติ
 *  (default: 'Modules', project นี้ใช้ 'Engine\Modules')
 */
final class ModuleHelper implements ModuleHelperInterface
{
    /**
     * @var array<string, ModuleInstance|null>
     *                                         per-instance resolution cache — ป้องกัน resolve ซ้ำใน request เดียวกัน
     */
    private array $resolveCache = [];

    // ═══════════════════════════════════════════════════════════
    //  Discovery
    // ═══════════════════════════════════════════════════════════

    /**
     * หา module instance ตามชื่อ
     *
     * @param  string  $name  ชื่อ module (case-sensitive, เช่น 'Demo', 'Auth')
     * @return ModuleInstance|null module instance หรือ null ถ้าไม่พบ
     */
    public function find(string $name): ?ModuleInstance
    {
        return Module::find($name);
    }

    /**
     * หา module instance หรือ throw exception ถ้าไม่พบ
     *
     * @param  string  $name  ชื่อ module
     * @return ModuleInstance module instance
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function findOrFail(string $name): ModuleInstance
    {
        $module = $this->find($name);

        if ($module === null) {
            throw new InvalidArgumentException("Module [{$name}] ไม่พบในระบบ");
        }

        return $module;
    }

    /**
     * คืน modules ทั้งหมด (enabled และ disabled)
     *
     * @return array<string, ModuleInstance> keyed by module name
     */
    public function all(): array
    {
        return Module::all();
    }

    /**
     * คืนเฉพาะ modules ที่ enable อยู่
     *
     * @return array<string, ModuleInstance>
     */
    public function allEnabled(): array
    {
        return Module::allEnabled();
    }

    /**
     * คืนเฉพาะ modules ที่ disable อยู่
     *
     * @return array<string, ModuleInstance>
     */
    public function allDisabled(): array
    {
        return Module::allDisabled();
    }

    /**
     * ตรวจว่า module ชื่อนี้มีอยู่ในระบบหรือไม่
     *
     * @param  string  $name  ชื่อ module
     */
    public function has(string $name): bool
    {
        return Module::has($name);
    }

    /**
     * จำนวน module ทั้งหมดในระบบ
     */
    public function count(): int
    {
        return Module::count();
    }

    /**
     * คืนชื่อ module ทั้งหมดเป็น array
     *
     * @return string[] เช่น ['Auth', 'Demo', 'User']
     */
    public function names(): array
    {
        return array_keys($this->all());
    }

    /**
     * คืนชื่อเฉพาะ modules ที่ enabled
     *
     * @return string[]
     */
    public function enabledNames(): array
    {
        return array_keys($this->allEnabled());
    }

    // ═══════════════════════════════════════════════════════════
    //  Status
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจว่า module นี้ถูก enable หรือไม่
     *
     * คืน false ถ้า module ไม่มีอยู่
     *
     * @param  string  $name  ชื่อ module
     */
    public function isEnabled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null && $module->isEnabled();
    }

    /**
     * ตรวจว่า module นี้ถูก disable หรือไม่
     *
     * คืน false ถ้า module ไม่มีอยู่
     *
     * @param  string  $name  ชื่อ module
     */
    public function isDisabled(string $name): bool
    {
        $module = $this->find($name);

        return $module !== null && $module->isDisabled();
    }

    /**
     * เปิดใช้งาน module
     *
     * @param  string  $name  ชื่อ module
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function enable(string $name): void
    {
        $this->findOrFail($name)->enable();
    }

    /**
     * ปิดใช้งาน module
     *
     * @param  string  $name  ชื่อ module
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function disable(string $name): void
    {
        $this->findOrFail($name)->disable();
    }

    // ═══════════════════════════════════════════════════════════
    //  Path Utilities
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน module root path หรือ sub-path ภายใน module
     *
     * ตัวอย่าง:
     * ```php
     * $helper->path('Demo')                        // .../engine/modules/Demo
     * $helper->path('Demo', 'app/Http/Controllers')// .../Demo/app/Http/Controllers
     * ```
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน module (optional)
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function path(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, $subPath);
    }

    /**
     * คืน path ของ app/ directory ภายใน module
     *
     * ตัวอย่าง: $helper->appPath('Demo', 'Http/Controllers')
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน app/
     */
    public function appPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'app', $subPath);
    }

    /**
     * คืน path ของ config/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน config/
     */
    public function configPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'config', $subPath);
    }

    /**
     * คืน path ของ resources/views/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน resources/views/
     */
    public function viewsPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'resources/views', $subPath);
    }

    /**
     * คืน path ของ lang/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน lang/
     */
    public function langPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'lang', $subPath);
    }

    /**
     * คืน path ของ routes/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน routes/
     */
    public function routesPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'routes', $subPath);
    }

    /**
     * คืน path ของ database/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน database/ (เช่น 'migrations', 'seeders')
     */
    public function databasePath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'database', $subPath);
    }

    /**
     * คืน path ของ resources/assets/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน resources/assets/
     */
    public function assetPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'resources/assets', $subPath);
    }

    /**
     * คืน path ของ tests/ directory ภายใน module
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $subPath  sub-path ภายใน tests/
     */
    public function testsPath(string $name, string $subPath = ''): string
    {
        return $this->buildPath($name, 'tests', $subPath);
    }

    // ═══════════════════════════════════════════════════════════
    //  Context Detection
    // ═══════════════════════════════════════════════════════════

    /**
     * ตรวจหา module ปัจจุบันจาก context
     *
     * ลำดับการตรวจ (เร็วสุด → ช้าสุด):
     *  1. fromRoute()     — controller namespace, route name, URI (ไม่ใช้ backtrace)
     *  2. backtrace class — ค้นหา Engine\Modules\{Name} ใน call stack (สูงสุด 20 frames)
     *  3. backtrace file  — เทียบ file path กับ module paths
     *
     * ⚠️ backtrace มี overhead — ใน HTTP context ควรใช้ fromRoute() โดยตรง
     *    backtrace เหมาะสำหรับ console, queue, หรือ non-HTTP context
     *
     * @return ModuleInstance|null module ปัจจุบัน หรือ null ถ้าตรวจไม่ได้
     */
    public function current(): ?ModuleInstance
    {
        // 1. Route detection (fastest — no backtrace)
        $fromRoute = $this->fromRoute();
        if ($fromRoute !== null) {
            return $fromRoute;
        }

        // 2. Backtrace — ค้นหา module namespace ใน call stack
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        foreach ($frames as $frame) {
            $class = $frame['class'] ?? null;
            if ($class !== null) {
                $module = $this->fromClass($class);
                if ($module !== null) {
                    return $module;
                }
            }
        }

        // 3. Backtrace — เทียบ file path
        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            if ($file !== null) {
                $module = $this->fromPath($file);
                if ($module !== null) {
                    return $module;
                }
            }
        }

        return null;
    }

    /**
     * คืนชื่อ module ปัจจุบัน หรือ null ถ้าตรวจไม่ได้
     *
     * @return string|null เช่น 'Demo', 'Auth', null
     */
    public function currentName(): ?string
    {
        return $this->current()?->getName();
    }

    /**
     * ตรวจหา module จาก Route ปัจจุบัน (ไม่ใช้ backtrace)
     *
     * ลำดับการตรวจ:
     *  1. Controller class namespace → Engine\Modules\{Name}\...
     *  2. Route name prefix → demo.index → 'demo'
     *  3. URI first segment → /demo/users → 'demo'
     *
     * Cache: ผล cached ตลอด request (route ไม่เปลี่ยนใน request เดียวกัน)
     */
    public function fromRoute(): ?ModuleInstance
    {
        $cacheKey = '__route__';

        if (array_key_exists($cacheKey, $this->resolveCache)) {
            return $this->resolveCache[$cacheKey];
        }

        try {
            $route = Route::current();
        } catch (Throwable) {
            return $this->resolveCache[$cacheKey] = null;
        }

        if ($route === null) {
            return $this->resolveCache[$cacheKey] = null;
        }

        // 1. ตรวจจาก controller namespace
        /** @var array<string, mixed> $action */
        $action = $route->getAction();
        $controller = $action['controller'] ?? $action['uses'] ?? null;

        if (is_string($controller)) {
            // Controller อาจมี @method ต่อท้าย
            $controllerClass = str_contains($controller, '@')
                ? explode('@', $controller)[0]
                : $controller;

            $module = $this->fromClass($controllerClass);
            if ($module !== null) {
                return $this->resolveCache[$cacheKey] = $module;
            }
        }

        // 2. ตรวจจาก route name (demo.index → 'Demo')
        $routeName = $route->getName();
        if ($routeName !== null && $routeName !== '') {
            $prefix = explode('.', $routeName)[0];
            $module = $this->findCaseInsensitive($prefix);
            if ($module !== null) {
                return $this->resolveCache[$cacheKey] = $module;
            }
        }

        // 3. ตรวจจาก URI first segment (/demo/users → 'demo')
        $uri = $route->uri();
        $firstSegment = explode('/', ltrim($uri, '/'))[0] ?? '';

        if ($firstSegment !== '') {
            foreach ($this->all() as $module) {
                if (strtolower($firstSegment) === $module->getLowerName()) {
                    return $this->resolveCache[$cacheKey] = $module;
                }
            }
        }

        return $this->resolveCache[$cacheKey] = null;
    }

    /**
     * ตรวจหา module จาก FQCN (Fully Qualified Class Name)
     *
     * ตัวอย่าง:
     *  fromClass('Engine\Modules\Demo\Http\Controllers\DemoController') → Demo module
     *  fromClass('App\Services\SomeService')                           → null
     *
     * @param  string  $className  FQCN ของ class
     */
    public function fromClass(string $className): ?ModuleInstance
    {
        $cacheKey = 'class:'.$className;

        if (array_key_exists($cacheKey, $this->resolveCache)) {
            return $this->resolveCache[$cacheKey];
        }

        return $this->resolveCache[$cacheKey] = $this->fromNamespace($className);
    }

    /**
     * ตรวจหา module จาก namespace string
     *
     * รองรับทั้ง namespace และ FQCN:
     *  fromNamespace('Engine\Modules\Demo')                           → Demo module
     *  fromNamespace('Engine\Modules\Demo\Http\Controllers\Ctrl')    → Demo module
     *
     * @param  string  $namespace  namespace หรือ FQCN
     */
    public function fromNamespace(string $namespace): ?ModuleInstance
    {
        $modulesNs = $this->getModulesNamespace();
        $prefix = $modulesNs.'\\';

        if (! str_starts_with($namespace, $prefix)) {
            return null;
        }

        // ดึงชื่อ module segment ถัดจาก prefix
        $remainder = substr($namespace, strlen($prefix));
        $moduleName = explode('\\', $remainder)[0] ?? null;

        if ($moduleName === null || $moduleName === '') {
            return null;
        }

        return $this->find($moduleName);
    }

    /**
     * ตรวจหา module จาก file path
     *
     * ตัวอย่าง:
     *  fromPath('/app/engine/modules/Demo/app/Http/Controllers/DemoController.php')
     *  → Demo module
     *
     * @param  string  $filePath  absolute หรือ relative file path
     */
    public function fromPath(string $filePath): ?ModuleInstance
    {
        $cacheKey = 'path:'.$filePath;

        if (array_key_exists($cacheKey, $this->resolveCache)) {
            return $this->resolveCache[$cacheKey];
        }

        $realPath = realpath($filePath) ?: $filePath;
        $ds = DIRECTORY_SEPARATOR;

        foreach ($this->all() as $module) {
            $modulePath = realpath($module->getPath()) ?: $module->getPath();
            // ต้องขึ้นต้นด้วย module path + directory separator
            if (str_starts_with($realPath, $modulePath.$ds)) {
                return $this->resolveCache[$cacheKey] = $module;
            }
        }

        return $this->resolveCache[$cacheKey] = null;
    }

    // ═══════════════════════════════════════════════════════════
    //  Module Info
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน full namespace ของ module
     *
     * ตัวอย่าง: namespace('Demo') → 'Engine\Modules\Demo'
     *
     * @param  string  $name  ชื่อ module
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function namespace(string $name): string
    {
        $module = $this->findOrFail($name);

        return $this->getModulesNamespace().'\\'.$module->getStudlyName();
    }

    /**
     * คืน version ของ module จาก module.json
     *
     * @param  string  $name  ชื่อ module
     * @return string|null version string หรือ null ถ้าไม่ได้กำหนด
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function version(string $name): ?string
    {
        return $this->findOrFail($name)->get('version') ?: null;
    }

    /**
     * คืน description ของ module จาก module.json
     *
     * @param  string  $name  ชื่อ module
     * @return string|null description หรือ null ถ้าไม่ได้กำหนด
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function description(string $name): ?string
    {
        return $this->findOrFail($name)->get('description') ?: null;
    }

    /**
     * คืน attribute จาก composer.json ของ module
     *
     * ตัวอย่าง: composerAttr('Demo', 'require') → ['php' => '^8.1', ...]
     *
     * @param  string  $name  ชื่อ module
     * @param  string  $key  attribute key ใน composer.json
     * @param  mixed  $default  ค่า default ถ้าไม่พบ
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     */
    public function composerAttr(string $name, string $key, mixed $default = null): mixed
    {
        return $this->findOrFail($name)->getComposerAttr($key, $default);
    }

    // ═══════════════════════════════════════════════════════════
    //  Collection Helpers
    // ═══════════════════════════════════════════════════════════

    /**
     * Filter modules ตาม callback
     *
     * ตัวอย่าง:
     * ```php
     * // เฉพาะ module ที่มี version
     * $helper->filter(fn($m) => $m->get('version') !== null);
     * ```
     *
     * @param  callable(ModuleInstance): bool  $callback
     * @return array<string, ModuleInstance>
     */
    public function filter(callable $callback): array
    {
        return array_filter($this->all(), $callback);
    }

    /**
     * Map ผ่าน modules ทั้งหมด
     *
     * ตัวอย่าง:
     * ```php
     * $names = $helper->map(fn($m) => $m->getName());
     * ```
     *
     * @param  callable(ModuleInstance, string): mixed  $callback
     */
    public function map(callable $callback): array
    {
        return array_map($callback, $this->all());
    }

    /**
     * Iterate modules ทั้งหมด
     *
     * ตัวอย่าง:
     * ```php
     * $helper->each(fn($module, $name) => logger()->info($name));
     * ```
     *
     * @param  callable(ModuleInstance, string): void  $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->all() as $name => $module) {
            $callback($module, $name);
        }
    }

    /**
     * คืนเฉพาะ modules ที่ระบุชื่อ
     *
     * ตัวอย่าง: $helper->only(['Auth', 'Demo'])
     *
     * @param  string[]  $names  ชื่อ modules ที่ต้องการ
     * @return array<string, ModuleInstance>
     */
    public function only(array $names): array
    {
        return array_filter(
            $this->all(),
            fn (ModuleInstance $module): bool => in_array($module->getName(), $names, true),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Validation
    // ═══════════════════════════════════════════════════════════

    /**
     * Throw exception ถ้า module ไม่มีอยู่ในระบบ
     *
     * @param  string  $name  ชื่อ module
     *
     * @throws InvalidArgumentException
     */
    public function assertExists(string $name): void
    {
        if (! $this->has($name)) {
            throw new InvalidArgumentException(
                "Module [{$name}] ไม่มีอยู่ในระบบ — ตรวจสอบ modules_statuses.json และ engine/modules/",
            );
        }
    }

    /**
     * Throw exception ถ้า module ไม่ได้ enable
     *
     * @param  string  $name  ชื่อ module
     *
     * @throws InvalidArgumentException ถ้า module ไม่มีอยู่
     * @throws LogicException ถ้า module มีแต่ถูก disable
     */
    public function assertEnabled(string $name): void
    {
        $this->assertExists($name);

        if ($this->isDisabled($name)) {
            throw new LogicException(
                "Module [{$name}] ถูก disable อยู่ — เปิดใช้งานด้วย php artisan module:enable {$name}",
            );
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Utility
    // ═══════════════════════════════════════════════════════════

    /**
     * ล้าง resolution cache ทั้งหมด
     *
     * ใช้ใน testing เพื่อ reset state ระหว่าง test cases
     */
    public function flushCache(): void
    {
        $this->resolveCache = [];
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * อ่าน modules namespace จาก config
     *
     * project นี้ใช้ 'Engine\Modules' — อ่านจาก config('modules.namespace')
     */
    private function getModulesNamespace(): string
    {
        $ns = config('modules.namespace', 'Modules');

        return rtrim(is_string($ns) ? $ns : 'Modules', '\\');
    }

    /**
     * สร้าง path ภายใน module โดย join segments
     *
     * @param  string  $name  ชื่อ module
     * @param  string  ...$parts  path segments (กรอง empty string ออก)
     */
    private function buildPath(string $name, string ...$parts): string
    {
        $base = $this->findOrFail($name)->getPath();
        $segments = array_filter($parts, fn (string $p): bool => $p !== '');

        if ($segments === []) {
            return $base;
        }

        // Normalize separators และ trim slashes
        $normalized = array_map(
            fn (string $p): string => trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $p), DIRECTORY_SEPARATOR),
            $segments,
        );

        return $base.DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $normalized);
    }

    /**
     * ค้นหา module โดยชื่อแบบ case-insensitive
     *
     * ใช้สำหรับ route name prefix ที่อาจเป็น lowercase
     * ('demo.index' → ค้นหา 'Demo')
     */
    private function findCaseInsensitive(string $name): ?ModuleInstance
    {
        $nameLower = strtolower($name);

        foreach ($this->all() as $module) {
            if ($module->getLowerName() === $nameLower) {
                return $module;
            }
        }

        return null;
    }
}
