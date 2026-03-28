<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Trait HasConcurrencyOperations — transaction, locking, atomic counter
 *
 * ทุก operation ใช้ database connection ของ Model (getConnection())
 * แทน DB facade เพื่อรองรับ multi-database / multi-tenancy
 *
 * ⚠️ lockForUpdate และ sharedLock ต้องอยู่ภายใน transaction
 * มิฉะนั้น lock จะ release ทันทีหลัง query
 */
trait HasConcurrencyOperations
{
    /**
     * ครอบ callback ด้วย database transaction บน connection ของ Model
     *
     * ใช้ connection ของ Model (ไม่ใช่ DB::transaction) เพื่อรองรับ multi-database:
     * - ระบบ multi-tenancy ที่แต่ละ tenant ใช้ DB ต่างกัน
     * - read-replica: write ไป primary, read จาก replica
     *
     * @param  Closure  $callback  logic ที่ต้องการรันใน transaction
     * @param  int  $attempts  จำนวนครั้งที่จะ retry ถ้าเกิด deadlock
     * @return mixed ค่า return จาก callback
     *
     * @throws Throwable ถ้า callback throw exception → transaction rollback
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return $this->getConnection()->transaction($callback, $attempts);
    }

    /**
     * ค้นหา record พร้อม exclusive lock (FOR UPDATE)
     *
     * ใช้ใน transaction เมื่อต้อง read → validate → write แบบ atomic
     * เช่น ตรวจสอบ stock ก่อนหักยอด, ตรวจสอบ balance ก่อนโอน
     *
     * ⚠️ ต้องอยู่ใน transaction มิฉะนั้น lock ไม่มีผล
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  eager-load relations
     */
    public function lockForUpdate(int|string $id, array $relations = []): ?Model
    {
        return $this->newQuery()
            ->with($relations)
            ->lockForUpdate()
            ->find($id);
    }

    /**
     * ค้นหา record พร้อม shared lock (LOCK IN SHARE MODE)
     *
     * หลาย transaction อ่านพร้อมกันได้ แต่ block การ write
     * ใช้เมื่อต้องการ consistent read โดยไม่ block reader คนอื่น
     *
     * ⚠️ ต้องอยู่ใน transaction มิฉะนั้น lock ไม่มีผล
     */
    public function sharedLock(int|string $id, array $relations = []): ?Model
    {
        return $this->newQuery()
            ->with($relations)
            ->sharedLock()
            ->find($id);
    }

    /**
     * เพิ่มค่า column แบบ atomic (ปลอดภัยจาก race condition)
     *
     * ใช้แทน read + compute + update เพื่อป้องกัน lost update
     * เหมาะสำหรับ: view count, like count, inventory quantity
     *
     * @param  int|string  $id  primary key value
     * @param  string  $column  ชื่อ column ที่ต้องการเพิ่มค่า
     * @param  float|int  $amount  ค่าที่เพิ่ม (default: 1)
     * @param  array  $extra  column อื่นที่ต้องการอัพเดตพร้อมกัน เช่น ['updated_by' => $userId]
     * @return int จำนวน rows ที่ถูกอัพเดต
     */
    public function increment(int|string $id, string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->newQuery()
            ->whereKey($id)
            ->increment($column, $amount, $extra);
    }

    /**
     * ลดค่า column แบบ atomic (ปลอดภัยจาก race condition)
     *
     * @see increment() สำหรับรายละเอียดเพิ่มเติม
     *
     * @param  int|string  $id  primary key value
     * @param  string  $column  ชื่อ column ที่ต้องการลดค่า
     * @param  float|int  $amount  ค่าที่ลด (default: 1)
     * @param  array  $extra  column อื่นที่ต้องการอัพเดตพร้อมกัน
     */
    public function decrement(int|string $id, string $column, int|float $amount = 1, array $extra = []): int
    {
        return $this->newQuery()
            ->whereKey($id)
            ->decrement($column, $amount, $extra);
    }
}
