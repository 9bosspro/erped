<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * Trait HasPaginationOperations — แบ่งหน้าและ batch processing
 *
 * เลือก pagination strategy ตาม use case:
 * - paginate()       → UI ที่ต้องแสดงจำนวนหน้า (total count required)
 * - simplePaginate() → infinite scroll / load more (เร็วกว่า paginate)
 * - cursorPaginate() → real-time feed, large dataset (เร็วที่สุด)
 * - lazy()           → export, migration (ประหยัด memory)
 * - chunk()          → batch update/delete ทุก record
 */
trait HasPaginationOperations
{
    /**
     * แบ่งหน้าแบบมี total count
     *
     * รันสอง queries: SELECT + COUNT(*) → เหมาะสำหรับ UI ที่ต้องแสดงจำนวนหน้า
     *
     * @param  int  $perPage  จำนวนต่อหน้า (default: 15)
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['created_at' => 'desc'],
    ): LengthAwarePaginator {
        $query = $this->newQuery()->with($relations);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->paginate($perPage);
    }

    /**
     * แบ่งหน้าแบบ simple (ไม่นับ total)
     *
     * รัน query เดียว: SELECT LIMIT n+1 → เร็วกว่า paginate()
     * เหมาะสำหรับ "Load More" button, infinite scroll
     *
     * @return Paginator<int, Model>
     */
    public function simplePaginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['created_at' => 'desc'],
    ): Paginator {
        $query = $this->newQuery()->with($relations);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        foreach ($orderBy as $column => $direction) {
            $query->orderBy($column, $direction);
        }

        return $query->simplePaginate($perPage);
    }

    /**
     * แบ่งหน้าแบบ cursor-based (เร็วที่สุด สำหรับ large dataset)
     *
     * ใช้ cursor แทน OFFSET → ไม่ช้าลงเมื่อ page ลึกขึ้น
     * ⚠️ ต้องมี index บน cursorColumn เพื่อ performance
     * ⚠️ ไม่รองรับ jump to page — เหมาะกับ feed/timeline เท่านั้น
     *
     * @param  string  $cursorColumn  column ที่ใช้เป็น cursor (ต้อง unique + ordered)
     * @param  string  $direction  ทิศทางเรียง 'asc' หรือ 'desc' (default: 'desc' → ข้อมูลใหม่ก่อน)
     * @return CursorPaginator<int, Model>
     */
    public function cursorPaginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        string $cursorColumn = 'created_at',
        string $direction = 'desc',
    ): CursorPaginator {
        $query = $this->newQuery()
            ->with($relations)
            ->orderBy($cursorColumn, $direction);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        return $query->cursorPaginate($perPage);
    }

    /**
     * ดึงข้อมูลแบบ lazy — โหลดทีละ chunk เข้า memory
     *
     * ใช้สำหรับ export CSV, data migration, batch report
     * ไม่โหลดทุก record เข้า memory พร้อมกัน (ประหยัด RAM มาก)
     *
     * @param  int  $chunkSize  จำนวน records ต่อ chunk (default: 1000)
     * @return LazyCollection<int, Model> iterable ที่สามารถ foreach ได้
     */
    public function lazy(int $chunkSize = 1000, ?Closure $queryCallback = null, array $relations = []): LazyCollection
    {
        $query = $this->newQuery()->with($relations);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        return $query->lazy($chunkSize);
    }

    /**
     * ประมวลผลทีละ chunk — เรียก callback ทุก chunk
     *
     * เหมาะสำหรับ batch update/delete ที่ต้องการ side-effects ต่อทุก record
     * คืน false จาก callback เพื่อหยุด chunking ก่อนครบ
     *
     * @param  int  $count  จำนวน records ต่อ chunk
     * @param  Closure(Collection<int, Model>): mixed  $callback  callback ที่รับ Collection ของ chunk
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function chunk(int $count, Closure $callback, ?Closure $queryCallback = null, array $relations = []): bool
    {
        $query = $this->newQuery()->with($relations);

        if ($queryCallback !== null) {
            $queryCallback($query);
        }

        return $query->chunk($count, $callback);
    }
}
