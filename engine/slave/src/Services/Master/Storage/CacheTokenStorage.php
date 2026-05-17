<?php

declare(strict_types=1);

namespace Slave\Services\Master\Storage;

use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Cache;
use Slave\Contracts\Master\TokenStorageInterface;

/**
 * CacheTokenStorage — เก็บ token ผ่าน Laravel Cache (redis, file, ฯลฯ)
 *
 * เหมาะกับ OAuth/Client Credentials Flow ที่ shared ข้าม request ได้
 * storeName = null ใช้ default cache driver ที่ตั้งใน config/cache.php
 */
final class CacheTokenStorage implements TokenStorageInterface
{
    public function __construct(private readonly ?string $storeName = null) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->store()->get($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        $this->store()->put($key, $value, $ttl);
    }

    public function forget(string $key): void
    {
        $this->store()->forget($key);
    }

    private function store(): CacheRepository
    {
        return $this->storeName !== null
            ? Cache::store($this->storeName)
            : Cache::store();
    }
}
