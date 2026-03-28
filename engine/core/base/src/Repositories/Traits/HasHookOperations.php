<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Closure;

/**
 * Trait HasHookOperations — repository-level hooks สำหรับ before/after CRUD
 *
 * ใช้เมื่อต้องการ logic ที่ทำงานระดับ Repository:
 * - Audit logging (บันทึกว่าใครทำอะไร)
 * - Cache invalidation หลัง write
 * - Business rule validation ก่อน write
 *
 * ต่างจาก Eloquent Observer: hooks นี้ทำงานระดับ Repository ไม่ใช่ Model
 * เหมาะสำหรับ logic ที่ต้องการ context ของ Repository (เช่น user ที่ login อยู่)
 *
 * ⚠️ Hooks เป็น stateful — ถ้าใช้กับ long-running process (Queue worker, Laravel Octane)
 * ควร resetHooks() หลังใช้งานในแต่ละ request เพื่อป้องกัน memory leak
 *
 * ตัวอย่าง:
 * ```php
 * $repo->beforeCreate(function (&$payload) {
 *     $payload['created_by'] = auth()->id();
 * })->afterCreate(function ($model) {
 *     cache()->forget("user:{$model->id}");
 * });
 * ```
 */
trait HasHookOperations
{
    /** @var array<string, Closure[]> registry ของ hooks แต่ละ event */
    protected array $hooks = [
        'beforeCreate' => [],
        'afterCreate' => [],
        'beforeUpdate' => [],
        'afterUpdate' => [],
        'beforeDelete' => [],
        'afterDelete' => [],
    ];

    /**
     * ลงทะเบียน hook ก่อน create
     * Callback รับ &$payload (reference — แก้ไข payload ได้ก่อน save)
     */
    public function beforeCreate(Closure $callback): static
    {
        $this->hooks['beforeCreate'][] = $callback;

        return $this;
    }

    /**
     * ลงทะเบียน hook หลัง create
     * Callback รับ $model ที่สร้างแล้ว (พร้อม ID)
     */
    public function afterCreate(Closure $callback): static
    {
        $this->hooks['afterCreate'][] = $callback;

        return $this;
    }

    /**
     * ลงทะเบียน hook ก่อน update
     * Callback รับ &$payload (reference — แก้ไข payload ได้ก่อน update)
     */
    public function beforeUpdate(Closure $callback): static
    {
        $this->hooks['beforeUpdate'][] = $callback;

        return $this;
    }

    /**
     * ลงทะเบียน hook หลัง update
     * Callback รับ $payload ที่ถูก update
     */
    public function afterUpdate(Closure $callback): static
    {
        $this->hooks['afterUpdate'][] = $callback;

        return $this;
    }

    /**
     * ลงทะเบียน hook ก่อน delete
     * Callback รับ $id (primary key value)
     */
    public function beforeDelete(Closure $callback): static
    {
        $this->hooks['beforeDelete'][] = $callback;

        return $this;
    }

    /**
     * ลงทะเบียน hook หลัง delete
     * Callback รับ $model ที่ถูกลบแล้ว
     */
    public function afterDelete(Closure $callback): static
    {
        $this->hooks['afterDelete'][] = $callback;

        return $this;
    }

    /**
     * ล้าง hooks ทั้งหมด — ใช้ป้องกัน memory leak ใน long-running processes
     */
    public function resetHooks(): static
    {
        foreach (array_keys($this->hooks) as $event) {
            $this->hooks[$event] = [];
        }

        return $this;
    }

    /**
     * เรียก hooks ที่ลงทะเบียนไว้สำหรับ event นั้น
     *
     * ถูกเรียกจาก HasWriteOperations::runHooks()
     * Data ส่งผ่าน reference เพื่อให้ hook แก้ไขได้ (เช่น beforeCreate payload)
     *
     * @param  string  $event  ชื่อ event: beforeCreate, afterCreate, etc.
     * @param  mixed  $data  ข้อมูลที่ส่งเข้า hook (pass by reference)
     */
    protected function fireHook(string $event, mixed &$data = null): void
    {
        if (empty($this->hooks[$event])) {
            return;
        }

        foreach ($this->hooks[$event] as $callback) {
            $callback($data);
        }
    }
}
