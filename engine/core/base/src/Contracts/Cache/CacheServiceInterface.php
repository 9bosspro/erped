<?php

declare(strict_types=1);

namespace Core\Base\Contracts\Cache;

/**
 * CacheServiceInterface — สัญญาจ้างสำหรับจัดการ Cache Option (DB + Memory)
 */
interface CacheServiceInterface
{
    /**
     * บันทึกหรืออัปเดต cache option ลง DB แล้วล้าง cache key เดิม
     */
    public function setCacheOption(string $key, array|string|null $value, string $type = 'system'): bool;

    /**
     * ดึงค่า cache option — ค้นจาก cache ก่อน ถ้า miss ค้นจาก DB แล้ว cache ไว้
     */
    public function getCacheOption(string $key, int $timeout = 86400): mixed;

    /**
     * ลบ cache option ทั้งจาก DB และ cache
     */
    public function deleteCacheOption(string $key): bool;
}
