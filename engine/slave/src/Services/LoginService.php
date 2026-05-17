<?php

declare(strict_types=1);

namespace Slave\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * LoginService — จัดการ Business Logic สำหรับการเข้าสู่ระบบและจัดการ Token (Sanctum)
 *
 * หน้าที่หลัก:
 * - ตรวจสอบตัวตน (Authentication)
 * - ออก Personal Access Token ผ่าน Sanctum
 * - จำกัดจำนวน Active Sessions ตามที่กำหนด (MAX_CLIENTS)
 */
class LoginService
{
    /** จำนวน active session สูงสุดต่อ user — เกินนี้จะลบ session เก่าที่สุดออก */
    private const int MAX_CLIENTS = 2;

    /**
     * ดำเนินการเข้าสู่ระบบ
     *
     * @param  array<string, string>  $credentials  ข้อมูลเข้าสู่ระบบ (email, password)
     * @return array<string, mixed> ข้อมูล User และ Token พร้อมรายละเอียดสิทธิ์
     *
     * @throws ValidationException กรณีรหัสผ่านไม่ถูกต้องหรือบัญชีถูกปิด
     */
    public function executeLogin(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
                'password' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['บัญชีผู้ใช้ถูกปิดการใช้งาน'],
            ]);
        }

        // ดึง active tokens เรียงจากเก่าไปใหม่ เพื่อเตรียมลบถ้ายอดเกิน
        $activeTokens = $user->tokens()
            ->where('expires_at', '>', now())
            ->oldest()
            ->get();

        // ถ้า session เกิดขีดจำกัดสูงสุด ให้ลบ token ที่เก่าที่สุดออก
        if ($activeTokens->count() >= self::MAX_CLIENTS) {
            $activeTokens->first()?->delete();
        }

        // สร้าง Personal Access Token ใหม่ผ่าน Sanctum
        $newToken = $user->createToken('auth_token');
        $expiresAt = $newToken->accessToken->expires_at;
        $expiration = $expiresAt !== null
            ? max(0, (int) now()->diffInSeconds($expiresAt, false))
            : 0;

        $activeSessions = $user->tokens()
            ->where('expires_at', '>', now())
            ->get();

        return [
            'user' => $user,
            'tokens' => $activeSessions,
            'access_token' => $newToken->plainTextToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiration,
            'active_sessions_count' => $activeSessions->count(),
        ];
    }

    /**
     * ขอ Refresh Token ใหม่ผ่าน OAuth endpoint ภายใน
     *
     * @param  string  $refreshToken  รหัส Refresh Token จากผู้ใช้
     * @return array<string, mixed> ผลลัพธ์จากการต่ออายุ
     *
     * @throws ValidationException กรณี Refresh Token ไม่ถูกต้อง
     */
    public function refreshToken(string $refreshToken): array
    {
        $appUrl = (string) (config('app.url') ?: 'http://localhost');
        $clientId = (string) (config('oauth2.client_id') ?: '');
        $clientSecret = (string) (config('oauth2.client_secret') ?: '');
        $scope = (string) (config('oauth2.default_scope', '') ?: '');

        $response = Http::asForm()->post("{$appUrl}/oauth/token", [
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'scope' => $scope,
        ]);

        if ($response->failed()) {
            throw ValidationException::withMessages([
                'refresh_token' => ['Refresh token ไม่ถูกต้องหรือหมดอายุ'],
            ]);
        }

        $result = $response->json();

        return \is_array($result) ? $result : [];
    }
}
