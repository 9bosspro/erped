<?php

declare(strict_types=1);

namespace Core\Base\Services\Cache;

use App\Models\CacheOption;
use Core\Base\Contracts\Cache\CacheServiceInterface;
use Illuminate\Support\Facades\Cache;

/**
 * CacheService — จัดการ CacheOption ที่เก็บใน DB พร้อม in-memory cache
 *
 * ใช้สำหรับ option/config ที่เปลี่ยนแปลงได้ผ่าน UI และต้องการ cache เพื่อ performance
 * ไม่ใช้สำหรับ route caching หรือ request caching ทั่วไป
 *
 * Pattern: DB เป็น source of truth, Cache เป็น read-through layer
 */
class CacheService implements CacheServiceInterface
{
    /**
     * Prefix สำหรับแยกหมวดหมู่ Cache ของระบบ Option
     */
    protected const string CACHE_PREFIX = 'sys_opt:';

    /**
     * บันทึกหรืออัปเดต cache option ลง DB แล้วล้าง cache key เดิม
     *
     * @param  string  $key  Cache key (ชื่อ option)
     * @param  array<mixed>|string|null  $value  ค่าที่ต้องการบันทึก (array หรือ string)
     * @param  string  $type  ประเภท option (default: 'system')
     * @return bool true ถ้าบันทึกสำเร็จ
     */
    public function setCacheOption(string $key, array|string|null $value, string $type = 'system'): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        $storeValue = \is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value;

        CacheOption::updateOrCreate(
            ['name' => $key, 'type' => $type],
            ['value' => $storeValue],
        );

        Cache::forget(self::CACHE_PREFIX.$key);

        return true;
    }

    /**
     * ดึงค่า cache option — ค้นจาก cache ก่อน ถ้า miss ค้นจาก DB แล้ว cache เฉพาะเนื้อหา (ประหยัด RAM)
     *
     * @param  string  $key  Cache key (ชื่อ option)
     * @param  int  $timeout  ระยะเวลา cache (วินาที) — default 86400 (1 วัน)
     * @return mixed ค่าของ option หรือ null ถ้าไม่พบ
     */
    public function getCacheOption(string $key, int $timeout = 86400): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        return Cache::remember($cacheKey, $timeout, function () use ($key): mixed {
            $record = CacheOption::where('name', $key)->first();

            if ($record === null) {
                return null;
            }

            if (\is_string($record->value) && \json_validate($record->value)) {
                return json_decode($record->value, true);
            }

            return $record->value;
        });
    }

    /**
     * ลบ cache option ทั้งจาก DB และ cache
     *
     * @param  string  $key  Cache key (ชื่อ option)
     * @return bool true ถ้าลบสำเร็จ
     */
    public function deleteCacheOption(string $key): bool
    {
        $deleted = CacheOption::where('name', $key)->delete();

        if ($deleted > 0) {
            Cache::forget(self::CACHE_PREFIX.$key);

            return true;
        }

        return false;
    }

    /**
     * ล้าง Cache ของ CacheOption ทั้งหมด โดยอ้างอิง keys จาก DB
     *
     * วิธีนี้ปลอดภัยในทุก cache driver (ไม่ต้องอาศัย tag support)
     * สำหรับ Reset ระบบหรือล้างค่า Configurations ทั้งหมด
     */
    public function flushOptions(): void
    {
        CacheOption::query()
            ->select('name')
            ->cursor()
            ->each(function (CacheOption $record): void {
                $name = $record->getAttribute('name');
                if (\is_string($name) && $name !== '') {
                    Cache::forget(self::CACHE_PREFIX.$name);
                }
            });
    }

    /**
     * Cache ผลลัพธ์ของ query builder (helper สำหรับ subclass)
     *
     * @param  string  $key  Cache key
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query  Query ที่ต้องการ cache
     * @param  int  $timeout  ระยะเวลา cache (วินาที)
     * @return mixed ผลลัพธ์ของ query
     */
    protected function cacheQuery(string $key, mixed $query, int $timeout = 60): mixed
    {
        return Cache::remember($key, $timeout, function () use ($query): mixed {
            return $query->get();
        });
    }
}
