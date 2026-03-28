<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Traits;

use BadMethodCallException;
use Closure;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;

/**
 * Trait HasCacheOperations — cache layer สำหรับ Repository
 *
 * รองรับทั้ง tagged cache (Redis, Memcached) และ non-tagged (file, database)
 *
 * Boot: bootHasCacheOperations() ถูกเรียกใน BaseRepository::bootTraits()
 * จะ set default cacheTag จากชื่อ table ของ Model โดยอัตโนมัติ
 *
 * ตัวอย่าง:
 * ```php
 * // Explicit cache (ใช้บ่อยที่สุด)
 * $users = $repo->remember('users:active', 3600, fn($r) => $r->findWhere(['active' => true]));
 *
 * // Flush cache ทั้ง tag
 * $repo->forgetCache();
 *
 * // ลบ key เดียว
 * $repo->forgetByKey('users:active');
 * ```
 */
trait HasCacheOperations
{
    protected bool $cacheEnabled = false;

    protected int $cacheTtl = 3600;

    protected ?string $cacheTag = null;

    /**
     * ดึงจาก cache หรือ execute callback แล้ว cache ผลลัพธ์
     *
     * รองรับทั้ง tagged store (Redis) และ non-tagged (file) โดยอัตโนมัติ
     *
     * Cache Stampede Prevention (preventStampede=true):
     * เมื่อ cache หมดอายุพร้อมกัน หลาย requests จะแย่งกัน populate cache
     * (thundering herd / dog-pile effect) — ใช้ atomic lock ป้องกัน
     *
     * ⚠️ preventStampede=true ต้องการ cache driver ที่รองรับ atomic lock (Redis แนะนำ)
     *
     * ตัวอย่าง:
     * ```php
     * // ทั่วไป (default)
     * $users = $repo->remember('users:active', 3600, fn ($r) => $r->findWhere(['active' => true]));
     *
     * // High-traffic endpoint — ป้องกัน stampede
     * $stats = $repo->remember('stats:daily', 300, fn ($r) => $r->getStats(), preventStampede: true);
     * ```
     *
     * @param  string  $cacheKey  cache key (ควร unique ต่อ query)
     * @param  int  $ttl  อายุ cache เป็นวินาที
     * @param  Closure  $callback  callback รันเมื่อ cache miss — รับ ($repository, $relations)
     * @param  array  $relations  eager-load relations (ส่งเข้า callback)
     * @param  string|null  $cacheTag  tag override (null = ใช้ default tag)
     * @param  bool  $preventStampede  true = ใช้ atomic lock ป้องกัน thundering herd
     * @param  int  $lockSeconds  ระยะเวลา lock (ควรมากกว่า query time + buffer)
     */
    public function remember(
        string $cacheKey,
        int $ttl,
        Closure $callback,
        array $relations = [],
        ?string $cacheTag = null,
        bool $preventStampede = false,
        int $lockSeconds = 10,
    ): mixed {
        $tag = $cacheTag ?? $this->cacheTag;
        $store = $this->resolveStore($tag);

        if (! $preventStampede) {
            return $store->remember($cacheKey, $ttl, fn () => $callback($this, $relations));
        }

        // Fast path — ถ้า hit ส่งกลับทันที (เกือบทุก request)
        $cached = $store->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Slow path — เฉพาะเมื่อ cache miss
        // thread แรกได้ lock → populate cache
        // thread อื่นรอ lock แล้ว double-check → อ่านจาก cache ที่ populated แล้ว
        return Cache::lock("lock:{$cacheKey}", $lockSeconds)->get(
            function () use ($store, $cacheKey, $ttl, $callback, $relations) {
                $cached = $store->get($cacheKey);
                if ($cached !== null) {
                    return $cached;
                }

                $result = $callback($this, $relations);
                $store->put($cacheKey, $result, $ttl);

                return $result;
            },
        );
    }

    /**
     * ล้าง cache ทั้ง tag (เหมาะสำหรับ flush หลัง write operations)
     *
     * - มี tag + store รองรับ tagging → flush tag
     * - ไม่มี tag หรือ store ไม่รองรับ → คืน false (ไม่ throw)
     *
     * @param  string|null  $tag  tag override (null = ใช้ default tag)
     */
    public function forgetCache(?string $tag = null): bool
    {
        $tag = $tag ?? $this->cacheTag;

        if (! $tag) {
            return false;
        }

        try {
            return Cache::tags($tag)->flush();
        } catch (BadMethodCallException) {
            // Cache store ไม่รองรับ tagging (เช่น file, database)
            return false;
        }
    }

    /**
     * ลบ cache key เดียว (ไม่ flush ทั้ง tag)
     *
     * @param  string  $cacheKey  cache key ที่ต้องการลบ
     * @param  string|null  $cacheTag  tag override (null = ใช้ default tag)
     */
    public function forgetByKey(string $cacheKey, ?string $cacheTag = null): bool
    {
        $tag = $cacheTag ?? $this->cacheTag;
        $store = $this->resolveStore($tag);

        return $store->forget($cacheKey);
    }

    /**
     * เปิด auto-cache — ตั้งค่า default TTL และ tag
     *
     * @param  int  $ttl  อายุ cache เป็นวินาที (default: 1 ชั่วโมง)
     * @param  string|null  $tag  cache tag (null = ใช้ table name ของ Model)
     */
    public function enableCache(int $ttl = 3600, ?string $tag = null): static
    {
        $this->cacheEnabled = true;
        $this->cacheTtl = $ttl;

        if ($tag !== null) {
            $this->cacheTag = $tag;
        }

        return $this;
    }

    /**
     * ปิด auto-cache
     */
    public function disableCache(): static
    {
        $this->cacheEnabled = false;

        return $this;
    }

    /**
     * Boot — ถูกเรียกอัตโนมัติจาก BaseRepository::bootTraits()
     *
     * Set default cacheTag จากชื่อ table ของ Model
     * เช่น User model → cacheTag = 'users'
     */
    protected function bootHasCacheOperations(): void
    {
        if ($this->cacheTag === null && property_exists($this, 'model')) {
            $this->cacheTag = $this->model->getTable();
        }
    }

    /**
     * Resolve cache store — ใช้ tagged store ถ้ามี tag และ store รองรับ
     * ถ้า store ไม่รองรับ tagging (file, database) ใช้ default store แทน
     *
     * @param  string|null  $tag  cache tag
     */
    private function resolveStore(?string $tag): CacheRepository
    {
        if (! $tag) {
            return Cache::store();
        }

        try {
            return Cache::tags($tag);
        } catch (BadMethodCallException) {
            return Cache::store();
        }
    }
}
