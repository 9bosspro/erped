<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\File;

use Core\Base\Enums\FileType;

/**
 * FileTypeChecker — ตรวจสอบประเภทไฟล์จากนามสกุล
 *
 * ความรับผิดชอบ:
 * - ดึง FileType enum จากนามสกุลไฟล์
 * - ตรวจสอบประเภทไฟล์ (Document, Image, Video, Sound)
 * - ดึง Model class ที่ตรงกับประเภทไฟล์
 * - ดึงรายการนามสกุลไฟล์ของแต่ละประเภท
 */
final class FileTypeChecker
{
    /**
     * ตาราง mapping ประเภทไฟล์ → นามสกุลไฟล์
     *
     * @var array<string, array<string>>
     */
    protected static array $extensions = [

        FileType::Document->value => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx',
            'ppt', 'pptx', 'txt', 'csv', 'odt',
            'ods', 'odp', 'rtf', 'xml', 'json',
            'zip', 'rar', '7z', 'tar', 'gz',
        ],

        FileType::Image->value => [
            'jpg', 'jpeg', 'png', 'gif', 'bmp',
            'webp', 'svg', 'ico', 'tiff', 'tif',
            'heic', 'heif', 'avif', 'raw',
        ],

        FileType::Video->value => [
            'mp4', 'avi', 'mov', 'wmv', 'flv',
            'mkv', 'webm', 'm4v', '3gp', 'mpeg',
            'mpg', 'ogv', 'ts', 'vob',
        ],

        FileType::Sound->value => [
            'mp3', 'wav', 'aac', 'flac', 'ogg',
            'wma', 'm4a', 'opus', 'aiff', 'mid',
            'midi', 'amr', 'ape', 'alac',
        ],
    ];

    /**
     * ดึง FileType จากนามสกุลไฟล์
     *
     * @param  string  $filename  ชื่อไฟล์หรือ path
     */
    public static function getType(string $filename): FileType
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        foreach (self::$extensions as $type => $extensions) {
            if (in_array($ext, $extensions)) {
                return FileType::from($type);
            }
        }

        return FileType::Unknown;
    }

    /**
     * ตรวจสอบว่าเป็น Document หรือไม่
     */
    public static function isDocument(string $filename): bool
    {
        return self::getType($filename) === FileType::Document;
    }

    /**
     * ตรวจสอบว่าเป็น Image หรือไม่
     */
    public static function isImage(string $filename): bool
    {
        return self::getType($filename) === FileType::Image;
    }

    /**
     * ตรวจสอบว่าเป็น Video หรือไม่
     */
    public static function isVideo(string $filename): bool
    {
        return self::getType($filename) === FileType::Video;
    }

    /**
     * ตรวจสอบว่าเป็น Sound หรือไม่
     */
    public static function isSound(string $filename): bool
    {
        return self::getType($filename) === FileType::Sound;
    }

    /**
     * ดึง Model class ที่ตรงกับประเภทไฟล์
     *
     * @param  string  $filename  ชื่อไฟล์หรือ path
     * @return string fully qualified class name
     */
    public static function getModelClass(string $filename): string
    {
        return match (self::getType($filename)) {
            FileType::Document => \App\Models\Document::class,
            FileType::Image => \App\Models\Image::class,
            FileType::Video => \App\Models\Video::class,
            FileType::Sound => \App\Models\Sounds::class,
            default => \App\Models\Document::class,
        };
    }

    /**
     * สร้าง Model instance ที่ตรงกับประเภทไฟล์
     *
     * @param  string  $filename  ชื่อไฟล์หรือ path
     */
    public static function getModelInstance(string $filename): object
    {
        $class = self::getModelClass($filename);

        return new $class;
    }

    /**
     * ดึงรายการนามสกุลไฟล์ของประเภทที่ระบุ
     *
     * @param  FileType  $type  ประเภทไฟล์
     * @return array<string> รายการนามสกุลไฟล์
     */
    public static function getExtensions(FileType $type): array
    {
        return self::$extensions[$type->value] ?? [];
    }

    /**
     * ดึงนามสกุลไฟล์จาก filename
     *
     * @param  string  $filename  ชื่อไฟล์หรือ path
     * @return string นามสกุลไฟล์ (lowercase)
     */
    public static function getExtension(string $filename): string
    {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
}
