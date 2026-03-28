<?php

declare(strict_types=1);

namespace Core\Base\Actions\User;

use App\Models\User;
use Core\Base\Repositories\User\UserInterface;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * RegisterNewUserActions — Action สำหรับสร้าง User record ใหม่อย่างง่าย
 *
 * ใช้สำหรับกรณีที่ต้องการสร้าง User โดยไม่ผ่านกระบวนการลงทะเบียนแบบเต็ม
 * สำหรับ flow การลงทะเบียนแบบสมบูรณ์ (JWT token + People) ใช้ RegistrationService แทน
 *
 * @see \Core\Base\Services\User\RegistrationService  สำหรับ flow ลงทะเบียนเต็มรูปแบบ
 */
class RegisterNewUserActions
{
    /**
     * @param  UserInterface  $userRepository  Repository สำหรับจัดการ User data
     */
    public function __construct(
        private readonly UserInterface $userRepository,
    ) {}

    /**
     * สร้าง User ใหม่ในระบบ
     *
     * ตรวจสอบ email ซ้ำก่อนสร้าง — throw RuntimeException ถ้ามีอยู่แล้ว
     * password จะถูก hash ด้วย bcrypt ก่อนบันทึก
     *
     * @param  string  $name  ชื่อผู้ใช้
     * @param  string  $email  อีเมล (ต้องไม่ซ้ำในระบบ)
     * @param  string  $password  รหัสผ่าน (plain text — จะถูก hash อัตโนมัติ)
     * @return User User model ที่สร้างใหม่ (พร้อม id จาก DB)
     *
     * @throws RuntimeException เมื่อ email มีอยู่ในระบบแล้ว
     */
    public function execute(string $name, string $email, string $password): User
    {
        if ($this->userRepository->emailExists($email)) {
            throw new RuntimeException("อีเมล [{$email}] มีผู้ใช้งานในระบบแล้ว");
        }

        /** @var User */
        return $this->userRepository->create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);
    }
}
