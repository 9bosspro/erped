<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Files\Interfaces;

use App\Models\StorageFiles;
use Core\Base\Repositories\Interfaces\BaseRepositoryInterface;

/**
 * Interface สำหรับ StorageFile repository
 *
 * Extend BaseRepositoryInterface เพื่อรับ operations มาตรฐานทั้งหมด:
 * - find(), findMany(), findOrFail(), findWithTrashed()
 * - create(), update(), delete(), updateOrCreate()
 * - paginate(), lazy(), chunk()
 * - transaction(), lockForUpdate()
 * - cache, criteria, hooks
 *
 * Interface นี้ประกาศเฉพาะ domain-specific methods ของ StorageFiles เท่านั้น
 * ที่ไม่มีใน BaseRepositoryInterface
 *
 * @method \App\Models\StorageFiles|null find(int|string $id, array $relations = [])
 * @method \App\Models\StorageFiles findOrFail(int|string $id, array $relations = [])
 * @method \App\Models\StorageFiles|null findWithTrashed(string $id, array $relations = [])
 */
interface StorageFileInterface extends BaseRepositoryInterface
{
    // =========================================================================
    // ETag Lookup — ใช้สำหรับ deduplication ก่อน upload
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก ETag — เฉพาะไฟล์ที่ active (ไม่รวม soft-deleted)
     *
     * ใช้สำหรับ dedup ก่อน upload: ถ้าไฟล์ถูกลบไปแล้ว (soft-deleted)
     * ถือว่า "ไม่มี" และสามารถ upload ใหม่ได้
     *
     * @param  string  $etags  ETag ที่ได้จาก S3/MinIO response
     * @return StorageFiles|null พร้อม relation 'fileable' หรือ null ถ้าไม่พบ
     */
    public function checkFiles(string $etags): ?StorageFiles;

    /**
     * ค้นหาไฟล์จาก ETag — รวม soft-deleted ทุกสถานะ
     *
     * ใช้สำหรับ admin audit, restore, หรือตรวจว่า ETag เคยมีในระบบ
     *
     * @param  string  $etags  ETag ที่ต้องการค้นหา
     * @return StorageFiles|null พร้อม relation 'fileable' (รวมที่ถูกลบ)
     */
    public function checkFilesWithTrashed(string $etags): ?StorageFiles;

    // =========================================================================
    // Hash Lookup — ใช้สำหรับ integrity check และ dedup
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก SHA-256 hash (รวม soft-deleted)
     *
     * ใช้สำหรับ global deduplication: ถ้าพบไฟล์ที่ hash ตรงกัน
     * ไม่ต้อง upload ซ้ำ — ใช้ path เดิมได้เลย
     *
     * @param  string  $sha256  SHA-256 hash ของไฟล์
     * @return StorageFiles|null คืนค่า null ถ้าไม่พบ
     */
    public function findBySha256WithTrashed(string $sha256): ?StorageFiles;

    // =========================================================================
    // Path Lookup — ค้นหาไฟล์จาก storage path
    // =========================================================================

    /**
     * ค้นหาไฟล์จาก storage path — เฉพาะไฟล์ที่ active (ไม่รวม soft-deleted)
     *
     * ใช้สำหรับ API ที่รับ path เป็น parameter แทน ID
     * เช่น GET /files/{path} เพื่อดึง metadata จาก path
     *
     * @param  string  $path  storage path เช่น 'uploads/abc.jpg'
     * @return StorageFiles|null คืนค่า null ถ้าไม่พบ
     */
    public function findByPath(string $path): ?StorageFiles;
}
