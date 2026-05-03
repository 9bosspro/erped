<?php

declare(strict_types=1);

namespace Core\Base\Services\Ai;

use Core\Base\Services\Ai\Contracts\AiCacheServiceInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Facades\Ai;
use Throwable;

/**
 * AiCacheService — จัดการการเรียกใช้งาน AI พร้อมเลเยอร์ Caching เพื่อลดค่าใช้จ่าย
 */
class AiCacheService implements AiCacheServiceInterface
{
    /**
     * @param  string  $cachePrefix  คำนำหน้าชื่อ Key ใน Cache
     * @param  int  $defaultTtl  ค่าเวลาเริ่มต้น (7 วัน)
     */
    public function __construct(
        protected string $cachePrefix = 'llm_cache:',
        protected int $defaultTtl = 604800,
    ) {}

    /**
     * {@inheritdoc}
     */
    public function ask(string $prompt, array $options = [], ?int $ttl = null): mixed
    {
        $key = $this->generateKey($prompt, $options);
        $ttl = $ttl ?? $this->defaultTtl;

        // ตรวจสอบใน Cache ก่อน
        if (Cache::has($key)) {
            Log::debug("AI Cache Hit: [{$key}] for prompt: ".substr($prompt, 0, 50).'...');

            return Cache::get($key);
        }

        // หากไม่มีใน Cache ให้เรียก API จริง
        Log::info('AI Cache Miss: Requesting LLM API for prompt: '.substr($prompt, 0, 50).'...');

        try {
            $response = Ai::chat($prompt, $options);

            // เก็บลง Cache
            Cache::put($key, $response, $ttl);

            return $response;
        } catch (Throwable $e) {
            Log::error('AiCacheService Error: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function forget(string $prompt, array $options = []): bool
    {
        return Cache::forget($this->generateKey($prompt, $options));
    }

    /**
     * สร้าง Unique Hash สำหรับการทำ Cache Key
     */
    protected function generateKey(string $prompt, array $options): string
    {
        // ผสม Prompt + Options เพื่อให้ได้ Key ที่ระบุเจาะจงถึง Model และ Parameter ต่างๆ
        $payload = [
            'prompt' => $prompt,
            'options' => $options,
            'connection' => config('ai.default'),
        ];

        return $this->cachePrefix.sha1(serialize($payload));
    }
}
