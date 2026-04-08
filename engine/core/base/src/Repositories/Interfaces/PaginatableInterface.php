<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\LazyCollection;

/**
 * Paginatable Interface — สำหรับแบ่งหน้าและ batch processing
 */
interface PaginatableInterface
{
    /**
     * แบ่งหน้าแบบมี total count (ใช้สำหรับ UI ที่ต้องแสดงจำนวนหน้า)
     *
     * @param  int  $perPage  จำนวนต่อหน้า
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     * @param  array<string>  $relations  eager-load relations
     * @param  array<string, string>  $orderBy  คู่ column => direction
     */
    public function paginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['created_at' => 'desc'],
    ): LengthAwarePaginator;

    /**
     * แบ่งหน้าแบบ simple (ไม่นับ total — เร็วกว่า paginate)
     *
     * เหมาะสำหรับ infinite scroll หรือ "Load more" UI
     */
    public function simplePaginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        array $orderBy = ['created_at' => 'desc'],
    ): Paginator;

    /**
     * แบ่งหน้าแบบ cursor-based (เร็วที่สุด สำหรับข้อมูลขนาดใหญ่)
     *
     * ⚠️ ต้องมี index บน cursorColumn เพื่อ performance ที่ดี
     *
     * @param  string  $cursorColumn  column ที่ใช้เป็น cursor (ต้อง unique + ordered)
     * @param  string  $direction  ทิศทางเรียง 'asc' หรือ 'desc'
     */
    public function cursorPaginate(
        int $perPage = 15,
        ?Closure $queryCallback = null,
        array $relations = [],
        string $cursorColumn = 'created_at',
        string $direction = 'desc',
    ): CursorPaginator;

    /**
     * ดึงข้อมูลแบบ lazy — โหลดทีละ chunk เข้า memory (ประหยัด RAM)
     *
     * ใช้สำหรับ export, data migration, batch processing
     *
     * @param  int  $chunkSize  จำนวน records ต่อ chunk
     * @return LazyCollection iterable ที่โหลดทีละ chunk
     */
    public function lazy(int $chunkSize = 1000, ?Closure $queryCallback = null, array $relations = []): LazyCollection;

    /**
     * ประมวลผลทีละ chunk — เรียก callback ทุก chunk
     *
     * ใช้สำหรับ batch update/delete ที่ต้อง process ทุก record
     * คืน false จาก callback เพื่อหยุด chunking
     *
     * @param  int  $count  จำนวน records ต่อ chunk
     * @param  Closure(Collection): mixed  $callback  callback ที่รับ Collection ของ chunk
     * @param  Closure(Builder): void|null  $queryCallback  เงื่อนไขเพิ่มเติม
     */
    public function chunk(int $count, Closure $callback, ?Closure $queryCallback = null, array $relations = []): bool;
}
