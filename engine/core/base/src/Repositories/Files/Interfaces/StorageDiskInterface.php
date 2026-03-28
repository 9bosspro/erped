<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Files\Interfaces;

use App\Models\StorageDisk;
use Core\Base\Repositories\Interfaces\BaseRepositoryInterface;
use Engine\Modules\Files\DTOs\Disk\AddDiskData;

/**
 * Interface สำหรับ StorageDisk repository
 *
 * Extend BaseRepositoryInterface เพื่อรับ operations มาตรฐานทั้งหมด:
 * - find(), findMany(), findOrFail(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), lazy(), chunk()
 * - transaction(), lockForUpdate()
 * - cache, criteria, hooks
 *
 * Interface นี้ประกาศเฉพาะ domain-specific methods ของ StorageDisk เท่านั้น
 * ที่ไม่มีใน BaseRepositoryInterface
 *
 * หลักการออกแบบ:
 * - Single Responsibility: แยกออกจาก StorageFileInterface ชัดเจน
 * - disk record ใน DB คือ lookup table — ไม่ใช่ file storage config โดยตรง
 */
interface StorageDiskInterface extends BaseRepositoryInterface
{
    // =========================================================================
    // Disk Lookup — ค้นหา disk record จาก DB
    // =========================================================================

    /**
     * ค้นหา disk record จากชื่อ disk
     *
     * ใช้สำหรับตรวจสอบว่า disk นั้นมีอยู่ในระบบหรือไม่ก่อน upload
     * คืนค่า null ถ้าไม่พบ — caller ควรตัดสินใจว่าจะ fallback หรือ reject
     *
     * @param  string  $driverName  ชื่อ disk เช่น 'minio', 's3', 'local'
     * @return StorageDisk|null คืนค่า null ถ้าไม่พบ disk นั้นในระบบ
     */
    public function getDriverName(string $driverName): ?StorageDisk;

    // =========================================================================
    // Disk Registration — ลงทะเบียน disk ใหม่เข้าระบบ
    // =========================================================================

    /**
     * ลงทะเบียน disk/driver ใหม่ลงฐานข้อมูล (idempotent — firstOrCreate)
     *
     * ถ้า disk ที่มีชื่อและ driver เดียวกันมีอยู่แล้ว จะคืนค่า record เดิม
     * ไม่สร้างซ้ำ — ปลอดภัยสำหรับการเรียกซ้ำ (เช่นตอน deploy)
     *
     * @param  AddDiskData  $data  DTO สำหรับเพิ่ม disk
     * @return StorageDisk instance ที่มีอยู่แล้วหรือสร้างใหม่
     */
    public function addDriverName(AddDiskData $data): StorageDisk;
}
