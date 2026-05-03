<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use Carbon\Carbon;

/**
 * TokenManager — จัดการ Backend API token ใน session
 *
 * รับผิดชอบเฉพาะ session token lifecycle:
 *  - บันทึก / อ่าน / ลบ token จาก session
 *  - ตรวจสอบ expiry พร้อม buffer 5 นาที
 */
class TokenManager
{
    private const SESSION_ACCESS_TOKEN  = 'backend_access_token';
    private const SESSION_TOKEN_TYPE    = 'backend_token_type';
    private const SESSION_EXPIRES_IN    = 'backend_expires_in';
    private const SESSION_REFRESH_TOKEN = 'backend_refresh_token';
    private const SESSION_EXPIRES_AT    = 'backend_token_expires_at';

    /** Buffer ก่อน token หมดอายุ (นาที) — refresh ล่วงหน้า */
    private const EXPIRY_BUFFER_MINUTES = 5;

    /**
     * ดึง access token จาก session
     */
    public function getToken(): ?string
    {
        $token = session(self::SESSION_ACCESS_TOKEN);

        return \is_string($token) ? $token : null;
    }

    /**
     * ดึง refresh token จาก session
     */
    public function getRefreshToken(): ?string
    {
        $token = session(self::SESSION_REFRESH_TOKEN);

        return \is_string($token) ? $token : null;
    }

    /**
     * ตรวจสอบว่า token ใกล้หมดอายุหรือหมดอายุแล้ว (รวม buffer)
     */
    public function isExpired(): bool
    {
        $expiresAt = session(self::SESSION_EXPIRES_AT);

        if (! \is_string($expiresAt) || $expiresAt === '') {
            return false;
        }

        return now()->addMinutes(self::EXPIRY_BUFFER_MINUTES)->gte(Carbon::parse($expiresAt));
    }

    /**
     * บันทึก token data ลง session พร้อม calculated expiry timestamp
     *
     * @param array<string, mixed> $data
     */
    public function store(array $data): void
    {
        $rawExpiry = $data['expires_in'] ?? null;
        $expiresIn = \is_int($rawExpiry) ? $rawExpiry : null;

        session([
            self::SESSION_ACCESS_TOKEN  => $data['access_token'] ?? null,
            self::SESSION_TOKEN_TYPE    => $data['token_type'] ?? 'Bearer',
            self::SESSION_EXPIRES_IN    => $expiresIn,
            self::SESSION_REFRESH_TOKEN => $data['refresh_token'] ?? session(self::SESSION_REFRESH_TOKEN),
            self::SESSION_EXPIRES_AT    => $expiresIn
                ? now()->addSeconds($expiresIn)->toIso8601String()
                : null,
        ]);
    }

    /**
     * ลบ token data ทั้งหมดออกจาก session
     */
    public function clear(): void
    {
        session()->forget([
            self::SESSION_ACCESS_TOKEN,
            self::SESSION_TOKEN_TYPE,
            self::SESSION_EXPIRES_IN,
            self::SESSION_REFRESH_TOKEN,
            self::SESSION_EXPIRES_AT,
        ]);
    }
}
