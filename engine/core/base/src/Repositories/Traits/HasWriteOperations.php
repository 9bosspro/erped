<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasWriteOperations — จัดการ create, update, delete
 *
 * Features:
 * - สนับสนุน Model events (create/update ผ่าน Model instance)
 * - skipEvents mode สำหรับ bulk operations ที่ต้องการ performance
 * - Hook integration กับ HasHookOperations (ถ้า use อยู่)
 * - Transaction-safe createMany
 *
 * Hook Convention (consistent ทุก lifecycle):
 * - beforeCreate($payload)  — รับ array payload ก่อน save (mutation ได้)
 * - afterCreate($model)     — รับ Model instance หลัง save สำเร็จ
 * - beforeUpdate($payload)  — รับ array payload ก่อน update (mutation ได้)
 * - afterUpdate($model)     — รับ Model instance หลัง update สำเร็จ
 * - beforeDelete($id)       — รับ primary key ก่อนลบ
 * - afterDelete($model)     — รับ Model instance หลังลบสำเร็จ
 */
trait HasWriteOperations
{
    /**
     * สร้าง record ใหม่ผ่าน Model instance (trigger Model events)
     *
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการบันทึก
     * @param  bool  $fillGuarded  true = ใช้ forceFill (ข้าม $guarded)
     *                             ⚠️ ใช้เฉพาะกรณีที่ validate แล้ว เช่น seeder, migration
     */
    public function create(array $payload, bool $fillGuarded = false): Model
    {
        $this->runHooks('beforeCreate', $payload);

        $model = $this->model->newInstance();

        $fillGuarded
            ? $model->forceFill($payload)->save()
            : $model->fill($payload)->save();

        $this->runHooks('afterCreate', $model);

        return $model;
    }

    /**
     * สร้างหลาย records — atomic (wrap ด้วย transaction)
     *
     * - returnModels=true  → สร้างทีละตัวผ่าน create() (trigger events + hooks)
     *                         wrap ด้วย transaction — ถ้า record ไหน fail จะ rollback ทั้งหมด
     * - returnModels=false → bulk insert ด้วย query builder (เร็วมาก แต่ไม่ trigger events)
     *
     * @param  array<int, array<string, mixed>>  $records  รายการข้อมูล
     * @param  bool  $returnModels  true = คืน Collection, false = bulk insert
     * @return bool|Collection<int, Model>
     */
    public function createMany(array $records, bool $returnModels = true): Collection|bool
    {
        if (! $returnModels) {
            return $this->newQuery()->insert($records);
        }

        // Wrap ด้วย transaction — ถ้า record ที่ N fail จะ rollback 1 ถึง N-1
        return $this->getConnection()->transaction(
            fn () => (new Collection($records))->map(fn ($record) => $this->create($record)),
        );
    }

    /**
     * อัพเดต record จาก primary key
     *
     * - skipEvents=false (default) → ดึง Model มา update (trigger events + hooks ได้ Model จริง)
     * - skipEvents=true            → update ผ่าน query builder โดยตรง (เร็วกว่า ไม่ trigger events)
     *                                ⚠️ afterUpdate hook จะได้รับ stub Model (มีเฉพาะ id)
     *
     * @param  int|string  $id  primary key value
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการอัพเดต
     * @param  bool  $skipEvents  true = ข้าม Model events
     */
    public function update(int|string $id, array $payload, bool $skipEvents = false): bool
    {
        $this->runHooks('beforeUpdate', $payload);

        if ($skipEvents) {
            $affected = $this->newQuery()->whereKey($id)->update($payload);

            // afterUpdate hook ต้องได้ Model — ส่ง stub (มี key เท่านั้น)
            // ⚠️ hook listener ที่ต้องการ fresh attributes ควรใช้ skipEvents=false
            $stub = $this->model->newInstance()->forceFill([$this->model->getKeyName() => $id]);
            $this->runHooks('afterUpdate', $stub);

            return (bool) $affected;
        }

        $model = $this->findOrFail($id);
        $result = $model->update($payload);

        // ✅ ส่ง $model instance (consistent กับ afterCreate/afterDelete)
        $this->runHooks('afterUpdate', $model);

        return $result;
    }

    /**
     * อัพเดตหลาย records ที่ตรงเงื่อนไข
     *
     * - skipEvents=false → ดึงทุก record มา update ทีละตัว (trigger events + hooks)
     *   ⚠️ โหลดทุก record เข้า memory — ใช้ skipEvents=true สำหรับ bulk ขนาดใหญ่
     * - skipEvents=true  → update ผ่าน query builder โดยตรง (เร็ว ประหยัด memory)
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @param  array<string, mixed>  $payload  ข้อมูลที่ต้องการอัพเดต
     * @param  bool  $skipEvents  true = ข้าม Model events
     * @return int จำนวน records ที่ถูกอัพเดต
     */
    public function updateWhere(array $conditions, array $payload, bool $skipEvents = false): int
    {
        if ($skipEvents) {
            return $this->newQuery()->where($conditions)->update($payload);
        }

        $models = $this->newQuery()->where($conditions)->get();
        $count = 0;

        foreach ($models as $model) {
            $this->runHooks('beforeUpdate', $payload);

            if ($model->update($payload)) {
                $this->runHooks('afterUpdate', $model);
                $count++;
            }
        }

        return $count;
    }

    /**
     * สร้างหรืออัพเดต record (upsert ระดับ single record)
     *
     * ค้นหาจาก $attributes — ถ้าพบจะ merge $values แล้ว update
     * ถ้าไม่พบจะสร้างใหม่ด้วย array_merge($attributes, $values)
     *
     * ใช้ Eloquent updateOrCreate — trigger Model events ทั้ง creating/updating
     *
     * @param  array<string, mixed>  $attributes  เงื่อนไขค้นหา เช่น ['email' => '...']
     * @param  array<string, mixed>  $values  ข้อมูลที่ต้องการอัพเดต/เพิ่ม
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->newQuery()->updateOrCreate($attributes, $values);
    }

    /**
     * ลบ record จาก primary key
     *
     * @param  int|string  $id  primary key value
     * @param  bool  $force  true = force delete (ข้าม soft delete)
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException ถ้าไม่พบ record
     */
    public function delete(int|string $id, bool $force = false): bool
    {
        $this->runHooks('beforeDelete', $id);

        $model = $this->findOrFail($id);
        $result = $force ? $model->forceDelete() : $model->delete();

        $this->runHooks('afterDelete', $model);

        return (bool) $result;
    }

    /**
     * ลบหลาย records ที่ตรงเงื่อนไข
     *
     * ลบทีละ Model instance เพื่อ trigger events (deleting, deleted, etc.)
     * ⚠️ โหลดทุก record เข้า memory — สำหรับ bulk delete ขนาดใหญ่ใช้ query builder โดยตรง
     *
     * @param  array<string, mixed>  $conditions  เงื่อนไข where
     * @param  bool  $force  true = force delete
     * @return int จำนวน records ที่ถูกลบ
     */
    public function deleteWhere(array $conditions, bool $force = false): int
    {
        $models = $this->newQuery()->where($conditions)->get();
        $count = 0;

        foreach ($models as $model) {
            $result = $force ? $model->forceDelete() : $model->delete();

            if ($result) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Bulk upsert — สร้างหรืออัพเดตหลาย records พร้อมกันด้วย single query
     *
     * ใช้ Eloquent Builder::upsert() (MySQL INSERT ... ON DUPLICATE KEY UPDATE)
     * เร็วมากสำหรับ batch operations — ไม่ trigger Model events
     *
     * ตัวอย่าง:
     * ```php
     * // sync stock หลายรายการพร้อมกัน
     * $repo->upsertMany(
     *     records:  [['sku' => 'A001', 'qty' => 10], ['sku' => 'A002', 'qty' => 5]],
     *     uniqueBy: ['sku'],
     *     update:   ['qty', 'updated_at'],
     * );
     * ```
     *
     * @param  array<int, array<string, mixed>>  $records  รายการข้อมูล
     * @param  array<int, string>|string  $uniqueBy  column(s) ที่ใช้ตรวจ unique constraint
     * @param  array<int, string>|null  $update  column ที่ต้องการ update (null = ทุก column)
     * @return int จำนวน rows ที่ affected (insert + update)
     */
    public function upsertMany(array $records, array|string $uniqueBy, ?array $update = null): int
    {
        if (empty($records)) {
            return 0;
        }

        $uniqueBy = (array) $uniqueBy;

        return $this->newQuery()->upsert(
            $records,
            $uniqueBy,
            $update ?? array_keys(array_diff_key($records[0], array_flip($uniqueBy))),
        );
    }

    /**
     * เรียก repository hooks ถ้ามี HasHookOperations trait
     *
     * ใช้ method_exists เพื่อ decouple — ทำงานได้แม้ไม่ได้ use HasHookOperations
     * $data เป็น reference เพื่อให้ before-hooks (beforeCreate, beforeUpdate) สามารถ mutate payload ได้
     */
    protected function runHooks(string $event, mixed &$data = null): void
    {
        if (method_exists($this, 'fireHook')) {
            $this->fireHook($event, $data);
        }
    }
}
