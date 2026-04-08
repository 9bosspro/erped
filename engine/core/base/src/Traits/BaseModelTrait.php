<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Support\Facades\Schema;

/**
 * BaseModelTrait — ฟังก์ชันเสริมสำหรับ Eloquent Model
 *
 * ครอบคลุม: ดึง column listing, สร้าง unique UUID, และ save ที่รองรับ composite PK
 */
trait BaseModelTrait
{
    /**
     * ดึงรายการ column ทั้งหมดของ table ปัจจุบัน
     *
     * @return array<string>
     */
    public function listColumns(): array
    {
        return $this->getConnection()
            ->getSchemaBuilder()
            ->getColumnListing($this->getTable());
    }

    /**
     * ดึงรายการ column ของ table จาก connection ที่ระบุ
     *
     * @param  string  $table  ชื่อ table
     * @param  string  $db  ชื่อ database connection (default: 'mysql2')
     * @return array<string>
     */
    public function listColumnsForConnection(string $table, string $db = 'mysql2'): array
    {
        return Schema::connection($db)->getColumnListing($table);
    }

    /**
     * สร้าง UUID ใหม่ที่ไม่ซ้ำกับค่าที่มีอยู่ใน column ที่ระบุ
     *
     * วนซ้ำจนกว่าจะได้ UUID ที่ไม่ซ้ำใน DB
     * ใช้เฉพาะเมื่อจำเป็น — ควรใช้ UNIQUE constraint ของ DB แทนถ้าทำได้
     *
     * @param  string  $column  ชื่อ column ที่ต้องการตรวจสอบความซ้ำ
     * @return string UUID ที่ไม่ซ้ำ หรือ '' ถ้า $column ว่าง
     */
    public function generateUniqueUuid(string $column = ''): string
    {
        if ($column === '') {
            return '';
        }

        $hashHelper = new \Core\Base\Support\Helpers\Crypto\HashHelper;
        do {
            $uuid = $hashHelper->generateId(7, false);
        } while ($this->where($column, $uuid)->select($column)->exists());

        return $uuid;
    }

    /**
     * บันทึก Model ที่มี composite primary key (หลาย column)
     *
     * Eloquent ไม่รองรับ composite PK โดยตรง
     * method นี้เป็น workaround สำหรับ Model ที่ใช้ composite PK
     *
     * @param  array  $options  ตัวเลือกเพิ่มเติม — รองรับ 'touch' (bool, default: true)
     * @return bool true ถ้าบันทึกสำเร็จ
     */
    public function saveComposite(array $options = []): bool
    {
        if (! is_array($this->getKeyName())) {
            return parent::save($options);
        }

        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        $query = $this->newQueryWithoutScopes();

        if ($this->exists) {
            if (count($this->getDirty()) > 0) {
                if ($this->fireModelEvent('updating') === false) {
                    return false;
                }

                if ($this->timestamps) {
                    $this->updateTimestamps();
                }

                $primary = (array) $this->getKeyName();

                $unique = array_intersect_key($this->original, array_flip($primary));

                if (empty($unique)) {
                    $unique = array_intersect_key($this->getAttributes(), array_flip($primary));
                }

                $query->where($unique)->update($this->getDirty());

                $this->fireModelEvent('updated', false);
            }
        } else {
            if ($this->fireModelEvent('creating') === false) {
                return false;
            }

            if ($this->timestamps) {
                $this->updateTimestamps();
            }

            $attributes = $this->attributes;

            if ($this->incrementing && ! is_array($this->getKeyName())) {
                $this->insertAndSetId($query, $attributes);
            } else {
                $query->insert($attributes);
            }

            $this->exists = true;

            $this->fireModelEvent('created', false);
        }

        $this->fireModelEvent('saved', false);

        $this->original = $this->attributes;

        if (data_get($options, 'touch', true)) {
            $this->touchOwners();
        }

        return true;
    }
}
