<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * SoftDeletable Interface — operations สำหรับ Model ที่ใช้ SoftDeletes
 *
 * ทุก method จะ throw LogicException ถ้า Model ไม่ได้ use SoftDeletes trait
 */
interface SoftDeletableInterface
{
    /**
     * ค้นหา record จาก ID รวม soft-deleted (withTrashed)
     */
    public function findWithTrashed(int|string $id, array $relations = []): ?Model;

    /**
     * ค้นหา record จาก ID เฉพาะที่ถูก soft-delete แล้ว (onlyTrashed)
     */
    public function findTrashed(int|string $id, array $relations = []): ?Model;

    /**
     * ดึง records ทั้งหมดรวม soft-deleted
     *
     * @return Collection<int, Model>
     */
    public function allWithTrashed(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection;

    /**
     * ดึงเฉพาะ records ที่ถูก soft-delete (onlyTrashed)
     *
     * @return Collection<int, Model>
     */
    public function onlyTrashed(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection;

    /**
     * แบ่งหน้าเฉพาะ records ที่ถูก soft-delete
     */
    public function paginateTrashed(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['deleted_at' => 'desc'],
    ): LengthAwarePaginator;

    /**
     * กู้คืน (restore) record ที่ถูก soft-delete จาก ID
     *
     * @return bool true ถ้ากู้คืนสำเร็จ, false ถ้าไม่พบ record
     */
    public function restore(int|string $id): bool;

    /**
     * กู้คืน records ที่ตรงเงื่อนไข
     *
     * @return int จำนวน records ที่กู้คืน
     */
    public function restoreWhere(array $conditions): int;

    /**
     * ลบถาวร (force delete) จาก ID — ลบจากฐานข้อมูลจริงๆ
     *
     * @return bool true ถ้าลบสำเร็จ, false ถ้าไม่พบ record
     */
    public function forceDelete(int|string $id): bool;

    /**
     * ลบถาวร records ที่ตรงเงื่อนไข
     *
     * @return int จำนวน records ที่ถูกลบ
     */
    public function forceDeleteWhere(array $conditions): int;

    /**
     * นับจำนวน records ที่ถูก soft-delete
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function countTrashed(?Closure $queryCallback = null): int;

    /**
     * กู้คืน records ที่ตรงเงื่อนไขผ่าน query builder (เร็ว ไม่ trigger Model events)
     *
     * ใช้เมื่อ dataset ขนาดใหญ่ — ใช้ restoreWhere() ถ้าต้องการ Model events
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @return int จำนวน records ที่กู้คืน
     */
    public function bulkRestore(array $conditions): int;

    /**
     * ลบถาวร records ที่ตรงเงื่อนไขผ่าน query builder (เร็ว ไม่ trigger Model events)
     *
     * ใช้เมื่อ dataset ขนาดใหญ่ — ใช้ forceDeleteWhere() ถ้าต้องการ Model events
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @return int จำนวน records ที่ถูกลบถาวร
     */
    public function bulkForceDelete(array $conditions): int;

    /**
     * ตรวจสอบว่า Model ใช้ SoftDeletes trait หรือไม่
     */
    public function isSoftDeletable(): bool;
}
