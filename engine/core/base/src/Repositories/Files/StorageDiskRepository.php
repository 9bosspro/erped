<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Files;

use App\Models\StorageDisk;
use Core\Base\Repositories\Eloquent\BaseRepository;
use Core\Base\Repositories\Files\Interfaces\StorageDiskInterface;
use Illuminate\Support\Facades\Auth;
use Engine\Modules\Files\DTOs\Disk\AddDiskData;

/**
 * StorageDiskRepository — Data Access Layer สำหรับ StorageDisk
 *
 * Extend BaseRepository เพื่อรับ operations มาตรฐานครบชุด:
 * - find(), findOrFail(), findMany(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), simplePaginate(), cursorPaginate(), lazy(), chunk()
 * - transaction(), lockForUpdate(), increment(), decrement()
 * - withCriteria(), remember(), enableCache()
 * - beforeCreate(), afterCreate(), hooks ต่างๆ
 *
 * Repository นี้ implement เฉพาะ domain-specific methods ของ StorageDisk
 *
 * หลักการออกแบบ:
 * - แยกออกจาก StorageFileRepository ชัดเจน (SRP)
 * - ใช้ newQuery() ทุกครั้ง — ไม่ใช้ static Model:: หรือ $this->model->where() โดยตรง
 * - addDriverName() ใช้ firstOrCreate — idempotent ปลอดภัยสำหรับ concurrent requests
 */
class StorageDiskRepository extends BaseRepository implements StorageDiskInterface
{
    public function __construct(StorageDisk $model)
    {
        parent::__construct($model);
    }

    // =========================================================================
    // Disk Lookup
    // =========================================================================

    /**
     * ค้นหา disk record จากชื่อ disk
     *
     * ใช้ newQuery() เพื่อให้รองรับ criteria pattern และ multi-DB connection
     * ถ้าไม่พบ disk นั้นในระบบ คืนค่า null — caller ตัดสินใจว่าจะ fallback หรือ reject
     *
     * @param  string  $driverName  ชื่อ disk เช่น 'minio', 's3', 'local'
     * @return StorageDisk|null คืนค่า null ถ้าไม่พบ
     */
    public function getDriverName(string $driverName): ?StorageDisk
    {
        /** @var StorageDisk|null */
        return $this->newQuery()
            ->where('disk_name', $driverName)
            ->first();
    }

    // =========================================================================
    // Disk Registration
    // =========================================================================

    /**
     * ลงทะเบียน disk/driver ใหม่ลงฐานข้อมูล (idempotent — firstOrCreate)
     *
     * ถ้า disk ที่มีชื่อและ driver เดียวกันมีอยู่แล้ว จะคืนค่า record เดิม
     * ไม่สร้างซ้ำ — ปลอดภัยสำหรับการเรียกซ้ำ (เช่นตอน deploy หรือ seeder)
     *
     * หมายเหตุ: ใช้ newQuery() แทน $this->model->firstOrCreate() โดยตรง
     * เพื่อให้รองรับ criteria และ multi-DB connection ได้ถูกต้อง
     *
     * @param  AddDiskData  $data  DTO สำหรับเพิ่ม disk
     * @return StorageDisk instance ที่มีอยู่แล้วหรือสร้างใหม่
     */
    public function addDriverName(AddDiskData $data): StorageDisk
    {
        /** @var StorageDisk */
        return $this->newQuery()->firstOrCreate(
            ['disk_name' => $data->diskName, 'driver' => $data->driver],
            ['created_by' => $data->userId ?? Auth::id(), 'metadata' => []],
        );
    }
}
