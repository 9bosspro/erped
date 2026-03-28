<?php

namespace App\Repositories\Contracts;

use App\Models\User;

/**
 * อินเตอร์เฟสสำหรับจัดการข้อมูลผู้ใช้งาน (User Repository Interface)
 * กำหนดสัญญาการทำงานและแยกตรรกะการเข้าถึงฐานข้อมูล (Data Access Layer)
 * ออกจากส่วนอื่นของระบบตามหลักการ Dependency Inversion Principle (SOLID)
 */
interface UserRepositoryInterface
{
    /**
     * ค้นหาผู้ใช้งานจากรหัสอ้างอิง (ID)
     *
     * @param int $id รหัสอ้างอิงผู้ใช้งาน
     * @return User|null โมเดลผู้ใช้งาน หรือ null หากไม่พบ
     */
    public function findById(int $id): ?User;

    /**
     * ค้นหาผู้ใช้งานจากอีเมล (Email)
     *
     * @param string $email อีเมลผู้ใช้งาน
     * @return User|null โมเดลผู้ใช้งาน หรือ null หากไม่พบ
     */
    public function findByEmail(string $email): ?User;

    /**
     * สร้างผู้ใช้งานใหม่ในระบบ
     *
     * @param array $data ข้อมูลสำหรับสร้างผู้ใช้งาน
     * @return User โมเดลผู้ใช้งานที่ถูกสร้างขึ้น
     */
    public function create(array $data): User;

    /**
     * อัปเดตข้อมูลผู้ใช้งานและล้างแคชเพื่อให้ข้อมูลเป็นปัจจุบันเสมอ
     *
     * @param User $user โมเดลผู้ใช้งานที่ต้องการอัปเดต
     * @param array $data ข้อมูลใหม่ที่ต้องการเปลี่ยนแปลง
     * @return User โมเดลผู้ใช้งานที่อัปเดตเรียบร้อยแล้ว
     */
    public function update(User $user, array $data): User;

    /**
     * ลบข้อมูลผู้ใช้งานออกจากระบบถาวร
     *
     * @param User $user โมเดลผู้ใช้งานที่ต้องการลบ
     * @return void
     */
    public function delete(User $user): void;

    /**
     * ยกเลิกสถานะการยืนยันอีเมลของผู้ใช้งาน (เซ็ตเป็น Unverified)
     *
     * @param User $user โมเดลผู้ใช้งาน
     * @return User โมเดลผู้ใช้งานที่ถูกปรับสถานะเรียบร้อยแล้ว
     */
    public function markEmailAsUnverified(User $user): User;
}
