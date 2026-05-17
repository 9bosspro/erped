<?php

declare(strict_types=1);

namespace Slave\Traits;

use Carbon\Carbon;

/**
 * TokenExpiryTrait — ตรวจสอบวันหมดอายุของ Token
 *
 * ใช้ Double-check layer: เวลาปัจจุบัน + buffer > expires_at = ถือว่าหมดอายุ
 * ป้องกัน token หมดอายุกลางอากาศระหว่าง request
 */
trait TokenExpiryTrait
{
    /**
     * ตรวจสอบว่า cached token data หมดอายุแล้วหรือไม่
     *
     * @param  int  $bufferSeconds  ระยะ buffer ก่อน expires_at จริง (วินาที)
     */
    private function isExpiredData(mixed $cached, int $bufferSeconds = 300): bool
    {
        if ($cached === null) {
            return true;
        }

        // Legacy: string token ไม่มี expiry metadata — ถือว่า valid
        if (\is_string($cached)) {
            return false;
        }

        if (\is_array($cached) && isset($cached['expires_at'])) {
            return now()->addSeconds($bufferSeconds)->gte(
                Carbon::parse($cached['expires_at']),
            );
        }

        // ข้อมูลรูปแบบไม่รู้จัก — ปลอดภัยไว้ก่อน ถือว่าหมดอายุ
        return true;
    }
}
