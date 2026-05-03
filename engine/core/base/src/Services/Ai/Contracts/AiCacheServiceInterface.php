<?php

declare(strict_types=1);

namespace Core\Base\Services\Ai\Contracts;

/**
 * AiCacheServiceInterface — อินเทอร์เฟซสำหรับระบบจัดการ LLM Cache
 */
interface AiCacheServiceInterface
{
    /**
     * ถามคำถาม AI พร้อมระบบตรวจสอบ Cache
     *
     * @param  int|null  $ttl  ระยะเวลาเก็บ Cache (วินาที)
     */
    public function ask(string $prompt, array $options = [], ?int $ttl = null): mixed;

    /**
     * ล้าง Cache สำหรับ Prompt เฉพาะตัว
     */
    public function forget(string $prompt, array $options = []): bool;
}
