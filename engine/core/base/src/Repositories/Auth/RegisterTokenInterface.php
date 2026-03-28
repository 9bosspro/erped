<?php

declare(strict_types=1);

namespace Core\Base\Repositories\Auth;

use App\Models\RegisterToken;
use Core\Base\Repositories\Interfaces\BaseRepositoryInterface;

/**
 * RegisterTokenInterface — สัญญา Repository สำหรับจัดการ RegisterToken
 *
 * Extend BaseRepositoryInterface เพื่อรับ operations มาตรฐานทั้งหมด
 *
 * RegisterToken ใช้สำหรับยืนยัน email ก่อนลงทะเบียน:
 * 1. สร้าง token → ส่งเมล → user คลิก link (ใช้ JWT ที่มี token ID)
 * 2. ตรวจสอบ token → ลงทะเบียน → revoke token
 *
 * Token มี 2 สถานะที่ทำให้ใช้งานไม่ได้:
 * - `revoked = true`    → ถูกใช้ไปแล้ว
 * - `expires_at < now`  → หมดอายุ
 */
interface RegisterTokenInterface extends BaseRepositoryInterface
{
    // =========================================================================
    // Token Validation
    // =========================================================================

    /**
     * ค้นหา token ที่ยังใช้งานได้ (ไม่ revoked และไม่หมดอายุ)
     *
     * คืนค่า null ถ้า token ไม่พบ, ถูก revoke แล้ว, หรือหมดอายุแล้ว
     * Select เฉพาะ id กับ email เพื่อลด payload
     *
     * @param  string  $id  UUID ของ RegisterToken
     * @return RegisterToken|null token ที่ valid หรือ null ถ้าไม่พบ/หมดอายุ
     */
    public function findValidToken(string $id): ?RegisterToken;

    // =========================================================================
    // Token Query
    // =========================================================================

    /**
     * ค้นหา token ที่ยังใช้งานได้สำหรับ email ที่ระบุ
     *
     * ใช้สำหรับตรวจสอบ duplicate ก่อนสร้าง token ใหม่
     * เพื่อป้องกัน token flooding และ email spam
     *
     * @param  string  $email  อีเมลที่ต้องการค้นหา
     * @return RegisterToken|null token ที่ valid หรือ null ถ้าไม่พบ
     */
    public function findActiveByEmail(string $email): ?RegisterToken;

    /**
     * สร้าง RegisterToken ใหม่สำหรับ email ที่ระบุ
     *
     * Token จะหมดอายุหลัง $ttlSeconds วินาทีนับจากเวลาสร้าง
     *
     * @param  string  $email  อีเมลที่ต้องการลงทะเบียน
     * @param  int  $ttlSeconds  อายุของ token (วินาที, default: 86400 = 24 ชม.)
     * @return RegisterToken token ที่สร้างใหม่
     */
    public function createToken(string $email, int $ttlSeconds = 86400): RegisterToken;

    // =========================================================================
    // Token Lifecycle
    // =========================================================================

    /**
     * ยกเลิกการใช้งาน token (revoke) — ทำให้ใช้ไม่ได้อีก
     *
     * ตรวจสอบสถานะก่อน revoke เพื่อป้องกัน race condition:
     * - ต้องไม่ถูก revoked ไปแล้ว
     * - ต้องยังไม่หมดอายุ
     *
     * @param  string  $id  UUID ของ RegisterToken
     * @return bool true ถ้า revoke สำเร็จ, false ถ้า token ไม่พบ/ไม่ valid
     */
    public function revokeToken(string $id): bool;
}
