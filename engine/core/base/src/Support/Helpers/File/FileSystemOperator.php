<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\File;

use DirectoryIterator;
use Exception;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * FileSystemOperator — จัดการ file system (directory, copy, scan)
 *
 * ความรับผิดชอบ:
 * - ลบ directory แบบ recursive
 * - ดึง file paths จาก directory
 * - คัดลอก directory แบบ recursive
 * - คำนวณ relative path
 * - สแกน folder
 */
final class FileSystemOperator
{
    /**
     * ลบ directory และเนื้อหาทั้งหมดแบบ recursive
     *
     * @param  string  $dir  path directory ที่ต้องการลบ
     */
    public function deleteDirectory(string $dir): bool
    {
        if (! file_exists($dir)) {
            return true;
        }

        if (! is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (! $this->deleteDirectory($dir.DIRECTORY_SEPARATOR.$item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * ดึง file paths ทั้งหมดจาก directory แบบ recursive
     *
     * @param  string  $dir  path directory ที่ต้องการสแกน
     * @return array<string> รายการ full path ของไฟล์
     */
    public function getAllFilePaths(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        $files = [];
        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isFile()) {
                $files[] = $fileInfo->getPathname();
            }
        }

        return $files;
    }

    /**
     * ดึงรายการไฟล์ใน directory (ไม่ recursive)
     *
     * @param  string  $dir  path directory
     * @return array<string> รายการชื่อไฟล์
     */
    public function getFilesInDirectory(string $dir): array
    {
        if (! is_dir($dir)) {
            return [];
        }

        $items = scandir($dir);
        $files = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $dir.DIRECTORY_SEPARATOR.$item;

            if (is_file($fullPath)) {
                $files[] = $item;
            }
        }

        return $files;
    }

    /**
     * คัดลอก directory แบบ recursive
     *
     * @param  string  $src  path ต้นทาง
     * @param  string  $dst  path ปลายทาง
     *
     * @throws InvalidArgumentException ถ้าต้นทางไม่มีหรือไม่ใช่ directory
     * @throws Exception ถ้าสร้าง directory หรือคัดลอกไฟล์ไม่สำเร็จ
     */
    public function recursiveCopy(string $src, string $dst): bool
    {
        if (! file_exists($src)) {
            throw new InvalidArgumentException("Source directory does not exist: {$src}");
        }

        if (! is_dir($src)) {
            throw new InvalidArgumentException("Source is not a directory: {$src}");
        }

        if (! file_exists($dst)) {
            if (! mkdir($dst, 0755, true) && ! is_dir($dst)) {
                throw new Exception("Failed to create destination directory: {$dst}");
            }
        }

        $dir = opendir($src);
        if (! $dir) {
            throw new Exception("Cannot open source directory: {$src}");
        }

        try {
            while (($file = readdir($dir)) !== false) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $srcPath = $src.DIRECTORY_SEPARATOR.$file;
                $dstPath = $dst.DIRECTORY_SEPARATOR.$file;

                if (is_dir($srcPath)) {
                    $this->recursiveCopy($srcPath, $dstPath);
                } else {
                    if (! copy($srcPath, $dstPath)) {
                        throw new Exception("Failed to copy file: {$srcPath} to {$dstPath}");
                    }
                }
            }

            return true;
        } finally {
            closedir($dir);
        }
    }

    /**
     * คำนวณ relative path ระหว่างสอง path
     *
     * @param  string  $from  path ต้นทาง
     * @param  string  $to  path ปลายทาง
     */
    public function getRelativePath(string $from, string $to): string
    {
        $from = is_dir($from) ? rtrim($from, '\/').'/' : $from;
        $to = is_dir($to) ? rtrim($to, '\/').'/' : $to;
        $from = str_replace('\\', '/', $from);
        $to = str_replace('\\', '/', $to);

        $from = explode('/', $from);
        $to = explode('/', $to);
        $relPath = $to;

        foreach ($from as $depth => $dir) {
            if ($dir === $to[$depth]) {
                array_shift($relPath);
            } else {
                $remaining = count($from) - $depth;
                if ($remaining > 1) {
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    break;
                } else {
                    $relPath[0] = './'.$relPath[0];
                }
            }
        }

        return implode('/', $relPath);
    }

    /**
     * สแกน folder และคืนรายการชื่อไฟล์/directory
     *
     * @param  string  $path  path directory
     * @param  array<string>  $ignoreFiles  ไฟล์ที่ต้องการข้าม
     * @return array<string> รายการชื่อไฟล์ (เรียงตาม natural sort)
     */
    public function scanFolder(string $path, array $ignoreFiles = []): array
    {
        if (empty($path) || ! File::isDirectory($path)) {
            return [];
        }

        $ignoreFiles = array_merge(['.', '..', '.DS_Store'], $ignoreFiles);
        $files = [];

        foreach (new DirectoryIterator($path) as $file) {
            if (! $file->isDot() && ! in_array($file->getFilename(), $ignoreFiles)) {
                $files[] = $file->getFilename();
            }
        }

        natsort($files);

        return $files;
    }

    /**
     * ตรวจสอบว่า path เป็น directory หรือไม่
     */
    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * ตรวจสอบว่า path มีอยู่หรือไม่
     */
    public function exists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * สร้าง directory ถ้ายังไม่มี
     *
     * @param  string  $path  path directory
     * @param  int  $mode  permission mode
     */
    public function ensureDirectoryExists(string $path, int $mode = 0755): bool
    {
        if (is_dir($path)) {
            return true;
        }

        return mkdir($path, $mode, true);
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /**
     * @deprecated ใช้ getAllFilePaths() แทน
     */
    public function getallfiletPath(string $dir): array
    {
        return $this->getAllFilePaths($dir);
    }
}
