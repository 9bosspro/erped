<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Crypto\Concerns;

use RuntimeException;

/**
 * ParsesEncryptionKey — Trait สำหรับ parse encryption key
 *
 * รองรับ:
 *  - `base64:xxxxx` prefix — decode base64 ก่อนใช้ (Laravel convention)
 *  - Raw string — ใช้ตรงๆ
 *
 * ใช้ร่วมกันระหว่าง HashHelper และ EncryptionHelper
 * เพื่อหลีกเลี่ยง duplicate implementation
 */
trait ParsesEncryptionKey
{
    /**
     * Parse key — รองรับ `base64:xxxxx` prefix เหมือน Laravel
     *
     * @param  string  $key  raw key string (อาจมี `base64:` prefix)
     * @return string  decoded key string
     *
     * @throws RuntimeException ถ้า base64 decode ล้มเหลว
     */
    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false) {
                throw new RuntimeException('Key base64 decode ล้มเหลว');
            }

            return $decoded;
        }

        return $key;
    }
}
