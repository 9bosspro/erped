<?php

declare(strict_types=1);

namespace Core\Base\Services\Crypto;

use Core\Base\Services\Crypto\Contracts\TokenBlacklistServiceInterface;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Illuminate\Support\Facades\Redis;

/**
 * TokenBlacklistService — จัดการ revoke/blacklist JWT token ผ่าน Redis
 *
 * หลักการ:
 * - เก็บ JTI (JWT ID) ที่ถูก revoke ใน Redis พร้อม TTL = token expiration
 * - เมื่อ token หมดอายุ Redis จะลบ key อัตโนมัติ → ไม่เปลือง memory
 * - ใช้กับ AuthenticateJwt middleware เพื่อตรวจสอบ token ที่ถูก revoke
 *
 * ⚠️ ต้องมี Redis พร้อมใช้งาน (predis หรือ phpredis)
 *
 * Usage:
 * ```php
 * $blacklist = app(TokenBlacklistService::class);
 * $blacklist->revoke($jti, 3600);     // revoke 1 ชั่วโมง
 * $blacklist->isRevoked($jti);        // true
 * $blacklist->revokeToken($tokenString); // revoke จาก token string โดยตรง
 * ```
 */
final class TokenBlacklistService implements TokenBlacklistServiceInterface
{
    /** @var string prefix สำหรับ Redis key */
    private const PREFIX = 'jwt:blacklist:';

    /** @var string Redis connection ที่ใช้ */
    private readonly string $connection;

    public function __construct(
        private readonly JwtHelper $jwtService,
    ) {
        $this->connection = (string) config('crypto.jwt.blacklist_connection', 'default');
    }

    /**
     * Revoke token ด้วย JTI
     *
     * @param  string  $jti  JWT ID ที่ต้องการ revoke
     * @param  int  $ttl  อายุ key ใน Redis (วินาที) — ควรเท่ากับ token TTL
     */
    public function revoke(string $jti, int $ttl): void
    {
        if ($jti === '' || $ttl <= 0) {
            return;
        }

        Redis::connection($this->connection)
            ->setex(self::PREFIX . $jti, $ttl, '1');
    }

    /**
     * Revoke token จาก token string โดยตรง
     *
     * ดึง JTI และ expiration จาก token แล้ว revoke
     * คำนวณ TTL จาก expiration time ที่เหลือ
     *
     * @param  string  $token  JWT token string
     * @return bool true = revoke สำเร็จ, false = token ไม่ถูกต้อง
     */
    public function revokeToken(string $token): bool
    {
        $parsed = $this->jwtService->parseUnvalidated($token);

        if ($parsed === null) {
            return false;
        }

        $jti = $parsed->claims()->get('jti');
        $exp = $parsed->claims()->get('exp');

        if (! is_string($jti) || $jti === '') {
            return false;
        }

        // คำนวณ TTL ที่เหลือ (ถ้า expire แล้ว ไม่ต้อง revoke)
        $ttl = $exp instanceof \DateTimeImmutable
            ? $exp->getTimestamp() - time()
            : 3600; // fallback 1 ชั่วโมง

        if ($ttl <= 0) {
            return false; // token หมดอายุแล้ว
        }

        $this->revoke($jti, $ttl);

        return true;
    }

    /**
     * ตรวจสอบว่า JTI ถูก revoke หรือไม่
     *
     * @param  string  $jti  JWT ID
     * @return bool true = ถูก revoke แล้ว
     */
    public function isRevoked(string $jti): bool
    {
        if ($jti === '') {
            return true;
        }

        return (bool) Redis::connection($this->connection)
            ->exists(self::PREFIX . $jti);
    }

    /**
     * ตรวจสอบว่า token string ถูก revoke หรือไม่
     *
     * @param  string  $token  JWT token string
     * @return bool true = ถูก revoke หรือ parse ไม่ได้
     */
    public function isTokenRevoked(string $token): bool
    {
        $parsed = $this->jwtService->parseUnvalidated($token);

        if ($parsed === null) {
            return true;
        }

        $jti = $parsed->claims()->get('jti');

        return ! is_string($jti) || $this->isRevoked($jti);
    }

    /**
     * ลบ JTI ออกจาก blacklist (un-revoke)
     *
     * ⚠️ ใช้ด้วยความระมัดระวัง — ควรมี admin authorization
     *
     * @param  string  $jti  JWT ID
     */
    public function restore(string $jti): void
    {
        if ($jti === '') {
            return;
        }

        Redis::connection($this->connection)
            ->del(self::PREFIX . $jti);
    }
}
