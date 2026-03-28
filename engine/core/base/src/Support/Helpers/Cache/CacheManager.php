<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Cache;

use Closure;
use Core\Base\Support\Helpers\Cache\Contracts\CacheManagerInterface;
use Illuminate\Cache\TaggableStore;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * CacheManager — Cache Helper ที่ครบครัน ปลอดภัย และสอดคล้องกับ Laravel
 *
 * ═══════════════════════════════════════════════════════════════
 *  Core (อ่าน/เขียน/ลบ)
 * ═══════════════════════════════════════════════════════════════
 *  get($key, $default)         — ดึงค่า (คืน default ถ้า miss)
 *  put($key, $value, $ttl)     — เก็บค่าพร้อม TTL (วินาที)
 *  forever($key, $value)       — เก็บค่าถาวร (ไม่หมดอายุ)
 *  has($key)                   — ตรวจว่า key มีอยู่
 *  missing($key)               — ตรงข้าม has()
 *  pull($key, $default)        — ดึงค่า + ลบ (atomic)
 *  forget($key)                — ลบ key เดียว
 *  flush()                     — ล้างทั้ง store (ใช้ด้วยความระมัดระวัง)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Typed Getters (type-safe — ป้องกัน type confusion)
 * ═══════════════════════════════════════════════════════════════
 *  getString($key, $default)   — คืน string (หรือ default ถ้า miss/ผิด type)
 *  getInt($key, $default)      — คืน int
 *  getFloat($key, $default)    — คืน float
 *  getArray($key, $default)    — คืน array
 *  getBool($key, $default)     — คืน bool
 *
 * ═══════════════════════════════════════════════════════════════
 *  Remember (Lazy evaluation — ดึงจาก DB เฉพาะเมื่อ cache miss)
 * ═══════════════════════════════════════════════════════════════
 *  remember($key, $ttl, Closure)          — cache miss → เรียก Closure
 *  rememberForever($key, Closure)         — ไม่หมดอายุ
 *  rememberWithLock($key, $ttl, Closure)  — Anti-stampede via distributed lock
 *
 * ═══════════════════════════════════════════════════════════════
 *  Batch (หลาย key พร้อมกัน)
 * ═══════════════════════════════════════════════════════════════
 *  many($keys)                 — ดึงหลาย key ในครั้งเดียว
 *  putMany($values, $ttl)      — เก็บหลาย key ในครั้งเดียว
 *  forgetMany($keys)           — ลบหลาย key ในครั้งเดียว
 *
 * ═══════════════════════════════════════════════════════════════
 *  Atomic (ป้องกัน race condition)
 * ═══════════════════════════════════════════════════════════════
 *  add($key, $value, $ttl)     — เก็บเฉพาะเมื่อ key ไม่มีอยู่ก่อน
 *  increment($key, $amount)    — เพิ่มค่าตัวเลข
 *  decrement($key, $amount)    — ลดค่าตัวเลข
 *
 * ═══════════════════════════════════════════════════════════════
 *  Lock (Distributed Locking)
 * ═══════════════════════════════════════════════════════════════
 *  lock($name, $seconds)       — คืน Lock object สำหรับ distributed lock
 *
 * ═══════════════════════════════════════════════════════════════
 *  Tags (ลบ cache เป็นกลุ่ม — ต้องใช้ Redis หรือ Memcached)
 * ═══════════════════════════════════════════════════════════════
 *  tags($names)                          — คืน TaggedCache repository
 *  rememberTags($tags, $key, $ttl, fn)   — remember พร้อม tag
 *  rememberForeverTags($tags, $key, fn)  — rememberForever + tag
 *  forgetByTags($names)                  — ลบทุก key ใน tag กลุ่มนั้น
 *
 * ═══════════════════════════════════════════════════════════════
 *  Store (สลับ cache driver)
 * ═══════════════════════════════════════════════════════════════
 *  store($name)                — สลับ cache store (redis, file, database ฯลฯ)
 *
 * ═══════════════════════════════════════════════════════════════
 *  Response Cache (cache HTTP response ตาม URL)
 * ═══════════════════════════════════════════════════════════════
 *  cacheResponse($url, $content, $ttl) — เก็บ response content
 *  getCachedResponse($url)             — ดึง cached response
 *  hasCachedResponse($url)             — ตรวจว่ามี cached response
 *  forgetCachedResponse($url)          — ลบ cached response
 *
 * ═══════════════════════════════════════════════════════════════
 *  Key Utility
 * ═══════════════════════════════════════════════════════════════
 *  sanitizeKey($key)           — ทำ key ให้ปลอดภัย (PSR-6 compliant)
 *  prefixed($prefix, $key)     — สร้าง key พร้อม namespace prefix
 *  supportsTags()              — ตรวจว่า store รองรับ tags (instanceof TaggableStore)
 *
 * ─── หมายเหตุ ────────────────────────────────────────────────
 *  - TTL ทุกที่ใช้หน่วย วินาที (seconds) ตาม Laravel convention
 *  - ไม่รับ raw SQL — ใช้ Closure แทนเสมอเพื่อป้องกัน injection
 *  - rememberWithLock ใช้ sentinel object ตรวจ cache miss อย่างถูกต้อง
 *    (รองรับ cached value ที่เป็น null ด้วย)
 *  - Tags ต้องการ store ที่รองรับ (Redis, Memcached) — ไม่ใช่ file/array/database
 */
final class CacheManager implements CacheManagerInterface
{
    /** @var string prefix สำหรับ HTTP response cache keys */
    private const RESPONSE_KEY_PREFIX = 'http_response:';

    /** @var string suffix สำหรับ lock keys (ป้องกัน collision กับ data key) */
    private const LOCK_KEY_SUFFIX = ':__lock__';

    // ═══════════════════════════════════════════════════════════
    //  Core
    // ═══════════════════════════════════════════════════════════

    /**
     * ดึงค่าจาก cache
     *
     * @param  string  $key      Cache key
     * @param  mixed   $default  ค่า default ถ้า cache miss
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return Cache::get($key, $default);
    }

    /**
     * เก็บค่าใน cache พร้อม TTL
     *
     * @param  string  $key    Cache key
     * @param  mixed   $value  ค่าที่ต้องการเก็บ
     * @param  int     $ttl    Time-to-live (วินาที, default 3600 = 1 ชั่วโมง)
     */
    public function put(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Cache::put($key, $value, $ttl);
    }

    /**
     * เก็บค่าถาวรใน cache (ไม่หมดอายุจนกว่าจะ forget)
     *
     * ⚠️ ใช้กับข้อมูลที่ไม่เปลี่ยนแปลง หรือมีกลไก invalidation อื่น
     *
     * @param  string  $key    Cache key
     * @param  mixed   $value  ค่าที่ต้องการเก็บ
     */
    public function forever(string $key, mixed $value): bool
    {
        return Cache::forever($key, $value);
    }

    /**
     * ตรวจสอบว่า key มีอยู่ใน cache
     */
    public function has(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * ตรงข้าม has() — ตรวจว่า key ไม่มีอยู่ใน cache
     */
    public function missing(string $key): bool
    {
        return Cache::missing($key);
    }

    /**
     * ดึงค่าจาก cache แล้วลบออกทันที (atomic get-and-delete)
     *
     * ใช้กับ one-time tokens หรือ single-use values
     *
     * @param  string  $key      Cache key
     * @param  mixed   $default  ค่า default ถ้า miss
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return Cache::pull($key, $default);
    }

    /**
     * ลบ cache key
     */
    public function forget(string $key): bool
    {
        return Cache::forget($key);
    }

    /**
     * ล้าง cache store ทั้งหมด
     *
     * ⚠️ ล้างทุก key ใน store — ใช้ใน production ด้วยความระมัดระวังสูง
     */
    public function flush(): bool
    {
        return Cache::flush();
    }

    // ═══════════════════════════════════════════════════════════
    //  Typed Getters
    // ═══════════════════════════════════════════════════════════

    /**
     * ดึงค่าจาก cache แบบ type-safe เป็น string
     *
     * คืน $default ถ้า cache miss หรือค่าที่เก็บไม่ใช่ string
     * ป้องกัน bug จาก type coercion เช่น (string)false = "" หรือ (string)[] = "Array"
     *
     * @param  string  $key      Cache key
     * @param  string  $default  ค่า default
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = Cache::get($key);

        return is_string($value) ? $value : $default;
    }

    /**
     * ดึงค่าจาก cache แบบ type-safe เป็น int
     *
     * @param  string  $key      Cache key
     * @param  int     $default  ค่า default
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = Cache::get($key);

        return is_int($value) ? $value : $default;
    }

    /**
     * ดึงค่าจาก cache แบบ type-safe เป็น float
     *
     * @param  string  $key      Cache key
     * @param  float   $default  ค่า default
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $value = Cache::get($key);

        return is_float($value) || is_int($value) ? (float) $value : $default;
    }

    /**
     * ดึงค่าจาก cache แบบ type-safe เป็น array
     *
     * @param  string   $key      Cache key
     * @param  mixed[]  $default  ค่า default
     * @return mixed[]
     */
    public function getArray(string $key, array $default = []): array
    {
        $value = Cache::get($key);

        return is_array($value) ? $value : $default;
    }

    /**
     * ดึงค่าจาก cache แบบ type-safe เป็น bool
     *
     * @param  string  $key      Cache key
     * @param  bool    $default  ค่า default
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $value = Cache::get($key);

        return is_bool($value) ? $value : $default;
    }

    // ═══════════════════════════════════════════════════════════
    //  Remember (Lazy evaluation)
    // ═══════════════════════════════════════════════════════════

    /**
     * Cache ผลลัพธ์ของ Closure ตาม key และ TTL
     *
     * Closure จะถูกเรียกเฉพาะเมื่อ cache miss — ผลลัพธ์ถูกเก็บโดยอัตโนมัติ
     *
     * ตัวอย่าง:
     * ```php
     * $users = $cache->remember('users.active', 600, fn() => User::active()->get());
     * ```
     *
     * @param  string   $key       Cache key
     * @param  int      $ttl       Time-to-live (วินาที)
     * @param  Closure  $callback  Closure ที่ดึงข้อมูล (เรียกเมื่อ miss เท่านั้น)
     */
    public function remember(string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache ผลลัพธ์ของ Closure ถาวร (ไม่หมดอายุ)
     *
     * @param  string   $key       Cache key
     * @param  Closure  $callback  Closure ที่ดึงข้อมูล
     */
    public function rememberForever(string $key, Closure $callback): mixed
    {
        return Cache::rememberForever($key, $callback);
    }

    /**
     * Remember พร้อม distributed lock เพื่อป้องกัน Cache Stampede
     *
     * ─── ปัญหา Cache Stampede (Thundering Herd) ──────────────────
     *  Cache หมดอายุพร้อมกัน → request จำนวนมากยิง DB พร้อมกัน
     *  → DB โหลดพุ่งสูง → latency เพิ่ม → อาจ cascade failure
     *
     * ─── วิธีที่ method นี้แก้ ────────────────────────────────────
     *  1. ตรวจ cache ก่อน (fast path) ด้วย sentinel object
     *     → รองรับ cached value ที่เป็น null อย่างถูกต้อง
     *  2. ถ้า miss → ขอ lock (block() — รอได้นาน $lockTtl วินาที)
     *  3. Process แรกที่ได้ lock → double-check cache → ดึงข้อมูลและ cache
     *  4. Process อื่น → รอ lock หมด → double-check พบ cache → return ทันที
     *  5. ถ้า lock timeout → fallback ให้ request นั้น query DB เอง
     *     (rare case: ยอมรับได้ กันดีกว่า return null)
     *
     * ตัวอย่าง:
     * ```php
     * $settings = $cache->rememberWithLock('app.settings', 3600, fn() => Setting::all());
     * ```
     *
     * @param  string   $key       Cache key
     * @param  int      $ttl       Time-to-live (วินาที)
     * @param  Closure  $callback  Closure ที่ดึงข้อมูล
     * @param  int      $lockTtl   Lock timeout สูงสุด (วินาที, default 10)
     */
    public function rememberWithLock(
        string $key,
        int $ttl,
        Closure $callback,
        int $lockTtl = 10,
    ): mixed {
        // ── Fast path: ตรวจ cache ก่อนด้วย sentinel (null-safe) ─────
        $sentinel = new \stdClass();
        $cached   = Cache::get($key, $sentinel);

        if ($cached !== $sentinel) {
            return $cached;
        }

        // ── Slow path: ขอ lock และ double-check ──────────────────────
        $lock = Cache::lock($key . self::LOCK_KEY_SUFFIX, $lockTtl);

        try {
            // block() รอได้นาน $lockTtl วินาที — throw LockTimeoutException ถ้าหมดเวลา
            $lock->block($lockTtl);
        } catch (LockTimeoutException) {
            // Lock timeout → fallback: request นี้ query DB เอง (ดีกว่า return null)
            return Cache::remember($key, $ttl, $callback);
        }

        try {
            // Double-check หลังได้ lock (อาจมี process อื่น cache ไปแล้วระหว่างรอ)
            return Cache::remember($key, $ttl, $callback);
        } finally {
            $lock->release();
        }
    }

    // ═══════════════════════════════════════════════════════════
    //  Batch
    // ═══════════════════════════════════════════════════════════

    /**
     * ดึงหลาย key พร้อมกันในครั้งเดียว
     *
     * ประหยัด network round-trip กับ cache server (Redis pipeline)
     *
     * @param  string[]  $keys     รายการ keys
     * @return array<string, mixed>  map ของ key → value (null ถ้า miss)
     */
    public function many(array $keys): array
    {
        return Cache::many($keys);
    }

    /**
     * เก็บหลาย key พร้อมกันในครั้งเดียว
     *
     * @param  array<string, mixed>  $values  map ของ key → value
     * @param  int                   $ttl     Time-to-live (วินาที)
     */
    public function putMany(array $values, int $ttl = 3600): bool
    {
        return Cache::putMany($values, $ttl);
    }

    /**
     * ลบหลาย key พร้อมกัน
     *
     * @param  string[]  $keys  รายการ keys ที่ต้องการลบ
     */
    public function forgetMany(array $keys): bool
    {
        $success = true;

        foreach ($keys as $key) {
            if (! Cache::forget($key)) {
                $success = false;
            }
        }

        return $success;
    }

    // ═══════════════════════════════════════════════════════════
    //  Atomic
    // ═══════════════════════════════════════════════════════════

    /**
     * เก็บค่าเฉพาะเมื่อ key ไม่มีอยู่แล้ว (atomic check-and-set)
     *
     * ใช้สำหรับ: distributed lock seed, idempotency key, rate limit init
     *
     * @param  string  $key    Cache key
     * @param  mixed   $value  ค่าที่ต้องการเก็บ
     * @param  int     $ttl    Time-to-live (วินาที)
     * @return bool  true ถ้าเก็บสำเร็จ (key ไม่เคยมีมาก่อน)
     */
    public function add(string $key, mixed $value, int $ttl = 3600): bool
    {
        return Cache::add($key, $value, $ttl);
    }

    /**
     * เพิ่มค่าตัวเลขใน cache (atomic)
     *
     * ใช้สำหรับ: page view counter, rate limiting, request throttle
     * ⚠️ ถ้า key ยังไม่มี จะเริ่มจาก 0 + $amount
     *
     * @param  string  $key     Cache key
     * @param  int     $amount  จำนวนที่เพิ่ม (default 1)
     * @return int|bool  ค่าใหม่หลังเพิ่ม, false ถ้า store ไม่รองรับ
     */
    public function increment(string $key, int $amount = 1): int|bool
    {
        return Cache::increment($key, $amount);
    }

    /**
     * ลดค่าตัวเลขใน cache (atomic)
     *
     * @param  string  $key     Cache key
     * @param  int     $amount  จำนวนที่ลด (default 1)
     * @return int|bool  ค่าใหม่หลังลด, false ถ้า store ไม่รองรับ
     */
    public function decrement(string $key, int $amount = 1): int|bool
    {
        return Cache::decrement($key, $amount);
    }

    // ═══════════════════════════════════════════════════════════
    //  Lock
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน Lock object สำหรับ distributed locking
     *
     * ใช้เมื่อต้องการควบคุม lock lifecycle เอง หรือใช้กับ non-cache operations
     *
     * ตัวอย่าง:
     * ```php
     * // Auto-release หลัง Closure
     * $cache->lock('import:users', 30)->get(fn() => processImport());
     *
     * // Manual release (ใช้กับ queue job ข้ามขอบเขต request)
     * $lock = $cache->lock('report:generate', 60);
     * $lock->block(5);   // รอได้สูงสุด 5 วินาที
     * try {
     *     generateReport();
     * } finally {
     *     $lock->release();
     * }
     * ```
     *
     * @param  string  $name     ชื่อ lock
     * @param  int     $seconds  Lock TTL (วินาที, 0 = ไม่หมดอายุ)
     */
    public function lock(string $name, int $seconds = 0): Lock
    {
        return Cache::lock($name, $seconds);
    }

    // ═══════════════════════════════════════════════════════════
    //  Tags
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน TaggedCache repository สำหรับ tag-based operations
     *
     * ⚠️ ต้องใช้ Redis หรือ Memcached (TaggableStore) เท่านั้น
     *    ตรวจก่อนด้วย supportsTags() ถ้าต้องรองรับหลาย environment
     *
     * ตัวอย่าง:
     * ```php
     * $cache->tags(['users', 'admins'])->remember('list', 600, fn() => User::admins()->get());
     * $cache->tags('users')->flush();  // ลบทุก key ที่ tag ด้วย 'users'
     * ```
     *
     * @param  string|string[]  $names  Tag name หรือ array of tag names
     */
    public function tags(string|array $names): TaggedCache
    {
        return Cache::tags($names);
    }

    /**
     * Remember พร้อม tags — ลบ cache เป็นกลุ่มได้ด้วย forgetByTags()
     *
     * ตัวอย่าง:
     * ```php
     * // เก็บพร้อม tag
     * $users = $cache->rememberTags(['users'], 'users.all', 600, fn() => User::all());
     *
     * // เมื่อ user เปลี่ยน → ลบทุก key ใน tag 'users' พร้อมกัน
     * $cache->forgetByTags(['users']);
     * ```
     *
     * @param  string[]  $tags      Tag names สำหรับ grouping
     * @param  string    $key       Cache key
     * @param  int       $ttl       Time-to-live (วินาที)
     * @param  Closure   $callback  Closure ที่ดึงข้อมูล
     */
    public function rememberTags(array $tags, string $key, int $ttl, Closure $callback): mixed
    {
        return Cache::tags($tags)->remember($key, $ttl, $callback);
    }

    /**
     * RememberForever พร้อม tags
     *
     * @param  string[]  $tags      Tag names สำหรับ grouping
     * @param  string    $key       Cache key
     * @param  Closure   $callback  Closure ที่ดึงข้อมูล
     */
    public function rememberForeverTags(array $tags, string $key, Closure $callback): mixed
    {
        return Cache::tags($tags)->rememberForever($key, $callback);
    }

    /**
     * ลบทุก cache key ที่อยู่ใน tag group
     *
     * ใช้ใน Observer หรือ event listener เมื่อข้อมูลเปลี่ยน
     *
     * ตัวอย่าง:
     * ```php
     * // ใน UserObserver::saved()
     * $cache->forgetByTags(['users']);
     * ```
     *
     * @param  string|string[]  $names  Tag name หรือ array of tag names
     */
    public function forgetByTags(string|array $names): bool
    {
        Cache::tags($names)->flush();

        return true;
    }

    // ═══════════════════════════════════════════════════════════
    //  Store
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน cache Repository ของ store ที่ระบุ
     *
     * ตัวอย่าง:
     * ```php
     * $cache->store('redis')->put('key', $value, 300);
     * $cache->store('file')->remember('config', 86400, fn() => Config::all());
     * $cache->store('array')->put('test', 'value');  // สำหรับ testing
     * ```
     *
     * @param  string|null  $name  ชื่อ store จาก config('cache.stores') (null = default)
     */
    public function store(?string $name = null): Repository
    {
        return Cache::store($name);
    }

    // ═══════════════════════════════════════════════════════════
    //  Response Cache
    // ═══════════════════════════════════════════════════════════

    /**
     * Cache HTTP response content ตาม URL
     *
     * key = sha1(url) — สั้น (40 chars) และ unique เพียงพอ
     *
     * @param  string  $url      URL ที่ใช้เป็น cache key
     * @param  string  $content  Response content (HTML/JSON)
     * @param  int     $ttl      Time-to-live (วินาที, default 60)
     */
    public function cacheResponse(string $url, string $content, int $ttl = 60): void
    {
        Cache::put($this->makeResponseKey($url), $content, $ttl);
    }

    /**
     * ดึง cached HTTP response ตาม URL
     *
     * @return string|null  content ถ้า cache hit, null ถ้า miss
     */
    public function getCachedResponse(string $url): ?string
    {
        return Cache::get($this->makeResponseKey($url));
    }

    /**
     * ตรวจว่า URL มี cached response หรือไม่
     */
    public function hasCachedResponse(string $url): bool
    {
        return Cache::has($this->makeResponseKey($url));
    }

    /**
     * ลบ cached response ของ URL
     */
    public function forgetCachedResponse(string $url): bool
    {
        return Cache::forget($this->makeResponseKey($url));
    }

    // ═══════════════════════════════════════════════════════════
    //  Key Utility
    // ═══════════════════════════════════════════════════════════

    /**
     * ทำให้ key ปลอดภัยและ compatible กับทุก cache driver
     *
     * PSR-6 / PSR-16 key rules:
     *  - อนุญาต: A-Z, a-z, 0-9, underscore (_), dot (.), dash (-)
     *  - อักขระอื่น (รวม space, /, :, @) → แทนด้วย underscore
     *
     * ตัวอย่าง:
     *  "user list:2024"   → "user_list_2024"
     *  "settings/general" → "settings_general"
     *  "user@domain.com"  → "user_domain.com"
     *
     * @param  string  $key  Cache key ดิบ
     * @return string  key ที่ปลอดภัย
     */
    public function sanitizeKey(string $key): string
    {
        return preg_replace('/[^A-Za-z0-9_.\-]/', '_', $key) ?? $key;
    }

    /**
     * สร้าง cache key พร้อม namespace prefix
     *
     * ป้องกัน key collision ระหว่าง module/feature ต่างๆ
     *
     * ตัวอย่าง:
     *  prefixed('user', 'profile.42') → 'user:profile.42'
     *  prefixed('api.v2', 'users')    → 'api.v2:users'
     *
     * @param  string  $prefix  Namespace prefix (เช่น 'user', 'order', module name)
     * @param  string  $key     Cache key
     * @return string  key ในรูป "prefix:key"
     */
    public function prefixed(string $prefix, string $key): string
    {
        return $prefix . ':' . $key;
    }

    /**
     * ตรวจว่า default cache store รองรับ tags หรือไม่
     *
     * ใช้ instanceof TaggableStore (มาตรฐาน Laravel) แทน method_exists
     *
     * Store ที่รองรับ: RedisStore, MemcachedStore
     * Store ที่ไม่รองรับ: FileStore, DatabaseStore, ArrayStore, NullStore
     *
     * ใช้เพื่อ feature-flag tags ในโค้ดที่รองรับหลาย environment:
     * ```php
     * if ($cache->supportsTags()) {
     *     $cache->rememberTags(['users'], 'users.all', 600, fn() => User::all());
     * } else {
     *     $cache->remember('users.all', 600, fn() => User::all());
     * }
     * ```
     */
    public function supportsTags(): bool
    {
        try {
            return Cache::getStore() instanceof TaggableStore;
        } catch (\Throwable) {
            return false;
        }
    }

    // ─── Private ────────────────────────────────────────────────

    /**
     * สร้าง cache key สำหรับ HTTP response
     *
     * sha1 → 40 chars, เร็วกว่า sha256, เพียงพอสำหรับ uniqueness (ไม่ใช่ security)
     */
    private function makeResponseKey(string $url): string
    {
        return self::RESPONSE_KEY_PREFIX . sha1($url);
    }
}
