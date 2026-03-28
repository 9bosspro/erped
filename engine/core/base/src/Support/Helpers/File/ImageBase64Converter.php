<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\File;

use finfo;

/**
 * ImageBase64Converter — แปลงรูปภาพ ↔ Base64
 *
 * ความรับผิดชอบ:
 * - ดึง Base64 data จาก data URI
 * - สร้าง Base64 data URI จากไฟล์รูปภาพ
 * - แปลง Base64 กลับเป็นไฟล์รูปภาพ
 * - ตรวจสอบ validity ของ Base64 image
 * - ดึงขนาดรูปภาพจาก Base64
 */
final class ImageBase64Converter
{
    /**
     * ดึง Base64 data จาก data URI string
     *
     * @param  string  $imgsrc  data URI (เช่น "data:image/png;base64,...")
     * @return string|false Base64 string หรือ false ถ้า format ไม่ถูกต้อง
     */
    public function getBase64FromDataUri(string $imgsrc = ''): string|false
    {
        if (str_contains($imgsrc, ';base64,')) {
            $parts = explode(';base64,', $imgsrc, 2);

            return $parts[1] ?? false;
        }

        return false;
    }

    /**
     * สร้าง Base64 data URI จากไฟล์รูปภาพ
     *
     * @param  string  $image  path หรือ URL ของรูปภาพ
     * @param  bool  $base64Only  true = คืนเฉพาะ Base64 string (ไม่มี data URI prefix)
     * @return string data URI หรือ Base64 string
     */
    public function generateBase64(string $image = '', bool $base64Only = false): string
    {
        $imageData = base64_encode(file_get_contents($image));
        $mimeType = mime_content_type($image);

        if ($base64Only) {
            return $imageData;
        }

        return 'data:'.$mimeType.';base64,'.$imageData;
    }

    /**
     * แปลง Base64 string เป็นไฟล์รูปภาพ
     *
     * @param  string  $base64  Base64 encoded image data
     * @param  string  $outputPath  path ปลายทาง
     */
    public function base64ToImage(string $base64, string $outputPath): bool
    {
        if (str_contains($base64, ';base64,')) {
            $base64 = explode(';base64,', $base64, 2)[1];
        }

        $imageData = base64_decode($base64, true);

        if ($imageData === false) {
            return false;
        }

        $dir = dirname($outputPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents($outputPath, $imageData) !== false;
    }

    /**
     * ดึง MIME type จาก data URI
     *
     * @param  string  $dataUri  data URI string
     * @return string|null MIME type หรือ null ถ้าไม่พบ
     */
    public function getMimeTypeFromDataUri(string $dataUri): ?string
    {
        if (preg_match('/^data:([^;]+);base64,/', $dataUri, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * ตรวจสอบว่า string เป็น Base64 encoded image ที่ถูกต้อง
     *
     * @param  string  $data  ข้อมูลที่ต้องการตรวจสอบ
     */
    public function isValidBase64Image(string $data): bool
    {
        if (str_starts_with($data, 'data:image/')) {
            $base64 = $this->getBase64FromDataUri($data);
            if ($base64 === false) {
                return false;
            }
            $data = $base64;
        }

        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($decoded);

        return str_starts_with($mimeType, 'image/');
    }

    /**
     * ดึงขนาดรูปภาพจาก Base64 data
     *
     * @param  string  $base64  Base64 encoded image
     * @return array{0: int, 1: int}|null [width, height] หรือ null ถ้าไม่ valid
     */
    public function getImageDimensions(string $base64): ?array
    {
        if (str_contains($base64, ';base64,')) {
            $base64 = explode(';base64,', $base64, 2)[1];
        }

        $imageData = base64_decode($base64, true);
        if ($imageData === false) {
            return null;
        }

        $image = @imagecreatefromstring($imageData);
        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        imagedestroy($image);

        return [$width, $height];
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /** @deprecated ใช้ getBase64FromDataUri() แทน */
    public function getimgbase64imgsrc(string $imgsrc = ''): string|false
    {
        return $this->getBase64FromDataUri($imgsrc);
    }

    /** @deprecated ใช้ generateBase64() แทน */
    public function genimgbase64(string $image = '', bool $base64 = false): string
    {
        return $this->generateBase64($image, $base64);
    }
}
