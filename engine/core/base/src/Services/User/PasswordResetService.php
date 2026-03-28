<?php

declare(strict_types=1);

namespace Core\Base\Services\User;

use App\Models\ForgetPasswordToken;
use App\Models\User;
use Core\Base\Support\Helpers\Crypto\JwtHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * PasswordResetService — จัดการ password reset flow ทั้งหมด
 *
 * Flow การทำงาน:
 * 1. `requestPasswordResetToken(email)` → ตรวจสอบ + สร้าง ForgetPasswordToken + คืน JWT
 * 2. `resetPasswordByToken(jwt, password)` → decode JWT → ตรวจสอบ token → reset password
 * 3. `changePasswordByAdmin(email, password)` → Admin เปลี่ยนรหัสผ่านแทนผู้ใช้
 *
 * Business logic ทั้งหมดถูก extract จาก ChangePasswordController
 * เพื่อให้ Controller บางเบาและทดสอบได้ง่ายขึ้น
 */
class PasswordResetService
{
    private readonly int $tokenTtl;

    private readonly string $jwtKey;

    /**
     * @param  JwtHelper  $jwtService  Service สำหรับจัดการ JWT
     */
    public function __construct(
        private readonly JwtHelper $jwtService,
    ) {
        $this->tokenTtl = (int) config('auth.jwt.delay', 86400);
        $this->jwtKey = (string) config('auth.jwt.key', '');
    }

    // =========================================================================
    // Password Reset Flow
    // =========================================================================

    /**
     * ขอ ForgetPasswordToken สำหรับ email ที่ระบุ
     *
     * ถ้า token ที่ยังใช้งานได้มีอยู่แล้ว จะ reject พร้อม remaining time
     * เพื่อป้องกัน token flooding และ email spam
     *
     * @param  string  $email  อีเมลของผู้ใช้
     * @return array{status: bool, message: string, data: mixed, code: int}
     */
    public function requestPasswordResetToken(string $email): array
    {
        // ตรวจสอบ token ที่ยังไม่หมดอายุ — ป้องกันการขอซ้ำ
        $existing = ForgetPasswordToken::where('email', $email)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->select(['id', 'expires_at'])
            ->first();

        if ($existing) {
            return $this->fail(
                'กรุณาตรวจสอบอีเมล หรือไปดำเนินการก่อนจะหมดเวลา หรือติดต่อผู้ดูแลระบบ',
                422,
                ['remaining_time' => remaining_time_text($existing->expires_at)],
            );
        }

        // สร้าง ForgetPasswordToken ใหม่
        $tokenRecord = new ForgetPasswordToken;
        $tokenRecord->email = $email;
        $tokenRecord->data = json_encode_th([]);
        $tokenRecord->expires_at = now()->addSeconds($this->tokenTtl)->toDateTimeString();
        $tokenRecord->save();

        return $this->success('สำเร็จ', [
            'token' => $this->jwtService->buildCustomToken(
                data: $tokenRecord->id,
                ttl: $this->tokenTtl,
            ),
        ], 200);
    }

    /**
     * Reset รหัสผ่านด้วย JWT token ที่ได้รับจาก email
     *
     * ลำดับ: decode JWT → ตรวจสอบ ForgetPasswordToken → หา user → update password → revoke token
     *
     * ⚠️ Validation (password format) ต้องทำที่ Controller ก่อนเรียก method นี้
     *
     * @param  string|null  $jwt  JWT Bearer token (null = reject ทันที)
     * @param  string  $password  รหัสผ่านใหม่ (plain text — จะถูก hash ที่นี่)
     * @return array{status: bool, message: string, data: mixed, code: int}
     */
    public function resetPasswordByToken(?string $jwt, string $password): array
    {
        if (empty($jwt)) {
            return $this->fail('คุณไม่มีสิทธิ์ดำเนินการ ต้องขอโทเคนก่อน', 401);
        }

        $parsed = $this->jwtService->parseSafe($jwt);
        if ($parsed === null) {
            return $this->fail('โทเคนไม่ถูกต้องหรือหมดอายุแล้ว', 401);
        }

        $tokenDataId = $parsed->claims()->get('data');
        if (empty($tokenDataId)) {
            return $this->fail('รูปแบบโทเคนไม่ถูกต้อง', 401);
        }

        // ค้นหา ForgetPasswordToken ที่ยังใช้งานได้ (ไม่ revoked + ไม่หมดอายุ)
        $tokenRecord = ForgetPasswordToken::where('id', $tokenDataId)
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->select(['id', 'email'])
            ->first();

        if (! $tokenRecord) {
            return $this->fail('โทเคนไม่ถูกต้อง หมดอายุ หรือถูกใช้งานแล้ว', 422);
        }

        if (empty($tokenRecord->email)) {
            return $this->fail('ไม่มีอีเมลในระบบ กรุณาขอโทเคนใหม่', 422);
        }

        $user = User::where('email', $tokenRecord->email)->first();
        if (! $user) {
            return $this->fail('ไม่พบผู้ใช้งาน', 422);
        }

        // Update password + revoke token ใน transaction เดียว
        // ป้องกัน: password เปลี่ยนแล้วแต่ token ยังไม่ revoke → reuse ได้
        DB::transaction(function () use ($user, $password, $tokenRecord): void {
            $user->update(['password' => Hash::make($password)]);
            ForgetPasswordToken::where('id', $tokenRecord->id)->update(['revoked' => true]);
        });

        return $this->success('เปลี่ยนรหัสผ่านสำเร็จ', ['user' => $user], 201);
    }

    /**
     * Admin เปลี่ยนรหัสผ่านให้ผู้ใช้โดยตรง (ไม่ต้องใช้ token)
     *
     * ⚠️ ต้องมี admin authorization ที่ controller layer ก่อนเรียก method นี้
     *
     * @param  string  $email  อีเมลของผู้ใช้
     * @param  string  $password  รหัสผ่านใหม่ (plain text — จะถูก hash ที่นี่)
     * @return array{status: bool, message: string, data: mixed, code: int}
     */
    public function changePasswordByAdmin(string $email, string $password): array
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            return $this->fail('ไม่พบผู้ใช้งาน', 404);
        }

        $user->update(['password' => Hash::make($password)]);

        return $this->success('เปลี่ยนรหัสผ่านสำเร็จ', true, 200);
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * สร้าง response สำเร็จในรูปแบบมาตรฐาน
     *
     * @param  array<string, mixed>|mixed  $data
     * @return array{status: true, message: string, data: mixed, code: int}
     */
    private function success(string $message, mixed $data, int $code): array
    {
        return ['status' => true, 'message' => $message, 'data' => $data, 'code' => $code];
    }

    /**
     * สร้าง response ล้มเหลวในรูปแบบมาตรฐาน
     *
     * @param  array<string, mixed>|mixed  $data
     * @return array{status: false, message: string, data: mixed, code: int}
     */
    private function fail(string $message, int $code, mixed $data = []): array
    {
        return ['status' => false, 'message' => $message, 'data' => $data, 'code' => $code];
    }
}
