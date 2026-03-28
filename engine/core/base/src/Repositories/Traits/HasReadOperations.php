<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * Trait HasReadOperations — read operations พื้นฐาน
 *
 * ทุก method เรียก newQuery() เพื่อสร้าง Builder ใหม่ทุกครั้ง (stateless)
 *
 * เลือก method ที่เหมาะสมตามขนาดข้อมูล:
 * - all()    → ข้อมูลน้อย (< 1,000 rows) ต้องการทั้งหมดใน memory
 * - lazy()   → ข้อมูลมาก (> 10,000 rows) ดึงเป็น chunk แต่ใช้งานเหมือน Collection
 * - cursor() → ข้อมูลมากมาย ใช้ DB server-side cursor ประหยัด memory สุด
 */
trait HasReadOperations
{
    /**
     * ดึง records ทั้งหมด พร้อม eager-load relations และเรียงลำดับ
     *
     * ⚠️ ไม่เหมาะกับตารางขนาดใหญ่ — ใช้ lazy() หรือ cursor() แทน
     *
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     * @param  array<string, string>  $orderBy  คู่ column => direction เช่น ['name' => 'asc']
     * @return Collection<int, Model>
     */
    public function all(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection
    {
        $query = $this->newQuery()->with($relations);

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get();
    }

    /**
     * ค้นหา record จาก primary key (คืน null ถ้าไม่พบ)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     */
    public function find(int|string $id, array $relations = []): ?Model
    {
        return $this->newQuery()->with($relations)->find($id);
    }

    /**
     * ค้นหา record จาก primary key
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException ถ้าไม่พบ
     */
    public function findOrFail(int|string $id, array $relations = []): Model
    {
        return $this->newQuery()->with($relations)->findOrFail($id);
    }

    /**
     * ค้นหาหลาย records จากรายการ primary keys
     *
     * @param  array<int, int|string>  $ids  รายการ primary key values
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     * @return Collection<int, Model>
     */
    public function findMany(array $ids, array $relations = []): Collection
    {
        if (empty($ids)) {
            return new Collection;
        }

        return $this->newQuery()->with($relations)->findMany($ids);
    }

    /**
     * ค้นหา record แรกที่ตรงเงื่อนไข (คืน null ถ้าไม่พบ)
     *
     * @param  array<string, mixed>  $where  เงื่อนไข เช่น ['email' => 'test@mail.com']
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     */
    public function firstWhere(array $where, array $relations = []): ?Model
    {
        return $this->newQuery()
            ->with($relations)
            ->where($where)
            ->first();
    }

    /**
     * ค้นหา record แรกที่ตรงเงื่อนไข — throw ถ้าไม่พบ
     *
     * @param  array<string, mixed>  $where  เงื่อนไข
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException ถ้าไม่พบ
     */
    public function firstWhereOrFail(array $where, array $relations = []): Model
    {
        return $this->newQuery()
            ->with($relations)
            ->where($where)
            ->firstOrFail();
    }

    /**
     * ดึง records แบบ DB cursor — ใช้ server-side cursor ประหยัด memory สุด
     *
     * memory คงที่ไม่ว่าข้อมูลจะมีกี่ล้านแถว เหมาะสำหรับ export หรือ dispatch jobs
     *
     * ⚠️ ไม่รองรับ eager-load relations (N+1 ถ้าเข้า relation ใน loop)
     *    ถ้าต้องการ relations ให้ใช้ lazy() จาก HasPaginationOperations แทน
     *
     * ตัวอย่าง:
     * ```php
     * foreach ($repo->cursor(where: ['active' => true]) as $record) {
     *     ProcessRecordJob::dispatch($record->id);
     * }
     * ```
     *
     * @param  array<string, mixed>  $where  เงื่อนไข where
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return LazyCollection<int, Model>
     */
    public function cursor(
        array $where = [],
        array $orderBy = ['id' => 'asc'],
    ): LazyCollection {
        $query = $this->newQuery();

        if (! empty($where)) {
            $query->where($where);
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->cursor();
    }
}
