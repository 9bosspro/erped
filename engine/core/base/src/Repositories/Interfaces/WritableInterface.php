<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Writable Interface — สำหรับ write operations (create, update, delete)
 */
interface WritableInterface
{
    /**
     * สร้าง record ใหม่
     *
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการบันทึก
     * @param  bool  $fillGuarded  true = ข้าม $guarded ใน Model (ใช้ forceFill)
     */
    public function create(array $payload, bool $fillGuarded = false): Model;

    /**
     * สร้างหลาย records พร้อมกัน (atomic — wrap ด้วย transaction)
     *
     * @param  array<int, array<string, mixed>>  $records  รายการข้อมูล
     * @param  bool  $returnModels  true = คืน Collection ของ Model, false = bulk insert
     * @return bool|Collection<int, Model> Collection ถ้า returnModels=true, bool ถ้า false
     */
    public function createMany(array $records, bool $returnModels = true): Collection|bool;

    /**
     * อัพเดต record จาก primary key
     *
     * @param  int|string  $id  primary key value
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการอัพเดต
     * @param  bool  $skipEvents  true = ข้าม Model events (ใช้ query update โดยตรง)
     */
    public function update(int|string $id, array $payload, bool $skipEvents = false): bool;

    /**
     * อัพเดตหลาย records ที่ตรงเงื่อนไข
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการอัพเดต
     * @param  bool  $skipEvents  true = ข้าม Model events
     * @return int จำนวน records ที่ถูกอัพเดต
     */
    public function updateWhere(array $conditions, array $payload, bool $skipEvents = false): int;

    /**
     * สร้างหรืออัพเดต record (upsert ระดับ single record)
     *
     * ค้นหาจาก $attributes — ถ้าพบจะ merge $values แล้ว update, ถ้าไม่พบจะ create
     *
     * @param  array<string, mixed>  $attributes  เงื่อนไขค้นหา เช่น ['email' => '...']
     * @param  array<string, mixed>  $values  ข้อมูลที่ต้องการอัพเดต/สร้างเพิ่ม
     */
    public function updateOrCreate(array $attributes, array $values = []): Model;

    /**
     * ลบ record จาก primary key
     *
     * @param  int|string  $id  primary key value
     * @param  bool  $force  true = force delete (ข้าม soft delete)
     */
    public function delete(int|string $id, bool $force = false): bool;

    /**
     * ลบหลาย records ที่ตรงเงื่อนไข
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @param  bool  $force  true = force delete
     * @return int จำนวน records ที่ถูกลบ
     */
    public function deleteWhere(array $conditions, bool $force = false): int;

    /**
     * Bulk upsert — สร้างหรืออัพเดตหลาย records พร้อมกันด้วย single query
     *
     * ไม่ trigger Model events — ใช้สำหรับ batch operations ขนาดใหญ่
     *
     * @param  array<int, array<string, mixed>>  $records  รายการข้อมูล
     * @param  array<int, string>|string  $uniqueBy  column(s) ที่ใช้ตรวจ unique constraint
     * @param  array<int, string>|null  $update  columns ที่ update เมื่อ record มีอยู่แล้ว
     *                                           null = update ทุก column ยกเว้น uniqueBy
     * @return int จำนวน rows ที่ affected
     */
    public function upsertMany(array $records, array|string $uniqueBy, ?array $update = null): int;
}
