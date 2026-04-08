<?php

namespace App\Services;

use App\DTOs\ProfileUpdateData;
use App\Events\AccountDeleted;
use App\Events\ProfileUpdated;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\DB;

/**
 * คลาสจัดการลอจิกต่างๆ ที่เกี่ยวข้องกับโปรไฟล์ผู้ใช้งาน (Profile Service)
 * ออกแบบตามหลักการ SOLID (Single Responsibility Principle)
 */
class ProfileService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * อัปเดตข้อมูลโปรไฟล์ผู้ใช้งาน และจัดการกรณีการเปลี่ยนอีเมลด้วย Database Transactions
     *
     * @param User $user ออบเจ็กต์ผู้ใช้งาน
     * @param ProfileUpdateData $data วัตถุเก็บข้อมูลที่ได้รับจากการกรอกฟอร์ม (DTO)
     * @return User คืนค่าออบเจ็กต์ผู้ใช้หลังอัปเดตผ่าน Repository
     */
    public function updateProfile(User $user, ProfileUpdateData $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            // ตรวจสอบฟิลด์ที่มีการเปลี่ยนแปลงโดยใช้ array_filter แทน if รัวๆ
            $changedFields = array_filter(
                ['name', 'email'],
                fn (string $field) => $user->$field !== $data->$field
            );

            // คืนค่ากลับทันทีและไม่ต้องทำอะไร หากไม่มีการปรับเปลี่ยนใดๆ
            if (empty($changedFields)) {
                return $user;
            }

            $emailChanged = in_array('email', $changedFields, true);

            // ส่งข้อมูลไปยัง Repository เพื่ออัปเดต
            $user = $this->userRepository->update($user, [
                'name' => $data->name,
                'email' => $data->email,
            ]);

            // หากมีการเปลี่ยนอีเมล ให้ตั้งสถานะเป็น Unverified และส่ง verification email
            if ($emailChanged) {
                $user = $this->userRepository->markEmailAsUnverified($user);

                if ($user instanceof MustVerifyEmail) {
                    $user->sendEmailVerificationNotification();
                }
            }

            // กระจาย Event แจ้งการอัปเดตไปยังระบบอื่นๆ 
            ProfileUpdated::dispatch($user, array_values($changedFields));

            return $user;
        });
    }

    /**
     * ดำเนินการลบบัญชีผู้ใช้ออกจากระบบถาวร (Delete Account Data)
     *
     * @param User $user ออบเจ็กต์ผู้ใช้งาน
     * @return void
     */
    public function deleteAccount(User $user): void
    {
        DB::transaction(function () use ($user) {
            $userId = $user->id;
            $email = $user->email;

            // มอบหน้าที่การลบให้ Repository จัดการ
            $this->userRepository->delete($user);

            // กระจาย Event แจ้งให้ระบบประมวลผลต่อ (Background Async Processing)
            AccountDeleted::dispatch($userId, $email);
        });
    }
}
