<?php

declare(strict_types=1);

namespace Slave\Actions\User;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use RuntimeException;

/**
 * UserInfoAction — Action สำหรับดึงข้อมูลรายละเอียดของผู้ใช้งาน (User Info)
 *
 * ดึงข้อมูลผู้ใช้งานจากฐานข้อมูล พร้อม Token ที่กำลังใช้งานอยู่
 */
class UserInfoAction
{
    /**
     * ดำเนินการดึงข้อมูลผู้ใช้งานและรายละเอียด Token
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException หากไม่พบผู้ใช้งานหรือ Token
     */
    public function execute(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User) {
            throw new RuntimeException('ไม่พบผู้ใช้งานในระบบ');
        }

        $token = $user->currentAccessToken();

        if (! $token instanceof PersonalAccessToken) {
            throw new RuntimeException('ไม่พบ Token');
        }

        return [
            'user' => $user,
            'token_id' => $token->id,
            'device_id' => $token->name,
        ];
    }
}
