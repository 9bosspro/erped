<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use Core\Base\Repositories\Contracts\CriteriaContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait HasCriteriaOperations — Criteria Pattern สำหรับ reusable query conditions
 *
 * Criteria ช่วย encapsulate เงื่อนไข query ที่ซับซ้อนหรือใช้ซ้ำหลายที่
 * แทนการ pass array conditions หรือ Closure ทุกครั้ง
 *
 * Lifecycle:
 * 1. withCriteria() → เพิ่ม Criteria เข้า stack
 * 2. query ถัดไป (paginate, all, etc.) → newQuery() apply criteria แล้ว reset stack
 * 3. Stack ว่างเปล่า — stateless สำหรับ query ถัดไป
 *
 * ตัวอย่าง:
 * ```php
 * // chain criteria
 * $activeVerified = $repo
 *     ->withCriteria(new ActiveUsersCriteria())
 *     ->withCriteria(new VerifiedEmailCriteria())
 *     ->paginate(20);
 *
 * // ใช้ครั้งเดียว
 * $admins = $repo->getByCriteria(new AdminRoleCriteria());
 * ```
 */
trait HasCriteriaOperations
{
    /** @var CriteriaContract[] stack ของ Criteria ที่รอ apply */
    protected array $criteria = [];

    /**
     * ดึง records ที่ตรงตาม Criteria (single use — ไม่สะสมใน stack)
     *
     * @param  CriteriaContract  $criteria  instance ของ Criteria
     * @return Collection<int, Model>
     */
    public function getByCriteria(CriteriaContract $criteria): Collection
    {
        $query = $this->newQuery();
        $query = $criteria->apply($query);

        return $query->get();
    }

    /**
     * เพิ่ม Criteria เข้า stack — จะถูก apply ใน query ถัดไป แล้ว auto-reset
     *
     * Fluent interface: สามารถ chain ได้หลายตัว
     *
     * @param  CriteriaContract  $criteria  instance ของ Criteria
     * @return static fluent interface
     */
    public function withCriteria(CriteriaContract $criteria): static
    {
        $this->criteria[] = $criteria;

        return $this;
    }

    /**
     * ล้าง Criteria stack ทั้งหมด
     *
     * ใช้เมื่อต้องการ cancel criteria ที่ยังไม่ได้ถูก apply
     * ปกติไม่จำเป็นเพราะ newQuery() auto-reset อยู่แล้ว
     */
    public function resetCriteria(): static
    {
        $this->criteria = [];

        return $this;
    }
}
