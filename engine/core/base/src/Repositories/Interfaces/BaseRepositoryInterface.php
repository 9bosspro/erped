<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Base Repository Interface — รวมความสามารถทั้งหมดจาก sub-interface
 *
 * ใช้สำหรับ Repository มาตรฐานที่ต้องการ feature ครบทุกด้าน
 * ถ้าต้องการเฉพาะบางด้าน ให้ implement sub-interface โดยตรง
 *
 * @see ReadableInterface      — อ่านข้อมูล (find, all)
 * @see WritableInterface      — เขียนข้อมูล (create, update, delete)
 * @see QueryableInterface     — query ขั้นสูง (where, aggregate, exists)
 * @see PaginatableInterface   — แบ่งหน้า (paginate, cursor, chunk)
 * @see CacheableInterface     — cache layer
 * @see CriteriaInterface      — Criteria Pattern สำหรับ reusable query conditions
 * @see HookableInterface      — repository-level hooks (before/after CRUD)
 * @see ConcurrencyInterface   — transaction, locking
 * @see SoftDeletableInterface — soft delete operations
 */
interface BaseRepositoryInterface extends CacheableInterface, ConcurrencyInterface, CriteriaInterface, HookableInterface, PaginatableInterface, QueryableInterface, ReadableInterface, SoftDeletableInterface, WritableInterface
{
    /**
     * คืนชื่อ class ของ Model แบบ FQCN
     *
     * @return string เช่น "App\Models\User"
     */
    public function getModel(): string;

    /**
     * คืน Model instance สำหรับเข้าถึง Model โดยตรง
     */
    public function modelInstance(): Model;

    /**
     * คืน Query Builder สำหรับ query ขั้นสูงที่ Repository ไม่ได้รองรับ
     */
    public function getQuery(): Builder;

    /**
     * Refresh model instance จากฐานข้อมูล (reload attributes + relations)
     */
    public function refresh(Model $model): Model;
}
