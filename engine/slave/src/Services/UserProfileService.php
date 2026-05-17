<?php

declare(strict_types=1);

namespace Slave\Services;

use App\Models\User;
use Carbon\Carbon;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * UserProfileService — ดึงข้อมูล Profile และ Active Sessions ของ User
 *
 * แยกออกจาก LoginService ตามหลัก SRP —
 * LoginService รับผิดชอบแค่ Authentication
 * UserProfileService รับผิดชอบแค่การดึงข้อมูล Profile
 */
class UserProfileService
{
    /**
     * ดึงข้อมูลผู้ใช้งานและอุปกรณ์ที่กำลังล็อกอิน
     *
     * @return array<string, mixed>
     */
    public function getUserProfileWithDevices(User $user): array
    {
        $currentToken = $user->tokens()
            ->where('expires_at', '>', now())
            ->latest('expires_at')
            ->first();

        $activeTokens = $user->tokens()
            ->where('expires_at', '>', now())
            ->latest()
            ->get();

        $devices = $activeTokens->map(static fn (PersonalAccessToken $token): array => [
            'token_id' => $token->id,
            'device_name' => $token->name,
            'scopes' => $token->abilities,
            'logged_in_at' => Carbon::parse($token->created_at)->format('Y-m-d H:i:s'),
            'last_used_at' => $token->last_used_at
                ? Carbon::parse($token->last_used_at)->format('Y-m-d H:i:s')
                : 'N/A',
            'expires_at' => $token->expires_at
                ? Carbon::parse($token->expires_at)->format('Y-m-d H:i:s')
                : 'ไม่หมดอายุ',
        ]);

        return [
            'token_id' => $currentToken?->id,
            'user' => $user,
            'active_count' => $devices->count(),
            'devices' => $devices,
            'currentToken' => $currentToken,
            'token_type' => 'Bearer',
            'access_token' => $currentToken,
            'expires_at' => $currentToken?->expires_at !== null
                ? Carbon::parse($currentToken->expires_at)->copy()->addYears(543)->translatedFormat('d F Y H:i:s')
                : null,
            'expires_in' => $currentToken?->expires_at !== null
                ? now()->diffInSeconds($currentToken->expires_at)
                : null,
            'date' => Carbon::now()->copy()->addYears(543)->translatedFormat('d F Y H:i:s'),
        ];
    }
}
