<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Files;

use App\Models\Document;
use App\Models\Image;
use App\Models\Sounds;
use App\Models\StorageFiles;
use App\Models\Video;
use Core\Base\Repositories\Eloquent\BaseRepository;
use Core\Base\Repositories\Files\Interfaces\StorageFileInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * StorageFileRepository — Data Access Layer สำหรับ StorageFiles
 *
 * Extend BaseRepository เพื่อรับ operations มาตรฐานครบชุด:
 * - find(), findOrFail(), findMany(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), simplePaginate(), cursorPaginate(), lazy(), chunk()
 * - transaction(), lockForUpdate(), increment(), decrement()
 * - withCriteria(), remember(), enableCache()
 * - beforeCreate(), afterCreate(), hooks ต่างๆ
 *
 * Repository นี้ implement เฉพาะ domain-specific methods ของ StorageFiles
 *
 * ตัวอย่างการใช้งาน base methods:
 * ```php
 * // ค้นหาจาก ID (จาก BaseRepository)
 * $file = $repo->find($id);
 * $file = $repo->findOrFail($id, ['fileable']);
 *
 * // สร้างไฟล์ใหม่ (จาก BaseRepository)
 * $file = $repo->create(['path' => '...', 'mime_type' => '...']);
 *
 * // ลบด้วย soft delete (จาก BaseRepository)
 * $repo->delete($id);
 *
 * // ใช้ Criteria (จาก BaseRepository)
 * $files = $repo->withCriteria(new ActiveFilesCriteria())->paginate(20);
 * ```
 */
class StorageFileRepository extends BaseRepository implements StorageFileInterface
{
    /**
     * รายการ Model ที่เป็น polymorphic fileable types
     *
     * กำหนดที่นี่จุดเดียว — ถ้าเพิ่ม model ใหม่ (เช่น Spreadsheet) แก้ที่นี่เท่านั้น
     *
     * @var array<int, class-string>
     */
    protected array $fileableTypes = [
        Document::class,
        Image::class,
        Video::class,
        Sounds::class,
    ];

    public function __construct(StorageFiles $model)
    {
        parent::__construct($model);
    }

    // =========================================================================
    // ETag Lookup
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก ETag — เฉพาะไฟล์ที่ active (ไม่รวม soft-deleted)
     *
     * ใช้สำหรับ dedup ก่อน upload: ถ้าไฟล์ถูกลบไปแล้ว (soft-deleted)
     * ถือว่า "ไม่มี" และสามารถ upload ใหม่ได้
     *
     * @param  string  $etags  ETag ที่ได้จาก S3/MinIO response
     * @return StorageFiles|null พร้อม relation 'fileable' หรือ null ถ้าไม่พบ / ถูกลบแล้ว
     */
    public function checkFiles(string $etags): ?StorageFiles
    {
        /** @var StorageFiles|null */
        return $this->newQuery()
            ->whereHasMorph(
                'fileable',
                $this->fileableTypes,
                function (Builder $query) use ($etags): void {
                    $query->where('etags', $etags);
                },
            )
            ->with('fileable')
            ->first();
    }

    /**
     * ค้นหาไฟล์จาก ETag — รวม soft-deleted ทุกสถานะ
     *
     * ใช้สำหรับ admin audit, restore, หรือตรวจว่า ETag เคยมีในระบบ
     *
     * @param  string  $etags  ETag ที่ต้องการค้นหา
     * @return StorageFiles|null พร้อม relation 'fileable' (รวมที่ถูกลบ) หรือ null ถ้าไม่พบเลย
     */
    public function checkFilesWithTrashed(string $etags): ?StorageFiles
    {
        /** @var StorageFiles|null */
        return $this->newQuery()
            ->withTrashed()
            ->whereHasMorph(
                'fileable',
                $this->fileableTypes,
                function (Builder $query) use ($etags): void {
                    $query->withTrashed()->where('etags', $etags);
                },
            )
            ->with(['fileable' => function (Builder $query): void {
                $query->withTrashed();
            }])
            ->first();
    }

    // =========================================================================
    // Path Lookup
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก storage path — เฉพาะไฟล์ที่ active (ไม่รวม soft-deleted)
     *
     * ใช้ newQuery() เพื่อรองรับ criteria pattern และ multi-DB connection
     *
     * @param  string  $path  storage path เช่น 'uploads/abc.jpg'
     * @return StorageFiles|null คืนค่า null ถ้าไม่พบ
     */
    public function findByPath(string $path): ?StorageFiles
    {
        /** @var StorageFiles|null */
        return $this->newQuery()
            ->where('path', $path)
            ->first();
    }

    // =========================================================================
    // Hash Lookup
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก SHA-256 hash (รวม soft-deleted)
     *
     * ใช้สำหรับ global deduplication — ถ้า hash ตรงกัน ไม่ต้อง upload ซ้ำ
     *
     * @param  string  $sha256  SHA-256 hash ของไฟล์
     * @return StorageFiles|null คืนค่า null ถ้าไม่พบ
     */
    public function findBySha256WithTrashed(string $sha256): ?StorageFiles
    {
        /** @var StorageFiles|null */
        return $this->newQuery()
            ->withTrashed()
            ->where('checksum', $sha256)
            ->first();
    }
}
