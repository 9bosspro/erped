<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\File;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * PhpConfigManager — จัดการ PHP INI config และ .env file
 *
 * ความรับผิดชอบ:
 * - อ่าน/เขียนค่าใน .env file
 * - แปลง human-readable size เป็น bytes และกลับกัน
 * - จัดการ PHP INI settings
 * - ดึง memory limit และ max upload size
 */
final class PhpConfigManager
{
    /**
     * แปลง human-readable size string เป็น bytes
     * เช่น '256M' → 268435456, '2G' → 2147483648
     *
     * @param  string|float|int|null  $value  ค่าที่ต้องการแปลง
     * @return int จำนวน bytes
     */
    public static function convertHrToBytes(string|float|int|null $value): int
    {
        $value = strtolower(trim((string) $value));
        $bytes = (int) $value;

        $unit = substr($value, -1);

        $bytes *= match ($unit) {
            'y' => 1024 ** 8,
            'z' => 1024 ** 7,
            'e' => 1024 ** 6,
            'p' => 1024 ** 5,
            't' => 1024 ** 4,
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return min($bytes, PHP_INT_MAX);
    }

    /**
     * อัปเดตหรือเพิ่มค่าใน .env file
     *
     * @param  string  $key  ชื่อ environment variable (A-Z, 0-9, _ เท่านั้น)
     * @param  mixed  $newValue  ค่าใหม่
     * @param  string  $delim  delimiter ล้อมค่า เช่น '"'
     *
     * @throws InvalidArgumentException ถ้า key ไม่ตรงรูปแบบ
     */
    public function updateDotEnv(string $key, mixed $newValue, string $delim = ''): void
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
            throw new InvalidArgumentException(
                "Invalid .env key format: [{$key}]. Only alphanumeric and underscore allowed.",
            );
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            return;
        }

        $contents = File::get($path);
        $escapedKey = preg_quote($key, '/');
        $pattern = "/^{$escapedKey}=.*$/m";
        $replacement = "{$key}={$delim}{$newValue}{$delim}";

        if (preg_match($pattern, $contents)) {
            $newContents = preg_replace($pattern, $replacement, $contents);
        } else {
            $newContents = rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
        }

        File::put($path, $newContents);
    }

    /**
     * อ่านค่า key จาก .env file โดยตรง (bypass config cache)
     *
     * @param  string  $key  ชื่อ environment variable
     * @param  mixed  $default  ค่า default ถ้าไม่พบ
     * @return string|null ค่าที่อ่านได้
     */
    public function getDotEnv(string $key, mixed $default = null): ?string
    {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
            return $default;
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            return $default;
        }

        $contents = File::get($path);
        $escapedKey = preg_quote($key, '/');

        if (preg_match("/^{$escapedKey}=(.*)$/m", $contents, $matches)) {
            return Str::of(trim($matches[1]))->trim('"\'')->toString();
        }

        return $default;
    }

    /**
     * ตรวจสอบว่า PHP INI setting สามารถเปลี่ยนค่าได้ที่ runtime
     *
     * @param  string  $setting  ชื่อ INI setting
     */
    public function isIniValueChangeable(string $setting): bool
    {
        static $iniAll = null;

        if ($iniAll === null) {
            $iniAll = function_exists('ini_get_all') ? ini_get_all() : false;
        }

        if (isset($iniAll[$setting]['access']) &&
            (INI_ALL === ($iniAll[$setting]['access'] & 7) ||
             INI_USER === ($iniAll[$setting]['access'] & 7))
        ) {
            return true;
        }

        return ! is_array($iniAll);
    }

    /**
     * ตั้งค่า PHP INI ที่ runtime (ถ้าเปลี่ยนได้)
     */
    public function iniSet(string $setting, mixed $value): void
    {
        if ($this->isIniValueChangeable($setting)) {
            ini_set($setting, (string) $value);
        }
    }

    /**
     * ดึงค่า PHP INI ปัจจุบัน
     */
    public function iniGet(string $setting): string|false
    {
        return ini_get($setting);
    }

    /**
     * แปลง bytes เป็น human-readable string
     * เช่น 268435456 → '256.00 MB'
     *
     * @param  int|float  $bytes  ขนาดเป็น bytes
     * @param  int  $precision  จำนวนทศนิยม
     * @return string human-readable size
     */
    public function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? (int) floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);

        $value = $bytes / (1024 ** $pow);

        return number_format($value, $precision).' '.$units[$pow];
    }

    /**
     * ดึง PHP memory limit เป็น bytes
     *
     * @return int จำนวน bytes (PHP_INT_MAX ถ้า unlimited)
     */
    public function getMemoryLimit(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1') {
            return PHP_INT_MAX;
        }

        return self::convertHrToBytes($limit);
    }

    /**
     * ดึง max upload size เป็น bytes (min ของ upload_max_filesize และ post_max_size)
     *
     * @return int จำนวน bytes
     */
    public function getMaxUploadSize(): int
    {
        $uploadMax = self::convertHrToBytes(ini_get('upload_max_filesize'));
        $postMax = self::convertHrToBytes(ini_get('post_max_size'));

        return min($uploadMax, $postMax);
    }
}
