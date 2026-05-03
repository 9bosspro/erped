<?php

declare(strict_types=1);

namespace Core\Base\Services\Storage\Contracts;

use App\Models\StorageDisk;
use App\Models\StorageFiles;
use Core\Base\DTO\ServiceResult;
use Engine\Modules\Files\DTOs\Disk\AddDiskData;
use Engine\Modules\Files\DTOs\UploadAsyncDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;

/**
 * FileStorageServiceInterface — สัญญาสำหรับ orchestrate file storage operations
 *
 * กำหนด contract สำหรับ upload, delete, read, list และ disk management
 * ช่วยให้ swap implementation และ mock ใน test ได้โดยไม่แก้โค้ดผู้ใช้
 */
interface FileStorageServiceInterface
{
    // =========================================================================
    // Disk Management
    // =========================================================================

    /**
     * บันทึกชื่อ driver ลงใน storage_disks
     *
     * @param  AddDiskData  $data  DTO สำหรับเพิ่ม disk
     */
    public function addDriverName(AddDiskData $data): StorageDisk;

    /**
     * รายการ disk ทั้งหมด (paginated)
     *
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default 15)
     */
    public function listDisks(int $perPage = 15): LengthAwarePaginator;

    /**
     * ดึงข้อมูล disk ตาม id พร้อม Caching
     *
     * @param  string  $id  UUID ของ disk
     */
    public function showDisk(string $id): ?StorageDisk;

    /**
     * อัปเดตข้อมูล disk
     *
     * @param  string  $id  UUID ของ disk
     * @param  array<string, mixed>  $data  ข้อมูลที่ต้องการอัปเดต
     */
    public function updateDisk(string $id, array $data): ?StorageDisk;

    /**
     * ลบ disk (soft delete)
     *
     * @param  string  $id  UUID ของ disk
     */
    public function deleteDisk(string $id): bool;

    // =========================================================================
    // Upload Operations
    // =========================================================================

    /**
     * Async upload ไฟล์เดียว: บันทึก DB + เก็บ temp แล้ว dispatch Job
     *
     * @param  UploadAsyncDTO  $dto  DTO สำหรับ upload
     * @return ServiceResult ข้อมูลสถานะการอัปโหลด
     */
    public function uploadAsync(UploadAsyncDTO $dto): ServiceResult;

    /**
     * Async upload หลายไฟล์พร้อมกัน — dispatch Job แยกต่อไฟล์
     *
     * @param  UploadedFile[]  $files  ไฟล์ที่ต้องการ upload
     * @param  string  $directory  directory ปลายทาง
     * @param  string  $disk  ชื่อ disk ('minio', 'local', 's3')
     * @param  string|null  $userId  UUID ของเจ้าของไฟล์
     * @return ServiceResult[]
     */
    public function uploadManyAsync(
        array $files,
        string $directory = 'uploads',
        string $disk = 'local',
        ?string $userId = null,
    ): array;

    /**
     * อัปโหลดไฟล์ใหม่ทับไฟล์เดิม (async update)
     *
     * @param  UploadedFile  $file  ไฟล์ใหม่
     * @param  string  $id  UUID ของไฟล์เดิม
     * @param  string|null  $userId  UUID ของผู้ดำเนินการ
     */
    public function updateUploadAsync(UploadedFile $file, string $id, ?string $userId = null): ServiceResult;

    // =========================================================================
    // Delete Operations
    // =========================================================================

    /**
     * Soft delete ไฟล์ตาม ID
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $userId  UUID ของผู้ลบ
     */
    public function delete(string $id, ?string $userId = null): ServiceResult;

    /**
     * Force delete ไฟล์ตาม ID — ลบทั้ง DB และ physical file ถาวร
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $userId  UUID ของผู้ลบ
     */
    public function forceDelete(string $id, ?string $userId = null): ServiceResult;

    /**
     * Soft delete ไฟล์หลายรายการพร้อมกัน (batch)
     *
     * @param  string[]  $ids  UUID ของไฟล์ที่ต้องการลบ
     * @return int จำนวนไฟล์ที่ soft delete สำเร็จ
     */
    public function deleteMany(array $ids): int;

    // =========================================================================
    // Read Operations
    // =========================================================================

    /**
     * ดึง URL ของไฟล์ตาม ID
     *
     * @param  string  $id  UUID ของไฟล์
     */
    public function getUrl(string $id): ?string;

    /**
     * ดึง temporary URL (pre-signed) ของไฟล์ตาม ID
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  int  $minutes  ระยะเวลา (นาที) ก่อน URL หมดอายุ (default: 60)
     */
    public function getTemporaryUrl(string $id, int $minutes = 60): ?string;

    /**
     * ดาวน์โหลดไฟล์ตาม ID — คืน StreamedResponse
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $name  ชื่อไฟล์สำหรับ download
     */
    public function download(string $id, ?string $name = null): ?\Symfony\Component\HttpFoundation\Response;

    /**
     * ตรวจสอบว่าไฟล์มีอยู่จริงทั้งใน DB และ physical storage
     *
     * @param  string  $id  UUID ของไฟล์
     */
    public function exists(string $id): bool;

    /**
     * ค้นหาไฟล์จาก ID
     *
     * @param  string  $id  UUID ของไฟล์
     */
    public function findFile(string $id): ?StorageFiles;

    /**
     * ค้นหาไฟล์จาก path บน storage
     *
     * @param  string  $path  path ของไฟล์
     */
    public function findByPath(string $path): ?StorageFiles;

    /**
     * ดึงสถานะ upload ของไฟล์ (รวม trashed)
     *
     * @param  string  $id  UUID ของไฟล์
     * @return array{id: string, status: string, path: ?string, error: ?string, trashed: bool, created_by: ?string}|null
     */
    public function getUploadStatus(string $id): ?array;

    // =========================================================================
    // Query / List Operations
    // =========================================================================

    /**
     * แสดงรายการไฟล์ทั้งหมด (paginated)
     *
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default: 15)
     */
    public function list(int $perPage = 15): LengthAwarePaginator;

    /**
     * แสดงรายการไฟล์ของ user (paginated)
     *
     * @param  string  $userId  UUID ของ user
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default: 15)
     */
    public function getByUser(string $userId, int $perPage = 15): LengthAwarePaginator;

    // =========================================================================
    // Temp File Cleanup
    // =========================================================================

    /**
     * ลบไฟล์ชั่วคราวออกจาก localtmp disk (best-effort)
     *
     * @param  string  $tempPath  path ของไฟล์ชั่วคราวบน localtmp disk
     */
    public function cleanupTemp(string $tempPath): void;
}
