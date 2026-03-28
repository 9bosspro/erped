<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Readable Interface — สำหรับ read operations พื้นฐาน
 */
interface ReadableInterface
{
    /**
     * ดึง record ทั้งหมด พร้อม eager-load relations และเรียงลำดับ
     *
     * ⚠️ ระวังใช้กับตารางขนาดใหญ่ — ควรใช้ paginate() แทน
     *
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     * @param  array<string, string>  $orderBy  คู่ column => direction เช่น ['name' => 'asc']
     * @return Collection<int, Model>
     */
    public function all(array $relations = [], array $orderBy = ['created_at' => 'desc']): Collection;

    /**
     * ค้นหา record จาก primary key (คืน null ถ้าไม่พบ)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     */
    public function find(int|string $id, array $relations = []): ?Model;

    /**
     * ค้นหา record จาก primary key (throw ModelNotFoundException ถ้าไม่พบ)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int|string $id, array $relations = []): Model;

    /**
     * ค้นหาหลาย records จากรายการ primary keys
     *
     * @param  array<int, int|string>  $ids  รายการ primary key values
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     * @return Collection<int, Model>
     */
    public function findMany(array $ids, array $relations = []): Collection;

    /**
     * ค้นหา record แรกที่ตรงเงื่อนไข (คืน null ถ้าไม่พบ)
     *
     * @param  array<string, mixed>  $where  เงื่อนไข เช่น ['email' => 'test@mail.com']
     * @param  array<string>  $relations  ชื่อ relation ที่ต้องการ eager-load
     */
    public function firstWhere(array $where, array $relations = []): ?Model;
}
