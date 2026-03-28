<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;

/**
 * Queryable Interface — สำหรับ query ขั้นสูงและ aggregate functions
 */
interface QueryableInterface
{
    /**
     * Query ด้วย Closure แบบยืดหยุ่น — ใช้เมื่อ findWhere() ไม่เพียงพอ
     *
     * @param  Closure(Builder): void  $queryCallback  callback ที่รับ Builder
     * @param  array<string>  $relations  eager-load relations
     * @return Collection<int, Model>
     */
    public function query(Closure $queryCallback, array $relations = []): Collection;

    /**
     * ค้นหา record แรกจาก Closure query
     *
     * @param  Closure(Builder): void  $queryCallback  callback ที่รับ Builder
     * @param  array<string>  $relations  eager-load relations
     */
    public function firstBy(Closure $queryCallback, array $relations = []): ?Model;

    /**
     * ค้นหาหลาย records จากเงื่อนไข array พร้อมเรียงลำดับ
     *
     * @param  array<string, mixed>  $where  เงื่อนไข where
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return Collection<int, Model>
     */
    public function findWhere(array $where, array $relations = [], array $orderBy = []): Collection;

    /**
     * ตรวจสอบว่ามี record ที่ตรงเงื่อนไขหรือไม่
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม (optional)
     */
    public function exists(?Closure $queryCallback = null): bool;

    /**
     * นับจำนวน records ที่ตรงเงื่อนไข
     *
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม (optional)
     */
    public function count(?Closure $queryCallback = null): int;

    /**
     * หาค่าสูงสุดของ column
     */
    public function max(string $column, ?Closure $queryCallback = null): mixed;

    /**
     * หาค่าต่ำสุดของ column
     */
    public function min(string $column, ?Closure $queryCallback = null): mixed;

    /**
     * หาผลรวมของ column
     */
    public function sum(string $column, ?Closure $queryCallback = null): float|int;

    /**
     * หาค่าเฉลี่ยของ column
     */
    public function avg(string $column, ?Closure $queryCallback = null): ?float;

    /**
     * ดึงค่า column เดียวเป็น Collection (เหมือน SELECT column FROM ...)
     *
     * @param  string  $column  ชื่อ column ที่ต้องการ
     * @param  string|null  $key  column ที่ใช้เป็น key ของ Collection
     */
    public function pluck(string $column, ?string $key = null, ?Closure $queryCallback = null): SupportCollection;

    /**
     * ดึงค่า column เดียวของ record แรกที่ตรงเงื่อนไข
     *
     * เร็วกว่า first()->column เพราะ SELECT เฉพาะ column นั้น
     *
     * @param  string  $column  ชื่อ column ที่ต้องการ
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     * @return mixed ค่าของ column หรือ null ถ้าไม่พบ record
     */
    public function value(string $column, ?Closure $queryCallback = null): mixed;
}
