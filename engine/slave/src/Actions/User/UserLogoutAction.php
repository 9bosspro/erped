<?php

declare(strict_types=1);

namespace Slave\Actions\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * UserLogoutAction — Action สำหรับดำเนินการออกจากระบบ (Logout)
 *
 * รับผิดชอบในการเพิกถอน Token ของผู้ใช้ (OAuth2 Passport)
 * และเคลียร์ Session/Cookie (Web Guard) เพื่อความปลอดภัยอย่างสมบูรณ์
 */
class UserLogoutAction
{
    /**
     * ดำเนินการออกจากระบบ
     *
     * @param  Request  $request  HTTP Request ที่มีข้อมูลผู้ใช้ล็อกอินอยู่
     * @return bool true หากดำเนินการสำเร็จ
     */
    public function execute(Request $request): bool
    {
        // 1. เพิกถอน Token ปัจจุบัน (สำหรับ API/Passport)
        $request->user()?->token()?->revoke();

        // 2. จัดการ Session ในกรณีใช้งานผ่าน Web Guard / State
        if ($request->hasSession()) {
            // ออกจากระบบ Web Guard
            Auth::guard('web')->logout();

            // ล้างค่า Session เก่าทิ้งเพื่อป้องกัน Session Fixation
            $request->session()->invalidate();

            // สร้าง CSRF Token ใหม่เพื่อความปลอดภัยสูงสุด
            $request->session()->regenerateToken();
        }

        return true;
    }
}
