<?php

declare(strict_types=1);

namespace App\Services\BackendApi;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
class BackendAuthService
{
    public function __construct(
        private readonly BackendApiClient $apiClient,
        private readonly TokenManager    $tokenManager,
        private readonly UserSyncService $userSyncService,
    ) {}

    /**
     * Login ผ่าน Backend API แล้ว sync user ลง local database
     *
     * @return array{success: bool, message: string, user?: User, errors?: array<mixed>}
     */
    public function login(string $email, string $password): array
    {
        $response = $this->apiClient->login($email, $password);

        if ($response->failed()) {
            $message = $response->json('message');
            $errors  = $response->json('data');

            return [
                'success' => false,
                'message' => \is_string($message) ? $message : 'อีเมลหรือรหัสผ่านไม่ถูกต้อง',
                'errors'  => \is_array($errors) ? $errors : [],
            ];
        }

        $rawData     = $response->json('data');
        $data        = \is_array($rawData) ? $rawData : [];
        $backendUser = $data['user'] ?? null;

        if (! \is_array($backendUser)) {
            return [
                'success' => false,
                'message' => 'ไม่สามารถดึงข้อมูลผู้ใช้ได้',
            ];
        }

        /** @var array<string, mixed> $backendUser */
        $user = $this->userSyncService->sync($backendUser);
        $this->tokenManager->store($data);
        Auth::login($user);

        return [
            'success' => true,
            'message' => 'เข้าสู่ระบบสำเร็จ',
            'user'    => $user,
        ];
    }

    /**
     * Refresh token โดยใช้ refresh_token ที่ส่งมา
     *
     * @return array{success: bool, message: string}
     */
    public function refresh(string $refreshToken): array
    {
        $response = $this->apiClient->refreshToken($refreshToken);

        if ($response->failed()) {
            Log::warning('Backend token refresh failed', ['status' => $response->status()]);

            return ['success' => false, 'message' => 'ไม่สามารถต่ออายุ session ได้'];
        }

        $rawData = $response->json('data') ?? $response->json();
        $data    = \is_array($rawData) ? $rawData : [];

        if (empty($data['access_token'])) {
            return ['success' => false, 'message' => 'ไม่ได้รับ token ใหม่จาก Backend'];
        }

        $this->tokenManager->store($data);

        Log::info('Backend token refreshed successfully');

        return ['success' => true, 'message' => 'ต่ออายุ session สำเร็จ'];
    }

    /**
     * Logout ทั้ง Frontend session และ Backend token
     */
    public function logout(): void
    {
        $token = $this->tokenManager->getToken();

        if ($token !== null) {
            $this->apiClient->logout($token);
        }

        $this->tokenManager->clear();

        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
    }

    /**
     * ตรวจสอบว่า token ใกล้หมดอายุหรือหมดอายุแล้ว (delegate ไปยัง TokenManager)
     */
    public function isTokenExpired(): bool
    {
        return $this->tokenManager->isExpired();
    }

    /**
     * ดึง refresh token จาก session (delegate ไปยัง TokenManager)
     */
    public function getRefreshToken(): ?string
    {
        return $this->tokenManager->getRefreshToken();
    }

    /**
     * ดึง Backend access token จาก session (delegate ไปยัง TokenManager)
     */
    public function getBackendToken(): ?string
    {
        return $this->tokenManager->getToken();
    }
}
