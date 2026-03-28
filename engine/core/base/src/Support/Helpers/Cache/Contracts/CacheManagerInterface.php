<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Cache\Contracts;

use Closure;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\Repository;

/**
 * CacheManagerInterface — สัญญาสำหรับ Cache Helper
 *
 * ครอบคลุม:
 *  - Core         (get, put, forever, has, missing, pull, forget, flush)
 *  - Typed        (getString, getInt, getFloat, getArray, getBool)
 *  - Remember     (remember, rememberForever, rememberWithLock)
 *  - Batch        (many, putMany, forgetMany)
 *  - Atomic       (add, increment, decrement)
 *  - Lock         (lock)
 *  - Tags         (tags, rememberTags, rememberForeverTags, forgetByTags)
 *  - Store        (store)
 *  - Response     (cacheResponse, getCachedResponse, hasCachedResponse, forgetCachedResponse)
 *  - Key Utility  (sanitizeKey, prefixed, supportsTags)
 */
interface CacheManagerInterface
{
    // ─── Core ───────────────────────────────────────────────────

    public function get(string $key, mixed $default = null): mixed;

    public function put(string $key, mixed $value, int $ttl = 3600): bool;

    public function forever(string $key, mixed $value): bool;

    public function has(string $key): bool;

    public function missing(string $key): bool;

    public function pull(string $key, mixed $default = null): mixed;

    public function forget(string $key): bool;

    public function flush(): bool;

    // ─── Typed Getters ──────────────────────────────────────────

    public function getString(string $key, string $default = ''): string;

    public function getInt(string $key, int $default = 0): int;

    public function getFloat(string $key, float $default = 0.0): float;

    /** @param mixed[] $default @return mixed[] */
    public function getArray(string $key, array $default = []): array;

    public function getBool(string $key, bool $default = false): bool;

    // ─── Remember ───────────────────────────────────────────────

    public function remember(string $key, int $ttl, Closure $callback): mixed;

    public function rememberForever(string $key, Closure $callback): mixed;

    public function rememberWithLock(
        string $key,
        int $ttl,
        Closure $callback,
        int $lockTtl = 10,
    ): mixed;

    // ─── Batch ──────────────────────────────────────────────────

    /** @param string[] $keys @return array<string, mixed> */
    public function many(array $keys): array;

    /** @param array<string, mixed> $values */
    public function putMany(array $values, int $ttl = 3600): bool;

    /** @param string[] $keys */
    public function forgetMany(array $keys): bool;

    // ─── Atomic ─────────────────────────────────────────────────

    public function add(string $key, mixed $value, int $ttl = 3600): bool;

    public function increment(string $key, int $amount = 1): int|bool;

    public function decrement(string $key, int $amount = 1): int|bool;

    // ─── Lock ───────────────────────────────────────────────────

    public function lock(string $name, int $seconds = 0): Lock;

    // ─── Tags ───────────────────────────────────────────────────

    public function tags(string|array $names): TaggedCache;

    /** @param string[] $tags */
    public function rememberTags(array $tags, string $key, int $ttl, Closure $callback): mixed;

    /** @param string[] $tags */
    public function rememberForeverTags(array $tags, string $key, Closure $callback): mixed;

    public function forgetByTags(string|array $names): bool;

    // ─── Store ──────────────────────────────────────────────────

    public function store(?string $name = null): Repository;

    // ─── Response Cache ─────────────────────────────────────────

    public function cacheResponse(string $url, string $content, int $ttl = 60): void;

    public function getCachedResponse(string $url): ?string;

    public function hasCachedResponse(string $url): bool;

    public function forgetCachedResponse(string $url): bool;

    // ─── Key Utility ────────────────────────────────────────────

    public function sanitizeKey(string $key): string;

    public function prefixed(string $prefix, string $key): string;

    public function supportsTags(): bool;
}
