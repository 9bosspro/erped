<?php

declare(strict_types=1);

namespace Core\Base\Services\Storage;

use App\Models\StorageFiles;
use Core\Base\Contracts\FileStorage\StorageDriverInterface;
use Core\Base\Factories\FileStoreFactory;
use Core\Base\Services\Storage\Contracts\DriverResolverServiceInterface;

/**
 * DriverResolverService — Resolve และ cache storage driver instance
 *
 * รับผิดชอบการ resolve driver เพียงอย่างเดียว (Single Responsibility)
 * Actions ทุกตัว depend on class นี้แทน FileStorageService โดยตรง
 *
 * ใช้ in-memory cache เพื่อไม่สร้าง driver ซ้ำใน request เดียวกัน
 */
class DriverResolverService implements DriverResolverServiceInterface
{
    /** @var array<string, StorageDriverInterface> cache ของ driver ที่ resolve แล้ว */
    private array $cache = [];

    /**
     * Resolve driver จากชื่อ disk (เช่น 'minio', 'local', 's3')
     *
     * ถ้า resolve แล้วจะ cache ไว้ — เรียกซ้ำจะได้ instance เดิม
     *
     * @param  string  $disk  ชื่อ disk ตาม filesystems config
     * @return StorageDriverInterface driver instance ที่พร้อมใช้งาน
     */
    public function forDisk(string $disk): StorageDriverInterface
    {
        return $this->cache[$disk] ??= FileStoreFactory::make($disk);
    }

    /**
     * Resolve driver จาก StorageFiles model
     *
     * ดึง disk_name จาก relation storageDisk — fallback ไป default disk
     *
     * @param  StorageFiles  $file  model ของไฟล์ที่ต้องการ driver
     * @return StorageDriverInterface driver instance สำหรับไฟล์นี้
     */
    public function forFile(StorageFiles $file): StorageDriverInterface
    {
        $defaultDisk = config('filesystems.default', 'minio');
        $diskName = $file->storageDisk?->disk_name
            ?? (is_string($defaultDisk) ? $defaultDisk : 'minio');

        return $this->forDisk($diskName);
    }

    /**
     * Resolve driver ของ default disk
     *
     * @return StorageDriverInterface driver instance ของ default filesystem
     */
    public function default(): StorageDriverInterface
    {
        $default = config('filesystems.default', 'minio');

        return $this->forDisk(is_string($default) ? $default : 'minio');
    }
}
