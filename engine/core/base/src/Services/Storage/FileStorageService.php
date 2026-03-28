<?php

declare(strict_types=1);

namespace Core\Base\Services\Storage;

use App\Models\StorageDisk;
use App\Models\StorageFiles;
use Core\Base\Exceptions\Storage\FileAlreadyDeletedException;
use Core\Base\Exceptions\Storage\FileNotDeletedException;
use Core\Base\Exceptions\Storage\FileNotFoundException;
use Core\Base\Repositories\Files\Interfaces\StorageDiskInterface;
use Core\Base\Repositories\Files\Interfaces\StorageFileInterface;
use Engine\Modules\Files\Actions\DeleteFileAction;
use Engine\Modules\Files\Actions\DeleteForceFileAction;
use Engine\Modules\Files\Actions\UpdateUploadFileAction;
use Engine\Modules\Files\Actions\UploadFileAction;
use Engine\Modules\Files\DTOs\DeleteActionDTO;
use Engine\Modules\Files\DTOs\DeleteForceActionDTO;
use Engine\Modules\Files\DTOs\UpdateUploadActionDTO;
use Engine\Modules\Files\DTOs\UploadAsyncActionDTO;
use Engine\Modules\Files\DTOs\UploadAsyncDTO;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use Engine\Modules\Files\DTOs\Disk\AddDiskData;

/**
 * FileStorageService — Service หลักสำหรับจัดการไฟล์ใน Storage
 *
 * รับผิดชอบ orchestration ระหว่าง Repository, Action, และ Driver:
 * - Upload (async single / async batch)
 * - Delete (soft delete / force delete / batch soft delete)
 * - Read (url, temporaryUrl, download, exists, metadata)
 * - List / paginate
 *
 * ⚠️ Stateless — ไม่มี mutable state หลัง construction
 * Dependency ทั้งหมด inject ผ่าน constructor
 */
class FileStorageService
{
    public function __construct(
        private readonly StorageFileInterface $storageFileDbRepository,
        private readonly StorageDiskInterface $storageDiskDbRepository,
        private readonly UploadFileAction $uploadFileAction,
        private readonly DriverResolverService $driverResolverService,
        private readonly DeleteFileAction $deleteFileAction,
        private readonly DeleteForceFileAction $deleteForceFileAction,
        private readonly UpdateUploadFileAction $updateUploadFileAction,
    ) {}

    // =========================================================================
    // Disk Management
    // =========================================================================

    /**
     * บันทึกชื่อ driver ลงใน storage_disks
     *
     * @param  AddDiskData  $data  DTO สำหรับเพิ่ม disk
     */
    public function addDriverName(AddDiskData $data): StorageDisk
    {
        $disk = $this->storageDiskDbRepository->addDriverName($data);

        // เคลียร์ Cache หลังจากมีการเพิ่มข้อมูลใหม่ เพื่อให้ Query รอบหน้าได้ข้อมูลอัปเดต
        $this->storageDiskDbRepository->forgetCache();

        return $disk;
    }

    /**
     * รายการ disk ทั้งหมด (paginated)
     *
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default 15)
     */
    public function listDisks(int $perPage = 15): LengthAwarePaginator
    {
        // ใช้งาน Caching Layer พร้อมป้องกัน Cache Stampede (Thundering herd) สำหรับ high-traffic endpoint
        $page = request()->query('page', 1);
        $perPage = request()->query('per_page', $perPage);
        $cacheKey = "storage_disks_page_{$perPage}_{$page}";

        return $this->storageDiskDbRepository->remember(
            cacheKey: $cacheKey,
            ttl: 3600, // แคชไว้ 1 ชั่วโมง
            callback: fn($repo) => $repo->paginate($perPage),
            preventStampede: true, // ป้องกัน stampede
        );
    }

    /**
     * ดึงข้อมูล disk ตาม id พร้อม Caching
     *
     * @param  string  $id  UUID ของ disk
     */
    public function showDisk(string $id): ?StorageDisk
    {
        // ใช้ remembered query ระดับ ID
        $cacheKey = "storage_disk_{$id}";

        /** @var StorageDisk|null */
        return $this->storageDiskDbRepository->remember(
            cacheKey: $cacheKey,
            ttl: 7200, // แคชไว้ 2 ชั่วโมง
            callback: fn($repo) => $repo->find($id),
        );
    }

    /**
     * อัปเดตข้อมูล disk
     *
     * @param  string  $id  UUID ของ disk
     * @param  array<string,mixed>  $data  ข้อมูลที่ต้องการอัปเดต
     */
    public function updateDisk(string $id, array $data): ?StorageDisk
    {
        /** @var StorageDisk|null */
        $disk = $this->storageDiskDbRepository->update($id, $data);

        if ($disk) {
            // เคลียร์ Cache แบบ Tag (ทิ้ง Pagination) และแบบ Key เดี่ยว
            $this->storageDiskDbRepository->forgetCache();
            $this->storageDiskDbRepository->forgetByKey("storage_disk_{$id}");
        }

        return $disk;
    }

    /**
     * ลบ disk (soft delete)
     *
     * @param  string  $id  UUID ของ disk
     */
    public function deleteDisk(string $id): bool
    {
        $deleted = (bool) $this->storageDiskDbRepository->delete($id);

        if ($deleted) {
            $this->storageDiskDbRepository->forgetCache();
            $this->storageDiskDbRepository->forgetByKey("storage_disk_{$id}");
        }

        return $deleted;
    }

    // =========================================================================
    // Upload Operations
    // =========================================================================

    /**
     * Async upload ไฟล์เดียว: บันทึก DB + เก็บ temp แล้ว dispatch Job
     *
     * Client ได้ { id, status: "pending", path } กลับทันที
     * แล้ว poll GET /files/{id}/status เพื่อตรวจสอบ
     *
     * @param  UploadAsyncDTO  $dto  DTO สำหรับ upload (file, disk, directory, userId)
     * @return array{id: string, status: string, path: string, is_active: bool}
     *
     * @throws RuntimeException ถ้า DTO ไม่มี disk
     */
    public function uploadAsync(UploadAsyncDTO $dto): array
    {
        $disk = $dto->disk ?? throw new RuntimeException('Missing required key: disk');

        return $this->uploadFileAction->execute(new UploadAsyncActionDTO(
            objdriver: $this->driverResolverService->forDisk($disk),
            file: $dto->file,
            directory: $dto->directory ?? '',
            userId: $dto->userId ?? null,
        ));
    }

    /**
     * Async upload หลายไฟล์พร้อมกัน — dispatch Job แยกต่อไฟล์
     *
     * ทุก upload รัน DB transaction เดียวกัน — ถ้าไฟล์ใดล้มเหลว rollback ทั้งหมด
     *
     * @param  UploadedFile[]  $files  ไฟล์ที่ต้องการ upload
     * @param  string  $directory  directory ปลายทาง
     * @param  string  $disk  ชื่อ disk ('minio', 'local', 's3')
     * @param  string|null  $userId  UUID ของเจ้าของไฟล์
     * @return array<int, array{id: string, status: string, path: string}>
     */
    public function uploadManyAsync(
        array $files,
        string $directory = 'uploads',
        string $disk = 'local',
        ?string $userId = null,
    ): array {
        $results = [];

        DB::transaction(function () use ($files, $disk, $directory, $userId, &$results): void {
            foreach ($files as $file) {
                if ($file instanceof UploadedFile) {
                    $results[] = $this->uploadAsync(new UploadAsyncDTO(
                        file: $file,
                        directory: $directory,
                        disk: $disk,
                        userId: $userId,
                    ));
                }
            }
        });

        return $results;
    }

    /**
     * อัปโหลดไฟล์ใหม่ทับไฟล์เดิม (async update)
     *
     * @param  UploadedFile  $file  ไฟล์ใหม่
     * @param  string  $id  UUID ของไฟล์เดิม
     * @param  string|null  $userId  UUID ของผู้ดำเนินการ (null = system)
     * @return array{id: string, status: string, path: string, old_path: string}
     *
     * @throws FileNotFoundException ถ้าไม่พบไฟล์เดิม
     */
    public function updateUploadAsync(UploadedFile $file, string $id, ?string $userId = null): array
    {
        $existing = $this->storageFileDbRepository->findWithTrashed($id);

        if (! $existing) {
            throw new FileNotFoundException($id);
        }

        return $this->updateUploadFileAction->execute(new UpdateUploadActionDTO(
            objdriver: $this->driverResolverService->forFile($existing),
            newfile: $file,
            oldfile: $existing,
            updateId: $id,
            userId: $userId,
        ));
    }

    // =========================================================================
    // Delete Operations
    // =========================================================================

    /**
     * Soft delete ไฟล์ตาม ID — ไฟล์ยังอยู่ใน storage แต่ไม่แสดงในรายการ
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $userId  UUID ของผู้ลบ
     * @return array{id: string, status: string, diskName: string}
     *
     * @throws FileNotFoundException ไม่พบไฟล์
     * @throws FileAlreadyDeletedException ไฟล์ถูก soft delete ไปแล้ว
     */
    public function delete(string $id, ?string $userId = null): array
    {
        $file = $this->storageFileDbRepository->findWithTrashed($id);

        if (! $file) {
            throw new FileNotFoundException($id);
        }

        if ($file->trashed()) {
            throw new FileAlreadyDeletedException;
        }

        return $this->deleteFileAction->execute(new DeleteActionDTO(
            file: $file,
            filedriver: $this->driverResolverService->forFile($file),
            userId: $userId ?? Auth::id(),
        ));
    }

    /**
     * Force delete ไฟล์ตาม ID — ลบทั้ง DB และ physical file ถาวร
     *
     * ไฟล์ต้องถูก soft delete ก่อนแล้วเท่านั้น
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $userId  UUID ของผู้ลบ
     * @return array{id: string, status: string, diskName: string, job: string}
     *
     * @throws FileNotFoundException ไม่พบไฟล์
     * @throws FileNotDeletedException ไฟล์ยังไม่ถูก soft delete
     */
    public function forceDelete(string $id, ?string $userId = null): array
    {
        $file = $this->storageFileDbRepository->findWithTrashed($id);

        if (! $file) {
            throw new FileNotFoundException($id);
        }

        if (! $file->trashed()) {
            throw new FileNotDeletedException;
        }

        return $this->deleteForceFileAction->execute(new DeleteForceActionDTO(
            file: $file,
            fileable: $file->fileable()->withTrashed()->first(),
            filedriver: $this->driverResolverService->forFile($file),
            userId: $userId ?? Auth::id(),
        ));
    }

    /**
     * Soft delete ไฟล์หลายรายการพร้อมกัน (batch)
     *
     * ✅ soft delete เท่านั้น — ไม่ลบ physical file
     * ถ้าต้องการลบถาวร ต้องเรียก forceDelete() รายไฟล์
     *
     * @param  string[]  $ids  UUID ของไฟล์ที่ต้องการลบ
     * @return int จำนวนไฟล์ที่ soft delete สำเร็จ
     */
    public function deleteMany(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $count = 0;

        // เราหุ้มด้วย Transaction แบบภาพรวม
        // และเรียกใช้ $this->delete() เพื่อให้กระบวนการไปเรียก DeleteFileAction
        // ซึ่งจะชัวร์ว่า polymorphic relation (fileable) และ Hook ต่างๆ ทำงานครบถ้วน
        DB::transaction(function () use ($ids, &$count): void {
            foreach ($ids as $id) {
                try {
                    $this->delete($id);
                    $count++;
                } catch (Exception $e) {
                    // ถ้า catch เป็น FileAlreadyDeletedException หรือ FileNotFoundException
                    // สามารถข้ามไปลบอันถัดไปได้ทันที เพื่อไม่ให้ระบบหยุดทำงานทั้งล็อต
                    Log::warning('Batch delete skipped for file', ['id' => $id, 'reason' => $e->getMessage()]);
                }
            }
        });

        return $count;
    }

    // =========================================================================
    // Read Operations
    // =========================================================================

    /**
     * ดึง URL ของไฟล์ตาม ID
     *
     * @param  string  $id  UUID ของไฟล์
     * @return string|null URL หรือ null ถ้าไม่พบ
     */
    public function getUrl(string $id): ?string
    {
        $file = $this->storageFileDbRepository->find($id);

        return $file
            ? $this->driverResolverService->forFile($file)->url($file->path)
            : null;
    }

    /**
     * ดึง temporary URL (pre-signed) ของไฟล์ตาม ID
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  int  $minutes  ระยะเวลา (นาที) ก่อน URL หมดอายุ (default: 60)
     * @return string|null Temporary URL หรือ null ถ้าไม่พบ
     */
    public function getTemporaryUrl(string $id, int $minutes = 60): ?string
    {
        $file = $this->storageFileDbRepository->find($id);

        return $file
            ? $this->driverResolverService->forFile($file)->temporaryUrl($file->path, now()->addMinutes($minutes))
            : null;
    }

    /**
     * ดาวน์โหลดไฟล์ตาม ID — คืน StreamedResponse
     *
     * @param  string  $id  UUID ของไฟล์
     * @param  string|null  $name  ชื่อไฟล์สำหรับ download (null = ใช้ original_name)
     * @return mixed StreamedResponse หรือ null ถ้าไม่พบ
     */
    public function download(string $id, ?string $name = null): mixed
    {
        $file = $this->storageFileDbRepository->find($id);

        return $file
            ? $this->driverResolverService->forFile($file)->download($file->path, $name ?? $file->original_name)
            : null;
    }

    /**
     * ตรวจสอบว่าไฟล์มีอยู่จริงทั้งใน DB และ physical storage
     *
     * @param  string  $id  UUID ของไฟล์
     * @return bool true ถ้าพบทั้งใน DB และ storage จริง
     */
    public function exists(string $id): bool
    {
        $file = $this->storageFileDbRepository->find($id);

        return $file
            ? $this->driverResolverService->forFile($file)->exists($file->path)
            : false;
    }

    /**
     * ค้นหาไฟล์จาก ID — คืน model เพื่อให้ controller ใช้ Resource แปลง
     *
     * @param  string  $id  UUID ของไฟล์
     */
    public function findFile(string $id): ?StorageFiles
    {
        return $this->storageFileDbRepository->find($id);
    }

    /**
     * ค้นหาไฟล์จาก path บน storage
     *
     * @param  string  $path  path ของไฟล์
     */
    public function findByPath(string $path): ?StorageFiles
    {
        return $this->storageFileDbRepository->findByPath($path);
    }

    /**
     * ดึงสถานะ upload ของไฟล์ (รวม trashed)
     *
     * ใช้สำหรับ polling หลัง uploadAsync
     *
     * @param  string  $id  UUID ของไฟล์
     * @return array{id: string, status: string, path: ?string, error: ?string, trashed: bool, created_by: ?string}|null
     */
    public function getUploadStatus(string $id): ?array
    {
        $file = $this->storageFileDbRepository->findWithTrashed($id);

        if (! $file) {
            return null;
        }

        $status = $file->metadata['upload_status'] ?? ($file->is_active ? 'completed' : 'pending');

        return [
            'id' => $file->id,
            'status' => $status,
            'path' => ($status === 'completed') ? $file->path : null,
            'error' => $file->metadata['error'] ?? null,
            'trashed' => $file->trashed(),
            'created_by' => $file->metadata['created_by'] ?? null,
        ];
    }

    // =========================================================================
    // Query / List Operations
    // =========================================================================

    /**
     * แสดงรายการไฟล์ทั้งหมด (paginated) ผ่าน Criteria Pattern
     *
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default: 15)
     */
    public function list(int $perPage = 15): LengthAwarePaginator
    {
        return $this->storageFileDbRepository
            ->withCriteria(new \Engine\Modules\Files\Criterias\AllFilesCriteria)
            ->paginate($perPage);
    }

    /**
     * แสดงรายการไฟล์ของ user (paginated) ผ่าน Criteria Pattern
     *
     * @param  string  $userId  UUID ของ user
     * @param  int  $perPage  จำนวนรายการต่อหน้า (default: 15)
     */
    public function getByUser(string $userId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->storageFileDbRepository
            ->withCriteria(new \Engine\Modules\Files\Criterias\UserFilesCriteria($userId))
            ->paginate($perPage);
    }

    // =========================================================================
    // Temp File Cleanup
    // =========================================================================

    /**
     * ลบไฟล์ชั่วคราวออกจาก localtmp disk (best-effort — ไม่ throw exception)
     *
     * เรียกหลัง ProcessFileUploadJob ทำงานสำเร็จเพื่อคืน disk space
     *
     * @param  string  $tempPath  path ของไฟล์ชั่วคราวบน localtmp disk
     */
    public function cleanupTemp(string $tempPath): void
    {
        try {
            if (Storage::disk('localtmp')->exists($tempPath)) {
                Storage::disk('localtmp')->delete($tempPath);
            }
        } catch (Throwable) {
            Log::warning('FileStorageService: cleanup temp failed', ['path' => $tempPath]);
        }
    }
}
