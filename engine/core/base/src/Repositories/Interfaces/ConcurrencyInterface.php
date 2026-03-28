<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Concurrency Interface — transaction, locking, atomic operations
 *
 * ทุก method ใช้ database connection ของ Model (ไม่ใช่ default)
 * เพื่อรองรับระบบ multi-database / multi-tenancy
 */
interface ConcurrencyInterface
{
    /**
     * ครอบ callback ด้วย database transaction
     *
     * @param  Closure  $callback  logic ที่ต้องการรันใน transaction
     * @param  int  $attempts  จำนวนครั้งที่จะ retry ถ้าเกิด deadlock
     * @return mixed ค่า return จาก callback
     *
     * @throws Throwable ถ้า callback throw exception — transaction จะ rollback
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed;

    /**
     * ค้นหา record พร้อม lock for update (exclusive lock)
     *
     * ใช้ใน transaction เมื่อต้อง read-then-write แบบ atomic
     * เช่น ตรวจสอบยอดเงินก่อนหักลบ
     */
    public function lockForUpdate(int|string $id, array $relations = []): ?Model;

    /**
     * ค้นหา record พร้อม shared lock (อ่านได้หลาย transaction พร้อมกัน แต่เขียนไม่ได้)
     */
    public function sharedLock(int|string $id, array $relations = []): ?Model;

    /**
     * เพิ่มค่า column แบบ atomic (ไม่ต้อง read + write แยก)
     *
     * ปลอดภัยจาก race condition — เช่น เพิ่มจำนวนวิว, อัพเดตยอดขาย
     *
     * @param  int|string  $id  primary key value
     * @param  string  $column  ชื่อ column ที่ต้องการเพิ่ม
     * @param  float|int  $amount  จำนวนที่เพิ่ม
     * @param  array  $extra  column อื่นที่ต้องการอัพเดตพร้อมกัน
     * @return int จำนวน rows ที่ถูกอัพเดต
     */
    public function increment(int|string $id, string $column, int|float $amount = 1, array $extra = []): int;

    /**
     * ลดค่า column แบบ atomic
     *
     * @see increment() — ทำงานเหมือนกันแต่ลดค่าแทน
     */
    public function decrement(int|string $id, string $column, int|float $amount = 1, array $extra = []): int;
}
