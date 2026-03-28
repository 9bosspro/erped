<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Core\Base\Repositories\Contracts\CriteriaContract;
use Illuminate\Database\Eloquent\Collection;

/**
 * Criteria Interface — Criteria Pattern สำหรับ reusable query conditions
 *
 * ใช้เมื่อต้องการ encapsulate เงื่อนไข query ที่ใช้ซ้ำหลายที่
 * เช่น ActiveUsersCriteria, RecentOrdersCriteria
 *
 * Criteria ถูก apply ครั้งเดียวใน newQuery() แล้ว auto-reset
 * (stateless — ไม่ค้างข้าม query)
 *
 * ตัวอย่าง:
 * ```php
 * $repo->withCriteria(new ActiveUsersCriteria())
 *      ->withCriteria(new VerifiedEmailCriteria())
 *      ->paginate(20);
 * ```
 */
interface CriteriaInterface
{
    /**
     * ดึง records ที่ตรงตาม Criteria (ใช้ครั้งเดียว ไม่สะสม)
     *
     * @param  CriteriaContract  $criteria  instance ของ Criteria
     * @return Collection<int, Model>
     */
    public function getByCriteria(CriteriaContract $criteria): Collection;

    /**
     * เพิ่ม Criteria เข้า stack — จะถูก apply ใน query ถัดไป แล้ว auto-reset
     *
     * @param  CriteriaContract  $criteria  instance ของ Criteria
     * @return static fluent interface
     */
    public function withCriteria(CriteriaContract $criteria): static;

    /**
     * ล้าง Criteria stack ที่สะสมไว้
     */
    public function resetCriteria(): static;
}
