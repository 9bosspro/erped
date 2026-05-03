<?php

namespace App\Http\Controllers\Settings;

use App\DTOs\ProfileUpdateData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Services\ProfileService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * คลาสควบคุมการจัดการโปรไฟล์ส่วนตัวของผู้ใช้งาน (Profile Controller)
 * มีหน้าที่รับ Request ชำระลอจิกเบื้องต้นและมอบหมายลอจิกธุรกิจให้ Service จัดการตามหลักการ SOLID
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profileService,
    ) {}

    /**
     * แสดงหน้าการตั้งค่าโปรไฟล์ผู้ใช้
     *
     * @param Request $request
     * @return Response ส่งการเรนเดอร์กลับไปยัง UI ฝั่ง Frontend (Inertia.js)
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * รับคำร้องขอและอัปเดตข้อมูลรายละเอียดโปรไฟล์
     *
     * @param ProfileUpdateRequest $request ฟอร์มรีเควสต์และ Data validation
     * @return RedirectResponse ส่งหน้ากลับไปยังโปรไฟล์หลังบันทึก
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            abort(401);
        }

        $this->authorize('manage', $user);

        $this->profileService->updateProfile(
            $user,
            ProfileUpdateData::fromRequest($request),
        );

        return to_route('profile.edit');
    }

    /**
     * ดำเนินการลบบัญชีผู้ใช้ถาวร (Delete Account)
     *
     * @param Request $request
     * @return RedirectResponse รีไดเร็กต์ไปหน้าแรก
     */
    public function destroy(Request $request): RedirectResponse
    {
        // ตรวจสอบความถูกต้องของรหัสผ่านปัจจุบันเพื่อยืนยันสิทธิ์ก่อนจัดการบัญชีเพื่อป้องกันช่องโหว่ (Security)
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        if (! $user instanceof \App\Models\User) {
            abort(401);
        }

        $this->authorize('manage', $user);

        Auth::logout();

        $this->profileService->deleteAccount($user);

        // ทำลายเซสชัน ป้องกันการย้อนตึงข้ามมาใหม่ (Session Fixation Cleanup)
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
