<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;

/**
 * Hookable Interface — repository-level hooks สำหรับ before/after CRUD
 *
 * ใช้เมื่อต้องการ logic ที่ทำงานระดับ Repository (ไม่ใช่ Model Observer)
 * เช่น audit log, cache invalidation, notification
 *
 * ⚠️ Hooks เป็น stateful — ถ้าใช้กับ long-running process (queue, Octane)
 * ควร resetHooks() หลังใช้งาน เพื่อป้องกัน memory leak
 */
interface HookableInterface
{
    /** ลงทะเบียน hook ก่อน create — callback รับ &$payload (แก้ไขได้) */
    public function beforeCreate(Closure $callback): static;

    /** ลงทะเบียน hook หลัง create — callback รับ $model ที่สร้างแล้ว */
    public function afterCreate(Closure $callback): static;

    /** ลงทะเบียน hook ก่อน update — callback รับ &$payload (แก้ไขได้) */
    public function beforeUpdate(Closure $callback): static;

    /** ลงทะเบียน hook หลัง update — callback รับ $payload */
    public function afterUpdate(Closure $callback): static;

    /** ลงทะเบียน hook ก่อน delete — callback รับ $id */
    public function beforeDelete(Closure $callback): static;

    /** ลงทะเบียน hook หลัง delete — callback รับ $model ที่ถูกลบ */
    public function afterDelete(Closure $callback): static;

    /** ล้าง hooks ทั้งหมด — ใช้ป้องกัน memory leak ใน long-running processes */
    public function resetHooks(): static;
}
