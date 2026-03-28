<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Criteria Contract — interface สำหรับสร้าง reusable query conditions
 *
 * ใช้ encapsulate เงื่อนไข query ที่ต้องใช้ซ้ำหลายที่
 *
 * ตัวอย่าง:
 * ```php
 * class ActiveUsersCriteria implements CriteriaContract
 * {
 *     public function apply(Builder $query): Builder
 *     {
 *         return $query->where('is_active', true)
 *                      ->whereNotNull('email_verified_at');
 *     }
 * }
 * ```
 */
interface CriteriaContract
{
    /**
     * Apply เงื่อนไขลงใน query builder
     *
     * @param  Builder  $query  Eloquent query builder
     * @return Builder query ที่ถูก modify แล้ว
     */
    public function apply(Builder $query): Builder;
}
