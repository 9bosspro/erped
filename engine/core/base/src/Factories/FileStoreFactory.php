<?php

namespace Core\Base\Factories;

use Core\Base\Contracts\FileStorage\StorageDriverInterface;
use Engine\Modules\Files\Services\Drivers\GoogleDriveAdapter;
use Engine\Modules\Files\Services\Drivers\LocalStorageAdapter;
use Engine\Modules\Files\Services\Drivers\MinioAdapter;
// use Exception;
use InvalidArgumentException;

class FileStoreFactory
{
    /** @var array<string, class-string> */
    protected static array $driverMap = [
        's3' => MinioAdapter::class,
        'local' => LocalStorageAdapter::class,
        'google' => GoogleDriveAdapter::class,
    ];

    /**
     * สร้าง adapter จาก disk name
     *
     * @throws InvalidArgumentException
     *
     * @example
     *   FileStoreFactory::make('minio')      // driver=s3 → MinioAdapter('minio')
     *   FileStoreFactory::make('s3-backup')  // driver=s3 → MinioAdapter('s3-backup')
     *   FileStoreFactory::make('local')      // driver=local → LocalStorageAdapter('local')
     */
    public static function make(string $disk): StorageDriverInterface
    {
        $driverValue = config("filesystems.disks.{$disk}.driver");

        if ($driverValue === null) {
            throw new InvalidArgumentException("ไม่พบ disk '{$disk}' ใน config/filesystems.php");
        }

        $driver = is_string($driverValue) ? $driverValue : (is_scalar($driverValue) ? (string) $driverValue : '');

        $adapterClass = static::$driverMap[$driver]
            ?? throw new InvalidArgumentException("ไม่รองรับ driver '{$driver}' (disk: {$disk})");

        return new $adapterClass($disk); // สมมติทุก adapter รับ $disk เป็น constructor argument
    }

    /**
     * ลงทะเบียน custom driver เพิ่มเติม
     *
     * @param  class-string<StorageDriverInterface>  $adapterClass
     */
    public static function extend(string $driver, string $adapterClass): void
    {
        static::$driverMap[$driver] = $adapterClass;
    }

    /*  public static function makeold(string $disk): StorageDriverInterface
     {
         // อ่าน driver type จาก config
         $driver = config("filesystems.disks.{$disk}.driver", 'xxxxx');

         return match ($driver) {
             'local' => new LocalStorageAdapter,
             'google' => new GoogleDriveAdapter,
             's3' => new MinioAdapter($disk),
             default => throw new InvalidArgumentException("ไม่รองรับไดรเวอร์: {$driver} สำหรับดิสก์: {$disk}"),
         };
     } */
}
