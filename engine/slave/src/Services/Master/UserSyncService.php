<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use RuntimeException;

/**
 * UserSyncService — ประสานข้อมูลผู้ใช้งานระหว่าง Master Server กับ Local Database
 *
 * หน้าที่:
 *  - ดึงข้อมูลผู้ใช้จาก Master Server response
 *  - สร้างหรืออัพเดท user ในฐานข้อมูลท้องถิ่น
 *  - คืนค่า User object สำหรับ Local auth
 */
class UserSyncService
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * ประสานข้อมูลผู้ใช้จาก Master Server ลง Local DB
     *
     * @param  array<string, mixed>  $backendData  ข้อมูลผู้ใช้จาก Master Server
     * @return User Local user instance
     */
    public function sync(array $backendData): User
    {
        $backendUserId = $backendData['id'] ?? null;
        $email = $backendData['email'] ?? null;

        if (! \is_scalar($backendUserId) || ! \is_scalar($email)) {
            throw new RuntimeException('Invalid backend user data: missing id or email');
        }

        $backendUserId = (string) $backendUserId;
        $email = (string) $email;

        $user = User::firstOrNew(['backend_user_id' => $backendUserId]);

        $user->fill([
            'backend_user_id' => $backendUserId,
            'email' => $email,
            'name' => $backendData['name'] ?? $backendData['name_th'] ?? '',
            'username' => $backendData['username'] ?? '',
            'name_th' => $backendData['name_th'] ?? '',
            'name_en' => $backendData['name_en'] ?? '',
            'nickname_th' => $backendData['nickname_th'] ?? '',
            'nickname_en' => $backendData['nickname_en'] ?? '',
            'is_active' => (bool) ($backendData['is_active'] ?? true),
            'metadata' => $backendData['metadata'] ?? [],
        ]);

        $user->save();

        return $user;
    }
}
