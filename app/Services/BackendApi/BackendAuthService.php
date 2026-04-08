<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
    /** Session keys สำหรับ backend token */
    private const SESSION_ACCESS_TOKEN   = 'backend_access_token';
    private const SESSION_TOKEN_TYPE     = 'backend_token_type';
    private const SESSION_EXPIRES_IN     = 'backend_expires_in';
    private const SESSION_REFRESH_TOKEN  = 'backend_refresh_token';
    private const SESSION_EXPIRES_AT     = 'backend_token_expires_at';

    /** Buffer ก่อน token หมดอายุ (นาที) — refresh ล่วงหน้า */
    private const EXPIRY_BUFFER_MINUTES = 5;

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

        // เก็บ Backend token ใน session (พร้อม expiry timestamp + refresh token)
        $this->storeTokenInSession($data);

        // Login user ผ่าน session (สำหรับ Fortify/Inertia)
        Auth::login($user);

        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user'    => $user,
        ];
    }

    /**
     * Refresh token โดยใช้ refresh_token จาก session
     *
     * @return array{success: bool, message: string}
     */
    public function refresh(string $refreshToken): array
    {
        $response = $this->apiClient->refreshToken($refreshToken);

        if ($response->failed()) {
            Log::warning('Backend token refresh failed', [
                'status' => $response->status(),
            ]);

            return [
                'success' => false,
                'message' => 'ไม่สามารถต่ออายุ session ได้',
            ];
        }

        $data = $response->json('data') ?? $response->json();

        if (empty($data['access_token'])) {
            return [
                'success' => false,
                'message' => 'ไม่ได้รับ token ใหม่จาก Backend',
            ];
        }

        $this->storeTokenInSession($data);

        Log::info('Backend token refreshed successfully');

        return ['success' => true, 'message' => 'ต่ออายุ session สำเร็จ'];
    }

    /**
     * ตรวจสอบว่า token ใกล้หมดอายุหรือหมดอายุแล้ว (รวม buffer)
     */
    public function isTokenExpired(): bool
    {
        $expiresAt = session(self::SESSION_EXPIRES_AT);

        if (! $expiresAt) {
            return false; // ไม่ทราบ expiry — ถือว่ายังใช้งานได้
        }

        return now()->addMinutes(self::EXPIRY_BUFFER_MINUTES)->gte(Carbon::parse($expiresAt));
    }

    /**
     * ดึง refresh token จาก session
     */
    public function getRefreshToken(): ?string
    {
        return session(self::SESSION_REFRESH_TOKEN);
    }

    /**
     * Logout ทั้ง Frontend session และ Backend token
     */
    public function logout(): void
    {
        $token = session(self::SESSION_ACCESS_TOKEN);

        // Revoke token บน Backend
        if ($token) {
            $this->apiClient->logout($token);
        }

        $this->clearTokenSession();

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
        return session(self::SESSION_ACCESS_TOKEN);
    }

    /**
     * เก็บ token data ทั้งหมดลง session พร้อม calculated expiry timestamp
     */
    private function storeTokenInSession(array $data): void
    {
        $expiresIn = isset($data['expires_in']) ? (int) $data['expires_in'] : null;

        session([
            self::SESSION_ACCESS_TOKEN  => $data['access_token'] ?? null,
            self::SESSION_TOKEN_TYPE    => $data['token_type'] ?? 'Bearer',
            self::SESSION_EXPIRES_IN    => $expiresIn,
            self::SESSION_REFRESH_TOKEN => $data['refresh_token'] ?? session(self::SESSION_REFRESH_TOKEN),
            self::SESSION_EXPIRES_AT    => $expiresIn
                ? now()->addSeconds($expiresIn)->toIso8601String()
                : null,
        ]);
    }

    /**
     * ลบ token data ทั้งหมดออกจาก session
     */
    private function clearTokenSession(): void
    {
        session()->forget([
            self::SESSION_ACCESS_TOKEN,
            self::SESSION_TOKEN_TYPE,
            self::SESSION_EXPIRES_IN,
            self::SESSION_REFRESH_TOKEN,
            self::SESSION_EXPIRES_AT,
        ]);
    }

    /**
     * Sync user data จาก Backend ลง local database
     *
     * ใช้ backend_user_id เป็น key สำหรับ upsert
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
