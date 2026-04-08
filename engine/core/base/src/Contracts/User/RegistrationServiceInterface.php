<?php

declare(strict_types=1);

namespace Core\Base\Contracts\User;

use Core\Base\DTO\ServiceResult;
use Core\Base\DTO\User\RegistrationDTO;
use Throwable;

/**
 * RegistrationServiceInterface — สัญญาสำหรับ Service จัดการการลงทะเบียนผู้ใช้
 *
 * กำหนด contract ของ flow การลงทะเบียน:
 * 1. ขอ RegisterToken สำหรับ email (requestRegisterToken)
 * 2. ตรวจสอบ JWT token ที่ได้จากเมล (checkToken)
 * 3. ตรวจสอบ RegisterToken record ใน DB (checkRegisterToken)
 * 4. ดำเนินการสร้าง User จริง (executeRegistration)
 *
 * response format มาตรฐาน: ServiceResult
 */
interface RegistrationServiceInterface
{
    /**
     * ขอ RegisterToken สำหรับ email ที่ระบุ
     *
     * ถ้า token ที่ยังไม่หมดอายุมีอยู่แล้ว จะ reject พร้อม remaining_time
     * ถ้าไม่มี → สร้าง token ใหม่ + คืน JWT
     *
     * @param  string  $email  อีเมลที่ต้องการลงทะเบียน
     */
    public function requestRegisterToken(string $email): ServiceResult;

    /**
     * ตรวจสอบและ decode JWT token ของการลงทะเบียน
     *
     * ถ้า token เป็น null หรือ decode ไม่ได้ จะคืน status = false, code = 401
     * ถ้า RegisterToken ไม่ valid (revoked/expired) จะคืน status = false, code = 422
     *
     * @param  string|null  $token  JWT Bearer token (nullable — client อาจไม่ส่งมา)
     */
    public function checkToken(?string $token): ServiceResult;

    /**
     * ตรวจสอบว่า RegisterToken ID ยังใช้งานได้
     *
     * ตรวจสอบ: ไม่ revoked, ไม่หมดอายุ, email ยังไม่มีในระบบ
     *
     * @param  string  $registerTokenId  UUID ของ RegisterToken
     */
    public function checkRegisterToken(string $registerTokenId): ServiceResult;

    /**
     * ดำเนินการลงทะเบียนผู้ใช้ใหม่ภายใน DB transaction
     *
     * สร้าง People + User + revoke RegisterToken ในขั้นตอนเดียว
     * ถ้า step ใดล้มเหลว จะ rollback ทั้งหมดอัตโนมัติ
     *
     * ⚠️ ห้าม return password จาก $data ใน response
     *
     * @param  RegistrationDTO  $data  ข้อมูลผู้สมัคร
     * @param  string  $registerTokenId  UUID ของ RegisterToken ที่ใช้ลงทะเบียน
     * @param  string  $email  อีเมลของผู้ลงทะเบียน
     *
     * @throws Throwable เมื่อ transaction ล้มเหลว
     */
    public function executeRegistration(RegistrationDTO $data, string $registerTokenId, string $email): ServiceResult;
}
