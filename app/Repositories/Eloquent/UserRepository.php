<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Cache;

/**
 * คลาสจัดการข้อมูลผู้ใช้งานที่เชื่อมต่อกับฐานข้อมูลผ่าน Eloquent (User Repository)
 * รองรับการแคช (Caching) เพื่อลดภาระของฐานข้อมูลและเพิ่มประสิทธิภาพ (High Performance)
 */
class UserRepository implements UserRepositoryInterface
{
    /**
     * ค้นหาผู้ใช้งานจากรหัสอ้างอิงและแคชข้อมูลไว้ (Cache Layer)
     */
    public function findById(int $id): ?User
    {
        $ttl = config('myapp.cache.user_ttl', 3600);

        return Cache::remember(
            "user:{$id}",
            \is_int($ttl) ? $ttl : 3600,
            fn () => User::find($id),
        );
    }

    /**
     * ค้นหาผู้ใช้งานจากอีเมลและแคชข้อมูลไว้
     */
    public function findByEmail(string $email): ?User
    {
        $ttl = config('myapp.cache.user_ttl', 3600);

        return Cache::remember(
            "user:email:{$email}",
            \is_int($ttl) ? $ttl : 3600,
            fn () => User::where('email', $email)->first(),
        );
    }

    /**
     * ดำเนินการสร้างผู้ใช้งานใหม่ในระบบ
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): User
    {
        return User::create($data);
    }

    /**
     * ดำเนินการอัปเดตรายละเอียดของผู้ใช้และล้างแคช (Cache Invalidation)
     *
     * @param array<string, mixed> $data
     */
    public function update(User $user, array $data): User
    {
        $oldEmail = $user->email;

        // ดำเนินการอัปเดตข้อมูลผู้ใช้งานและบันทึกลงฐานข้อมูลทันที
        $user->update($data);

        // ล้างแคชเพื่อให้ข้อมูลเป็นปัจจุบัน
        $this->invalidateCache($user, $oldEmail);

        return $user;
    }

    /**
     * ดำเนินการลบผู้ใช้ออกจากระบบถาวรพร้อมทั้งล้างแคช
     */
    public function delete(User $user): void
    {
        $this->invalidateCache($user);
        $user->delete();
    }

    /**
     * ยกเลิกสถานะการยืนยันอีเมลของผู้ใช้ (เปลี่ยนกลับเป็น Unverified) อย่างปลอดภัย
     */
    public function markEmailAsUnverified(User $user): User
    {
        $oldEmail = $user->email;

        // ใช้ forceFill ทะลุการข้ามการตรวจสอบของ $fillable และตั้งเป็น null
        $user->forceFill(['email_verified_at' => null])->save();
        $this->invalidateCache($user, $oldEmail);

        return $user;
    }

    /**
     * ฟังก์ชันล้างแคชเพื่อรับประกันความสดใหม่ของข้อมูล (Cache Invalidation Logic)
     *
     * @param User $user ออบเจ็กต์ผู้ใช้งาน
     * @param string|null $oldEmail อีเมลเดิมก่อนการเปลี่ยนแปลง
     */
    private function invalidateCache(User $user, ?string $oldEmail = null): void
    {
        Cache::forget("user:{$user->id}");
        Cache::forget("user:email:{$user->email}");

        if ($oldEmail && $oldEmail !== $user->email) {
            Cache::forget("user:email:{$oldEmail}");
        }
    }
}
