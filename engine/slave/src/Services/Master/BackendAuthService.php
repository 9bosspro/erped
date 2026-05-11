<?php

declare(strict_types=1);

namespace Slave\Services\Master;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Contracts\Master\TokenFlow;

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
        private readonly MasterClientInterface $apiClient,
        private readonly TokenManager    $tokenManager,
        private readonly UserSyncService $userSyncService,
    ) {}
    //
    public function logout(): void
    {
        //
        $Clientnow = $this->apiClient;
        // 🔥 ดึง Client ที่คอนฟิกไว้สำหรับ Personal Session เพื่อดึง Token ที่ถูกต้อง
        // ยิง api ไป logout ที่ Master Backend
        //  $apiClientBackend = $this->apiClient;
        $personalClient = $this->apiClient->withFlow(TokenFlow::Personal)
            ->withTokenStore('session');
        $response = $personalClient->post('api/v1/auth/user/logout');



        // $response = $personalClient->post('/api/v1/auth/user/logout');
        /*   $personalClient->sendRequest('POST', '/api/v1/auth/user/logout', [
            'token' => $personalClient->getToken(), // 🌟 ดึง Token มาใส่แบบถูกต้องแล้วครับ!
        ]); */

        // 🧹 กวาดล้าง Cache ทั้งหมดทุกช่องทางเพื่อความปลอดภัย
        $Clientnow->clearAllTokens();
        $Clientnow->withTokenStore('session')->clearAllTokens();
        //


    }
}
