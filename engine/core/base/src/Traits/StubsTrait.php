<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Core\Base\Support\Helpers\Stub\StubResult;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Trait StubsTrait
 *
 * จัดการการสร้างไฟล์ต้นแบบ (Stubs) สำหรับ Artisan Commands และ Module Generator
 *
 * ═══════════════════════════════════════════════════════════════════════
 *  คุณสมบัติหลัก:
 * ═══════════════════════════════════════════════════════════════════════
 *  [Stub Resolution]
 *  - Multi-level fallback: Module stubs → App stubs → Package stubs
 *  - In-memory caching ป้องกันการอ่านไฟล์ซ้ำ (ลด I/O)
 *  - Extensible: Override getModuleStubsPath() เพื่อกำหนด Path ใน Module
 *
 *  [ความปลอดภัย]
 *  - Path Traversal Protection: บล็อก ../ และ absolute path injection
 *  - ชื่อไฟล์ตรวจสอบก่อนเขียนทุกครั้ง (whitelist pattern)
 *  - File permission ที่ปลอดภัย (0755 / 0644)
 *
 *  [ความยืดหยุ่น]
 *  - Overwrite Policy: ควบคุมว่าจะ overwrite ไฟล์ที่มีอยู่แล้วหรือไม่
 *  - Dry-run mode: ทดสอบโดยไม่เขียนไฟล์จริง
 *  - Namespace injection: แทนที่ DeclaredNamespace อัตโนมัติจาก Module
 *  - Extra placeholders: ส่ง replacements เพิ่มเติมได้ทุก method
 *
 *  [ประเภท Stub ที่รองรับ]
 *  Model, Controller (Resource/API/Invokable), Request, Resource,
 *  Policy, Migration, Seeder, Factory, Observer, Event, Listener,
 *  Job, DTO, Interface, ServiceProvider, Command, Middleware
 */
trait StubsTrait
{
    use PathResolverTrait;

    /** @var array<string, string>  In-memory cache สำหรับ stub content */
    private static array $stubCache = [];
    // ─────────────────────────────────────────────────────────────────
    //  State (ตัวแปรควบคุมพฤติกรรม)
    // ─────────────────────────────────────────────────────────────────

    /** @var bool true = เขียนทับไฟล์ที่มีอยู่, false = ข้ามไป (default) */
    protected bool $stubOverwrite = false;

    /** @var bool true = ไม่เขียนไฟล์จริง แค่คืน content กลับมา */
    protected bool $stubDryRun = false;

    // ─────────────────────────────────────────────────────────────────
    //  Fluent Configuration (ตั้งค่าก่อน generate)
    // ─────────────────────────────────────────────────────────────────

    /**
     * เปิด/ปิดโหมดเขียนทับไฟล์ที่มีอยู่แล้ว
     */
    public function stubOverwrite(bool $overwrite = true): static
    {
        $this->stubOverwrite = $overwrite;

        return $this;
    }

    /**
     * เปิด/ปิดโหมด Dry-run (ทดสอบโดยไม่เขียนไฟล์จริง)
     */
    public function stubDryRun(bool $dryRun = true): static
    {
        $this->stubDryRun = $dryRun;

        return $this;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Stub Path Resolution (ค้นหา Stub ต้นแบบ)
    // ─────────────────────────────────────────────────────────────────

    /**
     * คืนค่า path โฟลเดอร์ stubs ของ Module ปัจจุบัน
     *
     * Override method นี้ใน class ที่ใช้ Trait เพื่อกำหนด Module stub path
     * ตัวอย่าง: return dirname(__DIR__, 2) . '/stubs';
     */
    protected function getModuleStubsPath(): ?string
    {
        return null;
    }

    /**
     * คืนค่า base path ของ Package stubs (stubs ระดับ vendor)
     *
     * Override เพื่อชี้ไปยัง stubs folder ของ package
     */
    protected function getPackageStubsPath(): ?string
    {
        return null;
    }

    /**
     * อ่านเนื้อหาของ Stub ตามประเภท พร้อม Multi-level fallback + Caching
     *
     * ลำดับการค้นหา:
     *  1. Module stubs/   → getModuleStubsPath()
     *  2. App stubs/      → resource_path('stubs')
     *  3. Package stubs/  → getPackageStubsPath()
     *
     * @param  string  $type  ชื่อ stub ไม่ต้องมี .stub เช่น 'Model', 'controller.api'
     *
     * @throws RuntimeException ถ้าไม่พบ stub จากทุก path
     */
    protected function getStub(string $type): string
    {
        $safeType = $this->sanitizeStubType($type);
        $filename = $safeType.'.stub';
        $cacheKey = static::class.':'.$filename;

        // ── In-memory cache ──────────────────────────────────────────
        if (isset(self::$stubCache[$cacheKey])) {
            return self::$stubCache[$cacheKey];
        }

        // ── Candidate paths (กรอง null ออก) ─────────────────────────
        $candidates = array_values(array_filter([
            $this->getModuleStubsPath() ? rtrim($this->getModuleStubsPath(), '/\\').DIRECTORY_SEPARATOR.$filename : null,
            resource_path('stubs'.DIRECTORY_SEPARATOR.$filename),
            $this->getPackageStubsPath() ? rtrim($this->getPackageStubsPath(), '/\\').DIRECTORY_SEPARATOR.$filename : null,
        ]));

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $content = file_get_contents($path);

                if ($content === false) {
                    throw new RuntimeException("Failed to read stub file: [{$path}]");
                }

                return self::$stubCache[$cacheKey] = $content;
            }
        }

        throw new RuntimeException(
            "Stub [{$type}] not found. Searched:\n - ".implode("\n - ", $candidates),
        );
    }

    /**
     * ล้าง Stub cache (เพื่อใช้ใน test หรือ hot-reload)
     */
    protected function clearStubCache(): void
    {
        self::$stubCache = [];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Placeholder System (แทนค่าใน Template)
    // ─────────────────────────────────────────────────────────────────

    /**
     * แทนที่ Placeholders ใน Stub content ทีเดียวหลายตัว (Batch Replace)
     *
     * @param  string  $stubContent  เนื้อหา stub
     * @param  array<string,string>  $replacements  key = placeholder token, value = ค่าที่แทน
     */
    protected function replaceStubPlaceholders(string $stubContent, array $replacements): string
    {
        if (empty($replacements)) {
            return $stubContent;
        }

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stubContent,
        );
    }

    /**
     * สร้าง Placeholder map มาตรฐานจากชื่อ Class/Model
     *
     * Token ที่ได้:
     *  {{ class }}              → UserProfile
     *  {{ classPlural }}        → UserProfiles
     *  {{ classPluralLower }}   → userprofiles
     *  {{ classSingularLower }} → userprofile
     *  {{ classSnake }}         → user_profile
     *  {{ classKebab }}         → user-profile
     *  {{ classCamel }}         → userProfile
     *  {{ classTitle }}         → User Profile
     *  {{ table }}              → user_profiles
     *  {{ namespace }}          → (ค่าว่างถ้าไม่ได้ inject)
     *
     * @param  string  $name  ชื่อ Class ใน PascalCase
     * @param  string  $namespace  PHP namespace ปลายทาง (optional)
     * @return array<string,string>
     */
    protected function buildStandardPlaceholders(string $name, string $namespace = ''): array
    {
        $plural = Str::plural($name);
        $snake = Str::snake($name);

        return [
            // ── Class name variants ───────────────────────────
            '{{ class }}' => $name,
            '{{class}}' => $name,
            '{{ classPlural }}' => $plural,
            '{{classPlural}}' => $plural,
            '{{ classPluralLower }}' => strtolower($plural),
            '{{classPluralLower}}' => strtolower($plural),
            '{{ classSingularLower }}' => strtolower($name),
            '{{classSingularLower}}' => strtolower($name),
            '{{ classSnake }}' => $snake,
            '{{classSnake}}' => $snake,
            '{{ classKebab }}' => Str::kebab($name),
            '{{classKebab}}' => Str::kebab($name),
            '{{ classCamel }}' => Str::camel($name),
            '{{classCamel}}' => Str::camel($name),
            '{{ classTitle }}' => Str::headline($name),
            '{{classTitle}}' => Str::headline($name),
            // ── Database ──────────────────────────────────────
            '{{ table }}' => Str::snake($plural),
            '{{table}}' => Str::snake($plural),
            // ── Namespace ─────────────────────────────────────
            '{{ namespace }}' => $namespace,
            '{{namespace}}' => $namespace,
            // ── Legacy tokens (backward compat) ──────────────
            '{{modelName}}' => $name,
            '{{modelNamePlural}}' => $plural,
            '{{modelNamePluralLowerCase}}' => strtolower($plural),
            '{{modelNameSingularLowerCase}}' => strtolower($name),
            '{{modelNameSnakeCase}}' => $snake,
            '{{modelNameKebabCase}}' => Str::kebab($name),
            '{{modelNameCamelCase}}' => Str::camel($name),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  File Generators — Model, Controller, Request…
    // ─────────────────────────────────────────────────────────────────

    /**
     * สร้างไฟล์ Eloquent Model
     *
     * @param  string  $name  ชื่อ Model ใน PascalCase เช่น 'UserProfile'
     * @param  string  $namespace  PHP namespace ของไฟล์ที่สร้าง
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app_path('Models'))
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeModel(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Models')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('Model', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Controller (Resource CRUD)
     *
     * @param  string  $name  ชื่อ Model ที่ Controller ควบคุม
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Http/Controllers)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeController(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Controllers')).DIRECTORY_SEPARATOR."{$name}Controller.php";
        $content = $this->resolveStubContent('Controller', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ API Controller (ไม่มี HTML views)
     *
     * @param  string  $name  ชื่อ Model
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeApiController(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Controllers/Api')).DIRECTORY_SEPARATOR."{$name}Controller.php";
        $content = $this->resolveStubContent('controller.api', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Invokable Controller (Single Action)
     *
     * @param  string  $name  ชื่อ Controller (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeInvokableController(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Controllers')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('controller.invokable', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Form Request
     *
     * @param  string  $name  ชื่อ Model หรือ Request name
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Http/Requests)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeRequest(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Requests')).DIRECTORY_SEPARATOR."{$name}Request.php";
        $content = $this->resolveStubContent('Request', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ API Resource (Transformer)
     *
     * @param  string  $name  ชื่อ Model
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Http/Resources)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeResource(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Resources')).DIRECTORY_SEPARATOR."{$name}Resource.php";
        $content = $this->resolveStubContent('Resource', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Policy
     *
     * @param  string  $name  ชื่อ Model
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Policies)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makePolicy(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Policies')).DIRECTORY_SEPARATOR."{$name}Policy.php";
        $content = $this->resolveStubContent('Policy', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Database Migration
     *
     * @param  string  $tableName  ชื่อตาราง (snake_case) เช่น 'user_profiles'
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: database/migrations)
     * @param  string  $timestamp  timestamp prefix เช่น '2026_04_02_000000' (auto-gen ถ้าว่าง)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeMigration(
        string $tableName,
        string $outputPath = '',
        string $timestamp = '',
        array $extra = [],
    ): StubResult {
        $this->validateSnakeName($tableName);

        $ts = $timestamp ?: now()->format('Y_m_d_His');
        $className = 'Create'.Str::studly($tableName).'Table';
        $fileName = "{$ts}_create_{$tableName}_table.php";
        $dest = ($outputPath ?: database_path('migrations')).DIRECTORY_SEPARATOR.$fileName;

        $content = $this->resolveStubContent(
            'Migration',
            $tableName,
            '',
            array_merge([
                '{{ className }}' => $className,
                '{{className}}' => $className,
                '{{ table }}' => $tableName,
                '{{table}}' => $tableName,
            ], $extra),
        );

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Database Seeder
     *
     * @param  string  $name  ชื่อ Model (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: database/seeders)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeSeeder(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: database_path('seeders')).DIRECTORY_SEPARATOR."{$name}Seeder.php";
        $content = $this->resolveStubContent('Seeder', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Model Factory
     *
     * @param  string  $name  ชื่อ Model (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: database/factories)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeFactory(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: database_path('factories')).DIRECTORY_SEPARATOR."{$name}Factory.php";
        $content = $this->resolveStubContent('Factory', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Eloquent Observer
     *
     * @param  string  $name  ชื่อ Model (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Observers)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeObserver(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Observers')).DIRECTORY_SEPARATOR."{$name}Observer.php";
        $content = $this->resolveStubContent('Observer', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Event
     *
     * @param  string  $name  ชื่อ Event (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Events)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeEvent(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Events')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('Event', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Event Listener
     *
     * @param  string  $name  ชื่อ Listener (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Listeners)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeListener(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Listeners')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('Listener', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Queued Job
     *
     * @param  string  $name  ชื่อ Job (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Jobs)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeJob(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Jobs')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('Job', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Middleware
     *
     * @param  string  $name  ชื่อ Middleware (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Http/Middleware)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeMiddleware(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Http/Middleware')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent('Middleware', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Artisan Console Command
     *
     * @param  string  $name  ชื่อ Command class (PascalCase)
     * @param  string  $signature  artisan signature เช่น 'module:sync'
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Console/Commands)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeCommand(
        string $name,
        string $signature = '',
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Console/Commands')).DIRECTORY_SEPARATOR."{$name}.php";
        $content = $this->resolveStubContent(
            'Command',
            $name,
            $namespace,
            array_merge([
                '{{ signature }}' => $signature ?: Str::kebab($name),
                '{{signature}}' => $signature ?: Str::kebab($name),
            ], $extra),
        );

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Data Transfer Object (DTO)
     *
     * @param  string  $name  ชื่อ DTO (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/DTOs)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeDto(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('DTOs')).DIRECTORY_SEPARATOR."{$name}DTO.php";
        $content = $this->resolveStubContent('DTO', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Contract / Interface
     *
     * @param  string  $name  ชื่อ Interface (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Contracts)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeInterface(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Contracts')).DIRECTORY_SEPARATOR."{$name}Interface.php";
        $content = $this->resolveStubContent('Interface', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    /**
     * สร้างไฟล์ Service Provider
     *
     * @param  string  $name  ชื่อ Provider (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  string  $outputPath  โฟลเดอร์ปลายทาง (default: app/Providers)
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม
     */
    protected function makeServiceProvider(
        string $name,
        string $namespace = '',
        string $outputPath = '',
        array $extra = [],
    ): StubResult {
        $this->validateClassName($name);

        $dest = ($outputPath ?: app_path('Providers')).DIRECTORY_SEPARATOR."{$name}ServiceProvider.php";
        $content = $this->resolveStubContent('ServiceProvider', $name, $namespace, $extra);

        return $this->writeStubFile($dest, $content);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Scaffold (สร้างหลายไฟล์พร้อมกัน)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Full Scaffold: Model + Controller + Request + Resource พร้อมกัน
     *
     * @param  string  $name  ชื่อ Model ใน PascalCase
     * @param  string  $namespace  PHP namespace ร่วม
     * @return array<string, StubResult> key = ประเภทไฟล์, value = ผลการ generate
     */
    protected function makeScaffold(string $name, string $namespace = ''): array
    {
        $this->validateClassName($name);

        return [
            'model' => $this->makeModel($name, $namespace),
            'controller' => $this->makeController($name, $namespace),
            'request' => $this->makeRequest($name, $namespace),
            'resource' => $this->makeResource($name, $namespace),
        ];
    }

    /**
     * Full API Scaffold: Model + API Controller + Request + Resource + Policy
     *
     * @param  string  $name  ชื่อ Model ใน PascalCase
     * @param  string  $namespace  PHP namespace ร่วม
     * @return array<string, StubResult>
     */
    protected function makeApiScaffold(string $name, string $namespace = ''): array
    {
        $this->validateClassName($name);

        return [
            'model' => $this->makeModel($name, $namespace),
            'controller' => $this->makeApiController($name, $namespace),
            'request' => $this->makeRequest($name, $namespace),
            'resource' => $this->makeResource($name, $namespace),
            'policy' => $this->makePolicy($name, $namespace),
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    //  Config File Writer (เขียน PHP Config จาก Runtime Config)
    // ─────────────────────────────────────────────────────────────────

    /**
     * เขียนค่า Configuration array ลงไฟล์ PHP แบบ Readable
     *
     * ใช้สำหรับ publish config ออกจาก Module ไปยัง config/ directory
     *
     * @param  string  $configKey  config() key เช่น 'core.base::myapp'
     * @param  string  $outputPath  absolute path ไฟล์ปลายทาง เช่น config_path('myapp.php')
     *
     * @throws InvalidArgumentException ถ้า outputPath ไม่ปลอดภัย
     * @throws RuntimeException ถ้าเขียนไฟล์ไม่สำเร็จ
     */
    protected function writeConfigFile(string $configKey, string $outputPath): StubResult
    {
        if (trim($configKey) === '') {
            throw new InvalidArgumentException('Config key must not be empty.');
        }

        $data = config($configKey, []);
        $content = '<?php'.PHP_EOL.PHP_EOL
            .'// Auto-generated by StubsTrait — DO NOT edit manually.'.PHP_EOL
            .'return '.var_export($data, true).';'.PHP_EOL;

        return $this->writeStubFile($outputPath, $content);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Internal Mechanics (กลไกภายใน)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Resolve stub content: โหลด stub แล้วแทนที่ placeholder ทั้งหมด
     *
     * @param  string  $stubType  ชื่อ stub เช่น 'Model', 'controller.api'
     * @param  string  $name  ชื่อ class (PascalCase)
     * @param  string  $namespace  PHP namespace
     * @param  array<string,string>  $extra  placeholder เพิ่มเติม (override ได้)
     * @return string content ที่ผ่านการ replace แล้ว
     */
    private function resolveStubContent(
        string $stubType,
        string $name,
        string $namespace,
        array $extra = [],
    ): string {
        $placeholders = array_merge(
            $this->buildStandardPlaceholders($name, $namespace),
            $extra,
        );

        return $this->replaceStubPlaceholders(
            $this->getStub($stubType),
            $placeholders,
        );
    }

    /**
     * เขียน content ลงไฟล์ปลายทาง พร้อม:
     * - ตรวจสอบ Path Traversal
     * - สร้าง directory หากยังไม่มี
     * - ตรวจสอบ Overwrite policy
     * - Dry-run support
     *
     * @throws RuntimeException ถ้าสร้าง directory หรือเขียนไฟล์ไม่สำเร็จ
     * @throws InvalidArgumentException ถ้า path ไม่ปลอดภัย
     */
    private function writeStubFile(string $destination, string $content): StubResult
    {
        // ── Security: normalize & validate destination path ──────────
        $destination = $this->normalizePath($destination);
        $this->assertSafePath($destination);

        $directory = dirname($destination);

        // ── Overwrite check ──────────────────────────────────────────
        if (! $this->stubOverwrite && is_file($destination)) {
            return new StubResult(
                path: $destination,
                content: $content,
                written: false,
                skipped: true,
                dryRun: false,
            );
        }

        // ── Dry-run ──────────────────────────────────────────────────
        if ($this->stubDryRun) {
            return new StubResult(
                path: $destination,
                content: $content,
                written: false,
                skipped: false,
                dryRun: true,
            );
        }

        // ── Create directory ─────────────────────────────────────────
        if (! is_dir($directory)) {
            if (! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create directory: [{$directory}]");
            }
        }

        // ── Write file (0644 = owner rw, group/other r) ──────────────
        if (file_put_contents($destination, $content, LOCK_EX) === false) {
            throw new RuntimeException("Cannot write file: [{$destination}]");
        }

        return new StubResult(
            path: $destination,
            content: $content,
            written: true,
            skipped: false,
            dryRun: false,
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Security Validators
    // ─────────────────────────────────────────────────────────────────

    /**
     * ตรวจสอบว่าชื่อ Class เป็น PascalCase ที่ถูกต้อง
     *
     * @throws InvalidArgumentException
     */
    private function validateClassName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Class name must not be empty.');
        }

        // อนุญาต: PascalCase (A-Z นำหน้า ตามด้วย alphanumeric)
        if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid class name [{$name}]. Must be PascalCase (e.g. \"UserProfile\").",
            );
        }
    }

    /**
     * ตรวจสอบว่าชื่อตาราง / snake_case name ถูกต้อง
     *
     * @throws InvalidArgumentException
     */
    private function validateSnakeName(string $name): void
    {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Table name must not be empty.');
        }

        // อนุญาต: lowercase alphanumeric และ underscore เท่านั้น
        if (! preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid table/snake name [{$name}]. Must be snake_case (e.g. \"user_profiles\").",
            );
        }
    }

    /**
     * ป้องกัน Path Traversal Attack บน destination path
     *
     * ตรวจสอบ: ห้ามมี ../ หรือ ..\\ ใน path
     *
     * @throws InvalidArgumentException
     */
    private function assertSafePath(string $path): void
    {
        // ตรวจสอบ null bytes
        if (str_contains($path, "\0")) {
            throw new InvalidArgumentException('Null byte detected in destination path.');
        }

        // ตรวจสอบ directory traversal
        $normalized = str_replace('\\', '/', $path);
        if (preg_match('#(^|/)\.\.(/|$)#', $normalized)) {
            throw new InvalidArgumentException(
                "Path traversal detected in destination: [{$path}]",
            );
        }
    }

    /**
     * ป้องกัน Path Traversal ใน stub type name
     *
     * อนุญาต: alphanumeric, dash, dot เท่านั้น
     *
     * @throws InvalidArgumentException
     */
    private function sanitizeStubType(string $type): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9.\-]/', '', $type);

        if ($safe !== $type || trim($type) === '') {
            throw new InvalidArgumentException(
                "Invalid stub type [{$type}]. Only alphanumeric, dot, and dash allowed.",
            );
        }

        return $safe;
    }
}
