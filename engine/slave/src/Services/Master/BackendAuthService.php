<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Slave\Contracts\Master\BackendAuthServiceInterface;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Contracts\Master\TokenFlow;
use Throwable;

/**
 * BackendAuthService — orchestrate Authentication flow ผ่าน Backend API (pppportal)
 *
 * Flow:
 *  1. User กรอก email/password ที่ Frontend
 *  2. Frontend ส่ง credentials ไป Backend API (/api/v1/auth/login)
 *  3. Backend ตรวจสอบ → คืน Passport token + user data
 *  4. UserSyncService sync user ลง local database (สำหรับ Fortify/Inertia)
 *  5. TokenManager บันทึก token ลง session
 *  6. Frontend login user ผ่าน session
 */
class BackendAuthService implements BackendAuthServiceInterface
{
    public function __construct(
        private readonly MasterClientInterface $apiClient,
        private readonly UserSyncService $userSyncService,
    ) {}

    /**
     * Login ผ่าน Master Server ด้วย Password Grant แล้วทำการ Sync User
     *
     * @return array{success: bool, message: string, user?: User, errors?: array<mixed>}
     */
    public function login(string $email, string $password): array
    {
        try {
            // ตรวจสอบรูปแบบ Email เบื้องต้น
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => 'รูปแบบอีเมลไม่ถูกต้อง',
                ];
            }

            //  ค้นหาข้อมูลผู้ใช้ในฐานข้อมูล Slave (Local)
            $user = User::where('email', $email)->first();
            if (! $user) {
                return [
                    'success' => false,
                    'message' => 'ไม่พบผู้ใช้',
                ];
            }

            // 2. ตรวจสอบสถานะบัญชี (Business Logic)
            if (! $user->is_active) {
                return [
                    'success' => false,
                    'message' => 'บัญชีผู้ใช้นี้ถูกระงับการใช้งาน กรุณาติดต่อผู้ดูแลระบบ',
                ];
            }

            // 3. Fast Path: ตรวจสอบรหัสผ่านกับฐานข้อมูล Slave
            if (! Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
                ]; // รหัสผ่านถูกต้อง ล็อกอินสำเร็จ
            }

            // 4. Fallback Path: กรณีรหัสผ่านผิด อาจเกิดจาก Replication Lag (ข้อมูล Master ยังไม่ซิงค์มา)
            $personalClient = $this->apiClient
                ->withFlow(TokenFlow::Password)
                ->withUserPassword($email, $password);

            // 2. ร้องขอดึงข้อมูล Profile ผู้ใช้งาน (ระบบจะไปขอกู้ยืม Password Grant Token จาก /oauth/token ให้อัตโนมัติเบื้องหลัง)
            $response = $personalClient->sendRequest('POST', '/api/v1/auth/user/me');

            if ($response->failed()) {
                return [
                    'success' => false,
                    'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
                ];
            }

            $data = $response->json('data');
            $backendUser = $data['user'] ?? null;

            if (! \is_array($backendUser)) {
                return [
                    'success' => false,
                    'message' => 'ไม่สามารถดึงรายละเอียดข้อมูลผู้ใช้งานจาก Master Server ได้',
                ];
            }

            // 3. 🔄 Sync โปรไฟล์จาก Master ลง Local DB (สร้างผู้ใช้ใหม่อัตโนมัติหากยังไม่มี)
            /** @var array<string, mixed> $backendUser */
            //   $user = $this->userSyncService->sync($backendUser);  //ไม่ซิงค์ ในกรณีล้อเกินด้วย พาสเวิด
            Auth::login($user);
            //
            session()->regenerate();

            return [
                'success' => true,
                'message' => 'เข้าสู่ระบบสำเร็จ',
                'user' => $user,
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'อีเมลหรือรหัสผ่านไม่ถูกต้อง หรือเกิดข้อผิดพลาดในระบบ: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * ตรวจสอบว่าโทเคนปัจจุบันหมดอายุแล้วหรือไม่
     */
    public function isTokenExpired(): bool
    {
        return $this->apiClient->isExpired();
    }

    /**
     * ดึง Access Token ล่าสุดของ Context ปัจจุบัน
     */
    public function getBackendToken(): ?string
    {
        try {
            return $this->apiClient->getToken();
        } catch (Throwable) {
            return null;
        }
    }
}
