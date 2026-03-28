<?php

declare(strict_types=1);

namespace Core\Base\Services\Crypto\Concerns;

use RuntimeException;

/**
 * Parse encryption key จาก config
 *
 * รองรับ:
 *  - `base64:xxxxx` prefix — decode base64 ก่อนใช้
 *  - Raw string — ใช้ตรงๆ
 */
trait ParsesEncryptionKey
{
    private function parseKey(string $key): string
    {
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded === false) {
                throw new RuntimeException('Encryption key base64 decode ล้มเหลว');
            }

            return $decoded;
        }

        return $key;
    }
}
