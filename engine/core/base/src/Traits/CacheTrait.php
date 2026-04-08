<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * CacheTrait — ฟังก์ชัน cache เบื้องต้นสำหรับ class ที่ต้องการ cache response/query
 *
 * หมายเหตุ: สำหรับ use case ที่ซับซ้อนกว่า ให้ inject CacheManager แทน
 */
trait CacheTrait
{
    /**
     * ดึงผลลัพธ์ raw SQL จาก cache หรือ query แล้ว cache ผลลัพธ์
     *
     * ⚠️ ใช้ DB::select() กับ raw SQL ที่ไว้ใจได้เท่านั้น (static queries)
     *    ห้ามส่ง user input ลง $sql โดยตรง — ให้ใช้ bindings แทน
     *
     * @param  string  $key  cache key
     * @param  string  $sql  raw SQL query (ต้องไม่รับ user input โดยตรง)
     * @param  int  $seconds  TTL เป็นวินาที (default: 60)
     * @return array<mixed> ผลลัพธ์จาก query
     */
    protected function cacheQuery(string $key, string $sql, int $seconds = 60): array
    {
        /** @var array<mixed> */
        return Cache::remember($key, $seconds, fn (): array => DB::select($sql));
    }

    /**
     * เก็บ response content ลง cache โดยใช้ URL เป็น key (atomic — ไม่เขียนทับถ้ามีอยู่แล้ว)
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @param  Response  $response  HTTP response ที่ต้องการ cache
     * @param  int  $seconds  TTL เป็นวินาที (default: 60)
     */
    protected function set(Request $request, Response $response, int $seconds = 60): void
    {
        Cache::add($this->keygen($request->url()), $response->getContent(), $seconds);
    }

    /**
     * ดึง cached response content จาก URL
     *
     * @param  Request  $request  HTTP request ปัจจุบัน
     * @return string|null cached content หรือ null ถ้า cache miss
     */
    protected function grab(Request $request): ?string
    {
        /** @var string|null */
        return Cache::get($this->keygen($request->url()));
    }

    /**
     * สร้าง cache key จาก URL โดยใช้ SHA-256 hash เพื่อหลีกเลี่ยง collision
     *
     * @param  string  $url  URL ที่ต้องการสร้าง key
     * @return string cache key ในรูปแบบ 'route_{sha256}'
     */
    protected function keygen(string $url): string
    {
        return 'route_'.hash('sha256', $url);
    }
}
