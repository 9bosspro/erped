<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ReflectionClass;

/**
 * PathResolverTrait — จัดการ path resolution สำหรับ module/package
 *
 * แปลง namespace → filesystem path
 * ใช้ in-memory cache (scoped per class) เพื่อลด I/O
 *
 * เหตุผลที่แยกออกมาจาก LoadAndPublishDataTrait:
 *  - Path resolution เป็น concern แยกจาก publishing logic
 *  - Reuse ได้อิสระใน class ที่ต้องการ path เฉยๆ ไม่ต้อง publish
 *  - Test ง่ายกว่า เพราะมีหน้าที่เดียว (SRP)
 */
trait PathResolverTrait
{
    /**
     * In-memory path cache — scoped per class, ไม่ใช้ Redis/file cache
     * Path ไม่เปลี่ยนระหว่าง request จึง cache ใน static memory ได้ปลอดภัย
     *
     * @var array<string, string>
     */
    private static array $pathCache = [];

    /** @var string|null Namespace ของ module เช่น "Core\Base" */
    protected ?string $namespace = null;

    // ─────────────────────────────────────────────────────────────────
    //  Namespace
    // ─────────────────────────────────────────────────────────────────

    /**
     * กำหนด namespace ของ module
     * ตัด leading/trailing slash ออกเสมอ เพื่อ normalize
     */
    protected function setNamespace(string $namespace): static
    {
        $this->namespace = trim($namespace, '/\\');

        return $this;
    }

    /**
     * คืนค่า namespace ปัจจุบัน
     */
    protected function getNamespace(): string
    {
        return $this->namespace ?? '';
    }

    /**
     * namespace → kebab-case สำหรับ view/publish tags
     * "Core\Base" → "core-base"
     */
    protected function getDashedNamespace(): string
    {
        return str_replace(['/', '\\'], '-', strtolower($this->namespace ?? ''));
    }

    /**
     * namespace → dot-notation สำหรับ config keys
     * "Core\Base" → "core.base"
     */
    protected function getDottedNamespace(): string
    {
        return str_replace(['/', '\\'], '.', strtolower($this->namespace ?? ''));
    }

    // ─────────────────────────────────────────────────────────────────
    //  Path Resolution
    // ─────────────────────────────────────────────────────────────────

    /**
     * คืนค่า base path ของ module
     *
     * Reflect ครั้งแรก แล้ว cache ใน memory ตลอด request
     * dirname(..., 3): class อยู่ที่ src/Traits/Xxx.php → ขึ้น 3 ระดับถึง module root
     */
    protected function getBasePath(): string
    {
        $key = 'base:'.static::class;

        return self::$pathCache[$key]
            ??= dirname((new ReflectionClass(static::class))->getFileName(), 3)
            .DIRECTORY_SEPARATOR;
    }

    /**
     * คืนค่า path ของ subfolder ภายใน module root
     *
     * @param  string  $subfolder  เช่น 'config', 'lang', 'resources/views'
     *                             ใช้ forward slash ได้ทั้ง Windows และ Linux
     */
    protected function getPath(string $subfolder = ''): string
    {
        $key = 'path:'.static::class.':'.$subfolder;

        if (isset(self::$pathCache[$key])) {
            return self::$pathCache[$key];
        }

        $base = $this->getBasePath();

        if ($subfolder === '') {
            return self::$pathCache[$key] = $base;
        }

        $normalized = rtrim(
            $base.trim(
                str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $subfolder),
                DIRECTORY_SEPARATOR,
            ),
            DIRECTORY_SEPARATOR,
        ).DIRECTORY_SEPARATOR;

        return self::$pathCache[$key] = $normalized;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Shortcut Paths — คืน path แต่ละ resource folder
    // ─────────────────────────────────────────────────────────────────

    protected function getConfigPath(): string
    {
        return $this->getPath('config');
    }

    protected function getConstantsPath(): string
    {
        return $this->getPath('constants');
    }

    protected function getTranslationsPath(): string
    {
        return $this->getPath('lang');
    }

    protected function getHelpersPath(): string
    {
        return $this->getPath('helpers');
    }

    protected function getViewsPath(): string
    {
        return $this->getPath('resources/views');
    }

    protected function getMigrationsPath(): string
    {
        return $this->getPath('database/migrations');
    }

    protected function getFactoriesPath(): string
    {
        return $this->getPath('database/factories');
    }

    protected function getSeedersPath(): string
    {
        return $this->getPath('database/seeders');
    }

    protected function getAssetsPath(): string
    {
        return $this->getPath('storage/public');
    }

    protected function getCommandsPath(): string
    {
        return $this->getPath('src/Console/Commands');
    }

    /**
     * คืนค่า full path ของ route file
     */
    protected function getRouteFilePath(string $file): string
    {
        return $this->getPath('routes').Str::finish($file, '.php');
    }

    /**
     * คืนค่า full path ของ config file หรือ null ถ้าไม่พบ
     */
    protected function getConfigFilePath(string $file): ?string
    {
        if (trim($file) === '') {
            return null;
        }

        $path = $this->getConfigPath().Str::finish($file, '.php');

        return File::exists($path) ? $path : null;
    }

    /**
     * คืนค่า full path ของ constants file หรือ null ถ้าไม่พบ
     */
    protected function getConstantsFilePath(string $file): ?string
    {
        if (trim($file) === '') {
            return null;
        }

        $path = $this->getConstantsPath().Str::finish($file, '.php');

        return File::exists($path) ? $path : null;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Utilities
    // ─────────────────────────────────────────────────────────────────

    /**
     * คืนค่า relative path จาก module subfolder root
     *
     * ใช้สำหรับสร้าง config key จาก full path
     * Fallback: คืน basename ถ้า path ไม่อยู่ใน module
     *
     * @throws InvalidArgumentException ถ้า fullPath ว่าง
     */
    protected function getRelativePathFromModule(string $fullPath, string $subfolder = ''): string
    {
        if (trim($fullPath) === '') {
            throw new InvalidArgumentException('Full path must not be empty.');
        }

        $normalizedFull = $this->normalizePath($fullPath);
        $basePath = $this->normalizePath($this->getPath($subfolder));

        // Windows เปรียบเทียบ case-insensitive
        $startsWith = DIRECTORY_SEPARATOR === '\\'
            ? str_starts_with(strtolower($normalizedFull), strtolower($basePath))
            : str_starts_with($normalizedFull, $basePath);

        if ($startsWith) {
            return trim(substr($normalizedFull, strlen($basePath)), DIRECTORY_SEPARATOR);
        }

        return basename($normalizedFull);
    }

    /**
     * Normalize path separators รองรับทั้ง Windows (\) และ Linux (/)
     */
    protected function normalizePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $path = (string) preg_replace(
            '#'.preg_quote(DIRECTORY_SEPARATOR, '#').'{2,}#',
            DIRECTORY_SEPARATOR,
            $path,
        );

        return rtrim($path, DIRECTORY_SEPARATOR) ?: $path;
    }
}
