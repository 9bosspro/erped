<?php

declare(strict_types=1);

namespace Slave\Contracts\Master;

/**
 * TokenStorageInterface — Contract สำหรับระบบจัดเก็บ Token
 *
 * ทุก implementation ต้องรองรับ get / put / forget
 * เพื่อให้ TokenManager สามารถสลับ driver ได้โดยไม่ต้องรู้ว่าใช้ session หรือ cache
 */
interface TokenStorageInterface
{
    /**
     * อ่านข้อมูลจาก storage
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * เขียนข้อมูลลง storage พร้อมกำหนด TTL (วินาที)
     */
    public function put(string $key, mixed $value, int $ttl): void;

    /**
     * ลบข้อมูลออกจาก storage
     */
    public function forget(string $key): void;
}
