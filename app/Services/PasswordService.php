<?php

namespace App\Services;

use App\DTOs\PasswordUpdateData;
use App\Events\PasswordChanged;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

/**
 * คลาสจัดการลอจิกต่างๆ ที่เกี่ยวข้องกับรหัสผ่านผู้ใช้งาน (Password Service)
 * ออกแบบตามหลักการ SOLID (Single Responsibility Principle)
 */
class PasswordService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * อัปเดตรหัสผ่านใหม่ให้กับผู้ใช้งาน
     *
     * @param User $user ออบเจ็กต์ผู้ใช้งาน
     * @param PasswordUpdateData $data ข้อมูลรหัสผ่านใหม่ (DTO)
     * @return void
     */
    public function updatePassword(User $user, PasswordUpdateData $data): void
    {
        // อัปเดตรหัสผ่านพร้อมทำ Hashing เพื่อความปลอดภัย (Security Best Practice)
        $this->userRepository->update($user, [
            'password' => Hash::make($data->password),
        ]);

        // กระจาย Event แจ้งการเปลี่ยนรหัสผ่าน (เช่น ส่งอีเมล Security Alert)
        PasswordChanged::dispatch($user);
    }
}
