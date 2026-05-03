<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Storage;

use DirectoryIterator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

/**
 * FileManager — จัดการไฟล์และไดเรกทอรี
 *
 * แก้ไข bugs จาก MyFiles เดิม:
 * - deleteDirectory() เรียก global function แทน $this-> (fixed)
 * - beliefmedia_recurse_copy() เรียก global function (removed, replaced by copyDirectory)
 * - updateDotEnv() ไม่ escape regex (fixed with preg_quote)
 * - formatBytes() ซ้ำกับ humanFilesize() (merged)
 */
final class FileManager
{
    /**
     * แปลง human-readable size string เป็น bytes
     * เช่น "256M" → 268435456, "2G" → 2147483648
     */
    public static function hrToBytes(string|int $value): int
    {
        $value = trim((string) $value);
        $bytes = (int) $value;
        $unit = strtolower(substr($value, -1));

        return match ($unit) {
            'g' => $bytes * 1024 * 1024 * 1024,
            'm' => $bytes * 1024 * 1024,
            'k' => $bytes * 1024,
            default => $bytes,
        };
    }

    /**
     * ลบไดเรกทอรีและทุกอย่างข้างใน (recursive)
     * คืนค่า true หาก directory ไม่มีอยู่แล้ว (ถือว่าสำเร็จ)
     */
    public function deleteDirectory(string $dir): bool
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir)) {
            return unlink($dir);
        }

        $items = scandir($dir);
        if (! is_array($items)) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // แก้ bug: ใช้ $this-> ไม่ใช่ global function call
            if (! $this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * คัดลอกไดเรกทอรีแบบ recursive ใช้ RecursiveIteratorIterator
     * (แทน beliefmedia_recurse_copy ที่มี bugs และใช้ @ suppression)
     *
     * @throws InvalidArgumentException หาก source ไม่ใช่ directory
     * @throws RuntimeException หากสร้าง destination ไม่ได้
     */
    public function copyDirectory(string $src, string $dst): bool
    {
        if (! is_dir($src)) {
            throw new InvalidArgumentException("Source directory does not exist: {$src}");
        }

        if (! is_dir($dst) && ! mkdir($dst, 0755, true) && ! is_dir($dst)) {
            throw new RuntimeException("Cannot create destination directory: {$dst}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $target = $dst.DIRECTORY_SEPARATOR.$iterator->getSubPathname();

            if ($item->isDir()) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
            } else {
                if (! copy($item->getPathname(), $target)) {
                    throw new RuntimeException("Failed to copy: {$item->getPathname()} → {$target}");
                }
            }
        }

        return true;
    }

    /**
     * ดึง list ไฟล์ทั้งหมดใน directory (recursive)
     *
     * @return string[] absolute paths ของทุกไฟล์
     */
    public function listFilesRecursive(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            /** @var SplFileInfo $fileInfo */
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    /**
     * ดึง list ไฟล์ใน directory (ไม่ recursive)
     *
     * @param  string[]  $ignoreFiles  ชื่อไฟล์/โฟลเดอร์ที่ต้องการข้าม
     * @return string[] ชื่อไฟล์ (ไม่ใช่ full path)
     */
    public function listFiles(string $dir, array $ignoreFiles = []): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $ignored = array_merge(['.', '..', '.DS_Store', 'Thumbs.db'], $ignoreFiles);
        $files = [];

        foreach (new DirectoryIterator($dir) as $file) {
            if ($file->isDot() || in_array($file->getFilename(), $ignored, true)) {
                continue;
            }

            if ($file->isFile()) {
                $files[] = $file->getFilename();
            }
        }

        natsort($files);

        return array_values($files);
    }

    /**
     * อัปเดตหรือเพิ่มค่าใน .env file
     *
     * @param  string  $key  Environment key (A-Z, 0-9, _ เท่านั้น)
     * @param  mixed  $newValue  ค่าใหม่
     * @param  string  $delim  delimiter ล้อมค่า เช่น '"'
     *
     * @throws InvalidArgumentException หาก key ไม่ตรงรูปแบบ
     */
    public function updateDotEnv(string $key, mixed $newValue, string $delim = ''): void
    {
        // Validate key — ป้องกัน regex injection
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
            throw new InvalidArgumentException(
                "Invalid .env key format: [{$key}]. Only alphanumeric and underscore allowed.",
            );
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            return;
        }

        $handle = fopen($path, 'c+');
        if ($handle === false) {
            throw new RuntimeException("Cannot open .env file: {$path}");
        }

        try {
            flock($handle, LOCK_EX);

            $contents = stream_get_contents($handle);
            $escapedKey = preg_quote($key, '/');  // ป้องกัน regex injection
            $pattern = "/^{$escapedKey}=.*$/m";
            $replacementValue = is_scalar($newValue) ? (string) $newValue : '';
            $replacement = "{$key}={$delim}{$replacementValue}{$delim}";

            if (preg_match($pattern, $contents)) {
                $newContents = preg_replace($pattern, $replacement, $contents);
            } else {
                $newContents = rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, $newContents);
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * อ่านค่า key จาก .env file โดยตรง (bypass config cache)
     */
    public function getDotEnv(string $key, mixed $default = null): ?string
    {
        $defaultStr = is_string($default) ? $default : null;
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/i', $key)) {
            return $defaultStr;
        }

        $path = base_path('.env');

        if (! File::exists($path)) {
            return $defaultStr;
        }

        $contents = File::get($path);
        $escapedKey = preg_quote($key, '/');

        if (preg_match("/^{$escapedKey}=(.*)$/m", $contents, $matches)) {
            return Str::of(trim($matches[1]))->trim('"\'')->toString();
        }

        return $defaultStr;
    }

    /**
     * แปลงขนาดไฟล์ (bytes) เป็น human-readable
     * เช่น 1536 → "1.50 KB"
     *
     * @param  int  $bytes  ขนาดเป็น bytes
     * @param  int  $precision  จำนวนทศนิยม
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $pow = $bytes > 0 ? (int) floor(log($bytes) / log(1024)) : 0;
        $pow = min($pow, count($units) - 1);
        $value = $bytes / (1024 ** $pow);

        return number_format($value, $precision).' '.$units[$pow];
    }

    /**
     * นำเข้าไฟล์ CSV → array of associative arrays
     *
     * @param  string  $path  absolute path ของไฟล์ CSV
     * @param  string  $delimiter  delimiter (default: ',')
     * @return array<int, array<string, string>>
     *
     * @throws InvalidArgumentException หากอ่านไฟล์ไม่ได้
     */
    public function importCsv(string $path, string $delimiter = ','): array
    {
        if (! is_readable($path)) {
            throw new InvalidArgumentException("CSV file is not readable: {$path}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: {$path}");
        }

        $data = [];
        $header = null;

        try {
            // Strip UTF-8 BOM ถ้ามี (พบบ่อยในไฟล์ CSV จาก Excel)
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                // แปลง encoding จาก Windows-1252 / TIS-620 → UTF-8 ถ้าจำเป็น
                $row = array_map(static function (string $cell): string {
                    return mb_detect_encoding($cell, ['UTF-8', 'TIS-620', 'Windows-1252'], true) !== 'UTF-8'
                        ? mb_convert_encoding($cell, 'UTF-8')
                        : $cell;
                }, $row);

                if ($header === null) {
                    $header = $row;

                    continue;
                }

                if (count($header) === count($row)) {
                    $data[] = array_combine($header, $row);
                }
            }
        } finally {
            fclose($handle);
        }

        return $data;
    }

    /**
     * อ่าน JSON file และแปลงเป็น array หรือ string
     *
     * @param  string  $path  absolute path ของไฟล์
     * @param  bool  $convertToArray  true = คืนเป็น array, false = คืนเป็น string
     *
     * @throws RuntimeException หาก JSON เสียหาย
     */
    public function readJsonFile(string $path, bool $convertToArray = true): mixed
    {
        if (! File::exists($path)) {
            return $convertToArray ? [] : null;
        }

        $content = File::get($path);

        if (empty($content)) {
            return $convertToArray ? [] : null;
        }

        if (! $convertToArray) {
            return $content;
        }

        $result = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Failed to parse JSON file [{$path}]: ".json_last_error_msg(),
            );
        }

        return $result;
    }

    /**
     * บันทึกข้อมูลเป็น JSON file
     *
     * @param  string  $path  absolute path ปลายทาง
     * @param  array|string|null  $data  ข้อมูลที่จะบันทึก
     * @param  bool  $json  true = encode เป็น JSON ก่อนบันทึก
     */
    public function saveJsonFile(string $path, array|string|null $data, bool $json = true): bool
    {
        try {
            File::ensureDirectoryExists(File::dirname($path));

            if ($json) {
                $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if ($encoded === false) {
                    throw new RuntimeException('json_encode failed: '.json_last_error_msg());
                }

                $content = $encoded.PHP_EOL;
            } else {
                $content = (string) $data;
            }

            File::put($path, $content);

            return true;
        } catch (Throwable $e) {
            \Illuminate\Support\Facades\Log::error('FileManager::saveJsonFile failed: '.$e->getMessage());

            return false;
        }
    }
}
