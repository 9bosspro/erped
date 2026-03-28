<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Interfaces;

use Closure;

/**
 * Cacheable Interface — cache layer สำหรับ Repository
 *
 * รองรับทั้ง tagged cache (Redis, Memcached) และ non-tagged (file, database)
 */
interface CacheableInterface
{
    /**
     * ดึงจาก cache หรือ execute callback แล้ว cache ผลลัพธ์
     *
     * @param  string  $cacheKey  cache key (ควรตั้งให้ unique ต่อ query)
     * @param  int  $ttl  อายุ cache เป็นวินาที
     * @param  Closure  $callback  callback ที่จะรันถ้า cache miss
     * @param  array  $relations  eager-load relations (ส่งเข้า callback)
     * @param  string|null  $cacheTag  tag สำหรับ group cache (flush ทีเดียว)
     * @param  bool  $preventStampede  ป้องกัน stampede (thundering herd)
     * @param  int  $lockSeconds  ระยะเวลา lock
     */
    public function remember(
        string $cacheKey,
        int $ttl,
        Closure $callback,
        array $relations = [],
        ?string $cacheTag = null,
        bool $preventStampede = false,
        int $lockSeconds = 10,
    ): mixed;

    /**
     * ลบ cache key เดียว (ไม่ flush ทั้ง tag)
     *
     * @param  string  $cacheKey  cache key ที่ต้องการลบ
     * @param  string|null  $cacheTag  tag override (null = ใช้ default tag)
     */
    public function forgetByKey(string $cacheKey, ?string $cacheTag = null): bool;

    /**
     * ล้าง cache ตาม tag หรือ key
     *
     * @param  string|null  $tag  ถ้าระบุ — flush cache ทั้ง tag, ถ้า null — ใช้ default tag
     * @return bool true ถ้าล้างสำเร็จ
     */
    public function forgetCache(?string $tag = null): bool;

    /**
     * เปิด auto-cache — ใช้ร่วมกับ remember() ในการตั้ง default TTL/tag
     *
     * @param  int  $ttl  อายุ cache เป็นวินาที (default: 1 ชั่วโมง)
     * @param  string|null  $tag  cache tag (default: ชื่อ table ของ Model)
     */
    public function enableCache(int $ttl = 3600, ?string $tag = null): static;

    /**
     * ปิด auto-cache
     */
    public function disableCache(): static;
}
