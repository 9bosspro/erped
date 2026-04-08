<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\File;

use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Throwable;

/**
 * FileContentHandler — จัดการอ่าน/เขียนเนื้อหาไฟล์
 *
 * ความรับผิดชอบ:
 * - อ่านไฟล์และแปลงเป็น array (JSON decode)
 * - บันทึกข้อมูลลงไฟล์ (JSON encode)
 * - นำเข้า CSV เป็น array
 * - อ่าน/เขียน/ต่อท้ายไฟล์
 */
final class FileContentHandler
{
    /**
     * อ่านข้อมูลจากไฟล์ และแปลงเป็น array (JSON decode) ถ้าต้องการ
     *
     * @param  string  $file  path ไฟล์
     * @param  bool  $convertToArray  true = JSON decode เป็น array
     * @return mixed array หรือ string หรือ null
     */
    public function getFileData(string $file, bool $convertToArray = true): mixed
    {
        $content = File::get($file);

        if (empty($content)) {
            return $convertToArray ? [] : null;
        }

        return $convertToArray ? json_decode($content, true) : $content;
    }

    /**
     * บันทึกข้อมูลลงไฟล์
     *
     * @param  string  $path  path ไฟล์
     * @param  array|string|null  $data  ข้อมูลที่ต้องการบันทึก
     * @param  bool  $json  true = JSON encode ก่อนบันทึก
     */
    public function saveFileData(string $path, array|string|null $data, bool $json = true): bool
    {
        try {
            if ($json) {
                $data = $this->jsonEncodePrettify($data);
            }

            File::ensureDirectoryExists(File::dirname($path));
            File::put($path, $data);

            return true;
        } catch (Throwable $throwable) {
            $this->logError($throwable);

            return false;
        }
    }

    /**
     * นำเข้า CSV เป็น array (แถวแรกเป็น header)
     *
     * @param  string  $filename  path ไฟล์ CSV
     * @param  string  $delimiter  ตัวคั่น
     * @return array<int, array<string, mixed>> ข้อมูลที่แปลงแล้ว
     *
     * @throws InvalidArgumentException ถ้าไฟล์ไม่มีหรืออ่านไม่ได้
     */
    public function importCsv(string $filename, string $delimiter = ','): array
    {
        if (! file_exists($filename) || ! is_readable($filename)) {
            throw new InvalidArgumentException("CSV file does not exist or is not readable: {$filename}");
        }

        $data = [];
        $header = null;

        if (($handle = fopen($filename, 'r')) !== false) {
            try {
                while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
                    if ($row === null) {
                        continue;
                    }

                    if (! $header) {
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
        }

        return $data;
    }

    /**
     * JSON encode แบบ pretty print พร้อม Unicode support
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการ encode
     */
    public function jsonEncodePrettify(mixed $data): string
    {
        return (string) json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * อ่านเนื้อหาไฟล์เป็น string
     *
     * @param  string  $path  path ไฟล์
     * @return string|null เนื้อหาไฟล์ หรือ null ถ้าไม่พบ/อ่านไม่ได้
     */
    public function read(string $path): ?string
    {
        if (! file_exists($path) || ! is_readable($path)) {
            return null;
        }

        return file_get_contents($path) ?: null;
    }

    /**
     * เขียนเนื้อหาลงไฟล์ (สร้าง directory อัตโนมัติ)
     *
     * @param  string  $path  path ไฟล์
     * @param  string  $contents  เนื้อหาที่ต้องการเขียน
     */
    public function write(string $path, string $contents): bool
    {
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($path, $contents) !== false;
    }

    /**
     * ต่อท้ายเนื้อหาลงไฟล์
     *
     * @param  string  $path  path ไฟล์
     * @param  string  $contents  เนื้อหาที่ต้องการต่อท้าย
     */
    public function append(string $path, string $contents): bool
    {
        return file_put_contents($path, $contents, FILE_APPEND) !== false;
    }

    /**
     * บันทึก error ลง Laravel log
     */
    protected function logError(Throwable $throwable): void
    {
        if (function_exists('logError')) {
            logError($throwable);
        } elseif (function_exists('logger')) {
            logger()->error($throwable->getMessage(), [
                'exception' => $throwable,
            ]);
        }
    }
}
