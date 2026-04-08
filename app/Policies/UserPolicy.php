<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * UserPolicy — Authorization policy สำหรับจัดการ resource ของ User
 *
 * ใช้ตรวจสอบว่า authenticated user มีสิทธิ์จัดการ user resource นั้น ๆ หรือไม่
 * ปัจจุบัน: user จัดการได้เฉพาะ resource ของตัวเอง
 */
class UserPolicy
{
    /**
     * ดู/แก้ไข/ลบ profile ของตัวเอง
     * ใช้ครอบคลุม: viewProfile, updateProfile, deleteAccount, updatePassword
     */
    public function manage(User $authUser, User $user): bool
    {
        return $authUser->id === $user->id;
    }
}
