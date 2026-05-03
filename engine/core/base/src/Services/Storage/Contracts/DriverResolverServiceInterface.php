<?php

declare(strict_types=1);

namespace Core\Base\Services\Storage\Contracts;

use App\Models\StorageFiles;
use Core\Base\Contracts\FileStorage\StorageDriverInterface;

/**
 * DriverResolverServiceInterface — สัญญาสำหรับ resolve storage driver
 *
 * กำหนด contract สำหรับ resolve driver ตาม disk name หรือ model
 * ช่วยให้ swap implementation และ mock ใน test ได้โดยไม่แก้โค้ดผู้ใช้
 */
interface DriverResolverServiceInterface
{
    /**
     * Resolve driver จากชื่อ disk (เช่น 'minio', 'local', 's3')
     *
     * @param  string  $disk  ชื่อ disk ตาม filesystems config
     * @return StorageDriverInterface driver instance ที่พร้อมใช้งาน
     */
    public function forDisk(string $disk): StorageDriverInterface;

    /**
     * Resolve driver จาก StorageFiles model
     *
     * ดึง disk_name จาก relation storageDisk — fallback ไป default disk
     *
     * @param  StorageFiles  $file  model ของไฟล์ที่ต้องการ driver
     * @return StorageDriverInterface driver instance สำหรับไฟล์นี้
     */
    public function forFile(StorageFiles $file): StorageDriverInterface;

    /**
     * Resolve driver ของ default disk
     *
     * @return StorageDriverInterface driver instance ของ default filesystem
     */
    public function default(): StorageDriverInterface;
}
