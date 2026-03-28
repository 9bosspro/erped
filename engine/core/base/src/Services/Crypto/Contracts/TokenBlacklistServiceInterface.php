<?php

declare(strict_types=1);

namespace Core\Base\Services\Crypto\Contracts;

/**
 * TokenBlacklistServiceInterface — สัญญาสำหรับ JWT Token Blacklist Service
 *
 * ครอบคลุม:
 *  - Revoke token ด้วย JTI หรือ token string โดยตรง
 *  - ตรวจสอบว่า token ถูก revoke หรือไม่
 *  - Un-revoke (restore) token
 */
interface TokenBlacklistServiceInterface
{
    /**
     * Revoke token ด้วย JTI
     *
     * @param  string  $jti  JWT ID ที่ต้องการ revoke
     * @param  int  $ttl  อายุ key ใน Redis (วินาที)
     */
    public function revoke(string $jti, int $ttl): void;

    /**
     * Revoke token จาก token string โดยตรง
     *
     * @param  string  $token  JWT token string
     * @return bool true = revoke สำเร็จ, false = token ไม่ถูกต้อง
     */
    public function revokeToken(string $token): bool;

    /**
     * ตรวจสอบว่า JTI ถูก revoke หรือไม่
     *
     * @param  string  $jti  JWT ID
     * @return bool true = ถูก revoke แล้ว
     */
    public function isRevoked(string $jti): bool;

    /**
     * ตรวจสอบว่า token string ถูก revoke หรือไม่
     *
     * @param  string  $token  JWT token string
     * @return bool true = ถูก revoke หรือ parse ไม่ได้
     */
    public function isTokenRevoked(string $token): bool;

    /**
     * ลบ JTI ออกจาก blacklist (un-revoke)
     *
     * @param  string  $jti  JWT ID
     */
    public function restore(string $jti): void;
}
