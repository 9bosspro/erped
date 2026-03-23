<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * BackendAuthService — จัดการ Authentication ผ่าน Backend API (pppportal)
 *
 * Flow:
 *  1. User กรอก email/password ที่ Frontend
 *  2. Frontend ส่ง credentials ไป Backend API (/api/v1/auth/login)
 *  3. Backend ตรวจสอบ → คืน Passport token + user data
 *  4. Frontend sync user data ลง local database (สำหรับ Fortify/Inertia)
 *  5. Frontend login user ผ่าน session
 *  6. เก็บ Backend token ใน session สำหรับ API calls ต่อไป
 *
 * ทำไมต้อง sync?
 *  - Fortify ต้องการ User model ใน local database สำหรับ session auth
 *  - Inertia ต้องการ auth.user ใน shared props
 *  - แต่ Backend (pppportal) คือ single source of truth ของ user data
 */
class BackendAuthService
{
    public function __construct(
        private readonly BackendApiClient $apiClient,
    ) {}

    /**
     * Login ผ่าน Backend API แล้ว sync user ลง local database
     *
     * @return array{success: bool, message: string, user?: User}
     */
    public function login(string $email, string $password): array
    {
        $response = $this->apiClient->login($email, $password);

        if ($response->failed()) {
            return [
                'success' => false,
                'message' => $response->json('message', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง'),
                'errors'  => $response->json('data', []),
            ];
        }

        $data = $response->json('data');
        $backendUser = $data['user'] ?? null;

        if (! $backendUser) {
            return [
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลผู้ใช้ได้',
            ];
        }

        // Sync user ลง local database
        $user = $this->syncUser($backendUser);

        // เก็บ Backend token ใน session
        session([
            'backend_access_token'  => $data['access_token'] ?? null,
            'backend_token_type'    => $data['token_type'] ?? 'Bearer',
            'backend_expires_in'    => $data['expires_in'] ?? null,
        ]);

        // Login user ผ่าน session (สำหรับ Fortify/Inertia)
        Auth::login($user);

        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user'    => $user,
        ];
    }

    /**
     * Logout ทั้ง Frontend session และ Backend token
     */
    public function logout(): void
    {
        $token = session('backend_access_token');

        // Revoke token บน Backend
        if ($token) {
            $this->apiClient->logout($token);
        }

        // ลบ session data
        session()->forget([
            'backend_access_token',
            'backend_token_type',
            'backend_expires_in',
        ]);

        // Logout จาก Frontend session
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    /**
     * ดึง Backend access token จาก session
     */
    public function getBackendToken(): ?string
    {
        return session('backend_access_token');
    }

    /**
     * Sync user data จาก Backend ลง local database
     *
     * ใช้ email เป็น key สำหรับ upsert
     * password ใน local database เป็น random hash (ไม่ใช้จริง — auth ผ่าน Backend เท่านั้น)
     */
    private function syncUser(array $backendUser): User
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
