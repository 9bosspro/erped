<?php

declare(strict_types=1);

namespace Core\Base\Services\Security\Contracts;

/**
 * TokenBlacklistServiceInterface — สัญญาการใช้งานสำหรับ Token Blacklist Service
 *
 * ═══════════════════════════════════════════════════════════════
 *  วัตถุประสงค์: จัดการการ Revoke และตรวจสอบสถานะ Blacklist ของ JWT Token
 * ═══════════════════════════════════════════════════════════════
 */
interface TokenBlacklistServiceInterface
{
    /**
     * สั่ง Revoke Token โดยใช้ JTI (JWT ID)
     *
     * @param  string  $jti  รหัสประจำตัว Token
     * @param  int  $ttl  ระยะเวลาที่ต้องการเก็บไว้ในบัญชีดำ (วินาที)
     */
    public function revoke(string $jti, int $ttl): void;

    /**
     * สั่ง Revoke Token จากชุดตัวอักษร Token โดยตรง
     *
     * @param  string  $token  ชุดตัวอักษร JWT Token
     * @return bool คืนค่า true หากดำเนินการสำเร็จ
     */
    public function revokeToken(string $token): bool;

    /**
     * ตรวจสอบว่า JTI นี้ถูก Revoke ไปแล้วหรือไม่
     *
     * @param  string  $jti  รหัสประจำตัว Token
     * @return bool คืนค่า true หากอยู่ในบัญชีดำ
     */
    public function isRevoked(string $jti): bool;

    /**
     * ตรวจสอบว่า Token นี้ถูก Revoke ไปแล้วหรือไม่
     *
     * @param  string  $token  ชุดตัวอักษร JWT Token
     * @return bool คืนค่า true หากถูก Revoke หรือ Token ไม่ถูกต้อง
     */
    public function isTokenRevoked(string $token): bool;

    /**
     * ยกเลิกการ Revoke (คืนสถานะ Token)
     *
     * @param  string  $jti  รหัสประจำตัว Token
     */
    public function restore(string $jti): void;
}
