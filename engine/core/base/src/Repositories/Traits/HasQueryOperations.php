<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Trait HasQueryOperations — query ขั้นสูงและ aggregate functions
 *
 * Aggregate methods (max, min, sum, avg) ใช้ private helper เพื่อลด boilerplate
 */
trait HasQueryOperations
{
    /**
     * Query ด้วย Closure แบบยืดหยุ่น
     *
     * ใช้เมื่อ findWhere() ไม่เพียงพอ เช่น ต้องการ orWhere, join, subquery
     *
     * @param  Closure(Builder): void  $queryCallback  callback ที่รับ Builder
     * @param  array<string>  $relations  eager-load relations
     * @return Collection<int, Model>
     */
    public function query(Closure $queryCallback, array $relations = []): Collection
    {
        $query = $this->newQuery()->with($relations);
        $queryCallback($query);

        return $query->get();
    }

    /**
     * ค้นหา record แรกจาก Closure query (คืน null ถ้าไม่พบ)
     *
     * @param  Closure(Builder): void  $queryCallback  callback ที่รับ Builder
     * @param  array<string>  $relations  eager-load relations
     */
    public function firstBy(Closure $queryCallback, array $relations = []): ?Model
    {
        $query = $this->newQuery()->with($relations);
        $queryCallback($query);

        return $query->first();
    }

    /**
     * ค้นหาหลาย records จาก array conditions พร้อมเรียงลำดับ
     *
     * @param  array<string, mixed>  $where  เงื่อนไข where (AND)
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return Collection<int, Model>
     */
    public function findWhere(array $where, array $relations = [], array $orderBy = []): Collection
    {
        $query = $this->newQuery()
            ->with($relations)
            ->where($where);

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->get();
    }

    /**
     * ตรวจสอบว่ามี record ที่ตรงเงื่อนไขหรือไม่
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม (optional)
     */
    public function exists(?Closure $queryCallback = null): bool
    {
        return $this->applyOptionalQuery($queryCallback)->exists();
    }

    /**
     * นับจำนวน records ที่ตรงเงื่อนไข
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม (optional)
     */
    public function count(?Closure $queryCallback = null): int
    {
        return $this->applyOptionalQuery($queryCallback)->count();
    }

    /**
     * หาค่าสูงสุดของ column
     *
     * @param  string  $column  ชื่อ column
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function max(string $column, ?Closure $queryCallback = null): mixed
    {
        return $this->applyOptionalQuery($queryCallback)->max($column);
    }

    /**
     * หาค่าต่ำสุดของ column
     *
     * @param  string  $column  ชื่อ column
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function min(string $column, ?Closure $queryCallback = null): mixed
    {
        return $this->applyOptionalQuery($queryCallback)->min($column);
    }

    /**
     * หาผลรวมของ column
     *
     * @param  string  $column  ชื่อ column
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function sum(string $column, ?Closure $queryCallback = null): float|int
    {
        return $this->applyOptionalQuery($queryCallback)->sum($column);
    }

    /**
     * หาค่าเฉลี่ยของ column (คืน null ถ้าไม่มีข้อมูล)
     *
     * @param  string  $column  ชื่อ column
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function avg(string $column, ?Closure $queryCallback = null): ?float
    {
        return $this->applyOptionalQuery($queryCallback)->avg($column);
    }

    /**
     * ดึงค่าของ column เดียวเป็น Collection (เช่น list of IDs, emails)
     *
     * @param  string  $column  ชื่อ column ที่ต้องการ
     * @param  string|null  $key  column ที่ใช้เป็น key ของ Collection
     * @param  Closure|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function pluck(string $column, ?string $key = null, ?Closure $queryCallback = null): SupportCollection
    {
        return $this->applyOptionalQuery($queryCallback)->pluck($column, $key);
    }

    /**
     * ดึงค่า column เดียวของ record แรกที่ตรงเงื่อนไข
     *
     * เร็วกว่า first()->column เพราะ SELECT เฉพาะ column นั้น
     * ใช้เมื่อต้องการค่าเดียวเช่น email, status, last_login_at
     *
     * @param  string  $column  ชื่อ column ที่ต้องการ
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     * @return mixed ค่าของ column หรือ null ถ้าไม่พบ record
     */
    public function value(string $column, ?Closure $queryCallback = null): mixed
    {
        return $this->applyOptionalQuery($queryCallback)->value($column);
    }

    /**
     * ประมวลผล records เป็น chunks โดยใช้ primary key (แนะนำกว่า chunk ทั่วไป)
     *
     * ปลอดภัยกว่า chunk() เพราะใช้ WHERE id > :last_id แทน OFFSET
     * ป้องกัน offset drift เมื่อมีการ insert/delete ระหว่าง iteration
     *
     * ⚠️ ต้องการ ORDER BY บน $column (default: 'id') — ควรมี index
     *
     * @param  int  $size  ขนาด chunk ต่อรอบ
     * @param  Closure  $callback  callback รับ Collection<Model>
     * @param  string  $column  column ที่ใช้ chunk (ต้องมี index)
     * @param  array<string, mixed>  $where  เงื่อนไขเพิ่มเติม
     */
    public function chunkById(
        int $size,
        Closure $callback,
        string $column = 'id',
        array $where = [],
    ): bool {
        $query = $this->newQuery();

        if (! empty($where)) {
            $query->where($where);
        }

        return $query->chunkById($size, $callback, $column);
    }

    /**
     * Helper — สร้าง query ใหม่แล้ว apply Closure ถ้ามี
     *
     * ใช้ลด boilerplate ที่ซ้ำกันใน aggregate methods ทุกตัว
     * (max, min, sum, avg, count, exists, pluck ล้วนใช้ pattern เดิม)
     *
     * @param  Closure(Builder): void|null  $queryCallback  optional callback
     */
    private function applyOptionalQuery(?Closure $queryCallback): Builder
    {
        $query = $this->newQuery();

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        return $query;
    }
}
