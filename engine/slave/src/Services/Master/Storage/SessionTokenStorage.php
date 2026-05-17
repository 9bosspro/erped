<?php

declare(strict_types=1);

namespace Slave\Services\Master\Storage;

use Slave\Contracts\Master\TokenStorageInterface;

/**
 * SessionTokenStorage — เก็บ token ไว้ใน Laravel Session
 *
 * เหมาะกับ Personal/Password Flow ที่ผูกกับ user session เดียว
 * session()->save() เรียกทันทีเพื่อป้องกัน token หายเมื่อ process ถูก terminate กลางคัน
 */
final class SessionTokenStorage implements TokenStorageInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return session($key, $default);
    }

    public function put(string $key, mixed $value, int $ttl): void
    {
        session([$key => $value]);
        session()->save();
    }

    public function forget(string $key): void
    {
        session()->forget($key);
        session()->save();
    }
}
