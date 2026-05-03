<?php

declare(strict_types=1);

namespace Core\Base\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;

/**
 * LoginService — จัดการ Business Logic สำหรับการเข้าสู่ระบบและจัดการ Token
 *
 * หน้าที่หลัก:
 * - ตรวจสอบตัวตน (Authentication)
 * - ออก Access Token
 * - จำกัดจำนวน Active Sessions ตามที่กำหนด (MAX_CLIENTS)
 * - จัดการ Refresh Token
 */
class LoginService
{
    /** จำนวน active session สูงสุดต่อ user — เกินนี้จะ revoke session เก่าที่สุด */
    private const MAX_CLIENTS = 2;

    /**
     * ดำเนินการเข้าสู่ระบบ
     *
     * @param  array  $credentials  ข้อมูลเข้าสู่ระบบ (email, password)
     * @return array ข้อมูล User และ Token พร้อมรายละเอียดสิทธิ์
     *
     * @throws ValidationException กรณีรหัสผ่านไม่ถูกต้อง
     */
    public function executeLogin(array $credentials): array
    {
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
                'password' => ['อีเมลหรือรหัสผ่านไม่ถูกต้อง'],
            ]);
        }

        $user = Auth::user();

        if ($user === null) {
            throw ValidationException::withMessages(['email' => ['ไม่พบข้อมูลผู้ใช้']]);
        }

        // ตรวจสอบว่า account ยังใช้งานได้อยู่หรือไม่
        if (! $user->is_active) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => ['บัญชีผู้ใช้ถูกปิดการใช้งาน'],
            ]);
        }

        // 1. ดึง active tokens เรียงจากเก่าไปใหม่ เพื่อเตรียม revoke ถ้ายอดเกิน
        $activeTokens = $user->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->get();

        // 2. ถ้า session เกิดขีดจำกัดสูงสุด ให้ revoke token ที่เก่าที่สุดออก
        if ($activeTokens->count() >= self::MAX_CLIENTS) {
            $oldest = $activeTokens->first();
            $oldest?->revoke();
            $oldest?->refreshToken?->revoke();
        }

        // 3. สร้าง Access Token ใหม่สำหรับผู้ใช้
        /** @var \Laravel\Passport\PersonalAccessTokenResult $tokenResult */
        $tokenResult = $user->createToken('auth_token');
        /** @phpstan-ignore property.protected */
        $expiration = max(0, now()->diffInSeconds($tokenResult->token?->expires_at, false));

        // 4. ดึง Active Sessions เฉพาะที่เป็น Personal Access Token
        $activeSessions = $user->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->get()
            ->filter(fn (Token $t) => (bool) $t->client?->hasGrantType('personal_access'));

        return [
            'user' => $user,
            'tokens' => $activeSessions,
            'access_token' => $tokenResult->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $expiration,
            'active_sessions_count' => $activeSessions->count(),
        ];
    }

    /**
     * ขอ Refresh Access Token ใหม่ ผ่าน OAuth ภายใน
     *
     * @param  string  $refreshToken  รหัส Refresh Token จากผู้ใช้
     * @return array ผลลัพธ์จากการต่ออายุ (access_token, refresh_token, etc.)
     *
     * @throws ValidationException กรณี Refresh Token ไม่ถูกต้อง
     */
    public function refreshToken(string $refreshToken): array
    {
        $appUrlVal = config('app.url');
        $appUrl = is_scalar($appUrlVal) ? (string) $appUrlVal : 'http://localhost';

        $clientIdVal = config('oauth2.client_id');
        $clientId = is_scalar($clientIdVal) ? (string) $clientIdVal : '';

        $clientSecretVal = config('oauth2.client_secret');
        $clientSecret = is_scalar($clientSecretVal) ? (string) $clientSecretVal : '';

        $scopeVal = config('oauth2.default_scope', '');
        $scope = is_scalar($scopeVal) ? (string) $scopeVal : '';

        $response = Http::asForm()->post($appUrl.'/oauth/token', [
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

        return is_array($result) ? $result : [];
    }

    /**
     * ดึงข้อมูลผู้ใช้งานและอุปกรณ์ที่กำลังล็อกอิน
     */
    public function getUserProfileWithDevices(User $user): array
    {
        $currentToken = $user->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->orderByDesc('expires_at')
            ->first();
        $activeTokens = $user->tokens()
            ->where('revoked', false)
            ->where('expires_at', '>', now())
            ->with('client')
            ->orderBy('created_at', 'desc')
            ->get();

        $devices = $activeTokens->map(function ($token) {
            return [
                'token_id' => $token->id,
                'device_name' => $token->name ?? 'ไม่ระบุชื่อ',
                'client' => $token->client->name ?? 'Unknown Client',
                'scopes' => $token->scopes,
                'logged_in_at' => \Carbon\Carbon::parse($token->created_at)->format('Y-m-d H:i:s'),
                'last_used_at' => 'N/A',
                'expires_at' => $token->expires_at ? \Carbon\Carbon::parse($token->expires_at)->format('Y-m-d H:i:s') : 'ไม่หมดอายุ',
            ];
        });

        return [
            'user' => $user,
            'active_count' => $devices->count(),
            'devices' => $devices,
            'currentToken' => $currentToken,
            'token_type' => 'Bearer',
            'access_token' => $currentToken !== null ? clone $currentToken : null,
            'expires_at' => $currentToken && $currentToken->expires_at ? \Carbon\Carbon::parse($currentToken->expires_at)->copy()->addYears(543)->translatedFormat('d F Y H:i:s') : null,
            'expires_in' => $currentToken && $currentToken->expires_at ? now()->diffInSeconds($currentToken->expires_at) : null,
            'date' => \Carbon\Carbon::now()->copy()->addYears(543)->translatedFormat('d F Y H:i:s'),
        ];
    }
}
