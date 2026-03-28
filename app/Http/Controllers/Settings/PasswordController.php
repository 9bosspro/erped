<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Services\PasswordService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use App\DTOs\PasswordUpdateData;

/**
 * คลาสควบคุมการจัดการหน้ารหัสผ่านผู้ใช้งาน (Password Controller)
 * มีหน้าที่รับ Request ชำระลอจิกเบื้องต้นและมอบหมายลอจิกธุรกิจให้ Service จัดการตามหลักการ SOLID
 */
class PasswordController extends Controller
{
    public function __construct(
        private readonly PasswordService $passwordService,
    ) {}

    /**
     * แสดงแบบฟอร์มแก้ไขรหัสผ่าน
     *
     * @return Response คืนค่าข้อมูลฟอร์มไปยังหน้า UI (Inertia)
     */
    public function edit(): Response
    {
        return Inertia::render('settings/password');
    }

    /**
     * ดำเนินการอัปเดตรหัสผ่านใหม่ตามคำร้องขอ (Request)
     *
     * @param PasswordUpdateRequest $request คลาส Form Request จัดการ Validate ข้อมูล
     * @return RedirectResponse คืนค่าหน้าเว็บไซต์กลับไปหน้าเดิม (Back)
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        // ส่งต่อไปยัง PasswordService เพื่อจัดการเรื่องของฐานข้อมูล โดยใช้ DTO
        $this->passwordService->updatePassword(
            $request->user(),
            PasswordUpdateData::fromRequest($request),
        );

        return back();
    }
}
