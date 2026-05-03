<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * UserSyncService — sync ข้อมูล user จาก Backend (pppportal) ลง local database
 *
 * ทำไมต้อง sync?
 *  - Fortify ต้องการ User model ใน local database สำหรับ session auth
 *  - Inertia ต้องการ auth.user ใน shared props
 *  - Backend (pppportal) คือ single source of truth ของ user data
 *
 * password ใน local database เป็น random hash (ไม่ใช้จริง — auth ผ่าน Backend เท่านั้น)
 */
class UserSyncService
{
    /**
     * Upsert user จาก Backend data ลง local database
     * ใช้ backend_user_id เป็น key สำหรับ upsert
     *
     * @param array<string, mixed> $backendUser
     */
    public function sync(array $backendUser): User
    {
        return User::updateOrCreate(
            ['backend_user_id' => $backendUser['id']],
            [
                'name'              => $backendUser['name_th']
                    ?? $backendUser['name_en']
                    ?? $backendUser['nickname_th']
                    ?? $backendUser['email'],
                'email'             => $backendUser['email'],
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(),
            ],
        );
    }
}
