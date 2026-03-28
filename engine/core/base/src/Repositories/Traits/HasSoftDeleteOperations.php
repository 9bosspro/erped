<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Trait HasSoftDeleteOperations — soft delete operations สำหรับ Model ที่ใช้ SoftDeletes
 *
 * ทุก method จะ throw LogicException ถ้า Model ไม่ได้ use SoftDeletes trait
 * ตรวจสอบด้วย isSoftDeletable() ก่อนเรียกใช้ถ้าไม่แน่ใจ
 *
 * Performance: ผลลัพธ์ของ isSoftDeletable() ถูก cache ใน $softDeletable
 * เพื่อไม่ต้องทำ class_uses_recursive() (reflection) ทุกครั้ง
 */
trait HasSoftDeleteOperations
{
    /** @var bool|null cache ผล isSoftDeletable — null = ยังไม่ได้ check */
    private ?bool $softDeletable = null;

    /**
     * ค้นหา record จาก ID รวม soft-deleted (withTrashed)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  eager-load relations
     */
    public function findWithTrashed(int|string $id, array $relations = []): ?Model
    {
        $this->assertSoftDeletable();

        return $this->newQuery()
            ->withTrashed()
            ->with($relations)
            ->find($id);
    }

    /**
     * ค้นหา record จาก ID เฉพาะที่ถูก soft-delete แล้ว (onlyTrashed)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  eager-load relations
     */
    public function findTrashed(int|string $id, array $relations = []): ?Model
    {
        $this->assertSoftDeletable();

        return $this->newQuery()
            ->onlyTrashed()
            ->with($relations)
            ->find($id);
    }

    /**
     * ดึง records ทั้งหมดรวม soft-deleted (withTrashed)
     *
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return Collection<int, Model>
     */
    public function allWithTrashed(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection
    {
        $this->assertSoftDeletable();

        $query = $this->newQuery()->withTrashed()->with($relations);

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get();
    }

    /**
     * ดึงเฉพาะ records ที่ถูก soft-delete (onlyTrashed)
     *
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return Collection<int, Model>
     */
    public function onlyTrashed(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection
    {
        $this->assertSoftDeletable();

        $query = $this->newQuery()->onlyTrashed()->with($relations);

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get();
    }

    /**
     * แบ่งหน้าเฉพาะ records ที่ถูก soft-delete
     */
    public function paginateTrashed(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['deleted_at' => 'desc'],
    ): LengthAwarePaginator {
        $this->assertSoftDeletable();

        $query = $this->newQuery()->onlyTrashed()->with($relations);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * กู้คืน (restore) record ที่ถูก soft-delete จาก ID
     *
     * @param  int|string  $id  primary key value
     * @return bool true ถ้ากู้คืนสำเร็จ, false ถ้าไม่พบ record
     */
    public function restore(int|string $id): bool
    {
        $this->assertSoftDeletable();

        $model = $this->newQuery()
            ->onlyTrashed()
            ->whereKey($id)
            ->first();

        if ($model === null) {
            return false;
        }

        return (bool) $model->restore();
    }

    /**
     * กู้คืน records ที่ตรงเงื่อนไขทั้งหมด
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @return int จำนวน records ที่กู้คืนสำเร็จ
     */
    public function restoreWhere(array $conditions): int
    {
        $this->assertSoftDeletable();

        $models = $this->newQuery()
            ->onlyTrashed()
            ->where($conditions)
            ->get();

        $count = 0;

        foreach ($models as $model) {
            if ($model->restore()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * ลบถาวร (force delete) จาก ID — ลบจากฐานข้อมูลจริงๆ ไม่สามารถกู้คืนได้
     *
     * ค้นหาทั้ง active และ soft-deleted (withTrashed) เพื่อรองรับทั้ง 2 กรณี
     *
     * @param  int|string  $id  primary key value
     * @return bool true ถ้าลบสำเร็จ, false ถ้าไม่พบ record
     */
    public function forceDelete(int|string $id): bool
    {
        $this->assertSoftDeletable();

        $model = $this->newQuery()
            ->withTrashed()
            ->whereKey($id)
            ->first();

        if ($model === null) {
            return false;
        }

        return (bool) $model->forceDelete();
    }

    /**
     * ลบถาวร records ที่ตรงเงื่อนไขทั้งหมด
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @return int จำนวน records ที่ถูกลบถาวร
     */
    public function forceDeleteWhere(array $conditions): int
    {
        $this->assertSoftDeletable();

        $models = $this->newQuery()
            ->withTrashed()
            ->where($conditions)
            ->get();

        $count = 0;

        foreach ($models as $model) {
            if ($model->forceDelete()) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * นับจำนวน records ที่ถูก soft-delete (onlyTrashed)
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม (optional)
     * @return int จำนวน records ที่ถูก soft-delete
     */
    public function countTrashed(?Closure $queryCallback = null): int
    {
        $this->assertSoftDeletable();

        $query = $this->newQuery()->onlyTrashed();

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        return $query->count();
    }

    /**
     * กู้คืน records ที่ตรงเงื่อนไขผ่าน query builder (ไม่ trigger Model events)
     *
     * เร็วกว่า restoreWhere() มากเพราะไม่โหลด records เข้า memory
     * ใช้เมื่อ dataset ขนาดใหญ่ หรือไม่ต้องการ Model events
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where (AND)
     * @return int จำนวน records ที่ถูกกู้คืน
     */
    public function bulkRestore(array $conditions): int
    {
        $this->assertSoftDeletable();

        return $this->newQuery()
            ->onlyTrashed()
            ->where($conditions)
            ->restore();
    }

    /**
     * ลบถาวร records ที่ตรงเงื่อนไขผ่าน query builder (ไม่ trigger Model events)
     *
     * เร็วกว่า forceDeleteWhere() มากเพราะไม่โหลด records เข้า memory
     * ใช้เมื่อ dataset ขนาดใหญ่ หรือไม่ต้องการ Model events
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where (AND)
     * @return int จำนวน records ที่ถูกลบถาวร
     */
    public function bulkForceDelete(array $conditions): int
    {
        $this->assertSoftDeletable();

        return $this->newQuery()
            ->withTrashed()
            ->where($conditions)
            ->forceDelete();
    }

    /**
     * ตรวจสอบว่า Model ใช้ SoftDeletes trait หรือไม่
     *
     * ผลลัพธ์ถูก cache ใน $softDeletable เพื่อไม่ต้องทำ reflection ทุกครั้ง
     */
    public function isSoftDeletable(): bool
    {
        if ($this->softDeletable === null) {
            $this->softDeletable = in_array(
                SoftDeletes::class,
                class_uses_recursive($this->model),
                strict: true,
            );
        }

        return $this->softDeletable;
    }

    /**
     * Assert ว่า Model ใช้ SoftDeletes — throw ถ้าไม่ใช่
     *
     * @throws LogicException ถ้า Model ไม่ได้ use SoftDeletes trait
     */
    protected function assertSoftDeletable(): void
    {
        if (! $this->isSoftDeletable()) {
            throw new LogicException(
                sprintf(
                    'Model [%s] ไม่ได้ใช้ SoftDeletes trait — ไม่สามารถเรียก soft delete operations ได้',
                    get_class($this->model),
                ),
            );
        }
    }
}
