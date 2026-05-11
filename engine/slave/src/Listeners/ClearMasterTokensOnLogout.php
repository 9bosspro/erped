<?php

declare(strict_types=1);

namespace Slave\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Contracts\Master\TokenFlow;

/**
 * Listener ที่คอยดักจับการออกจากระบบของผู้ใช้
 * เมื่อผู้ใช้คลิก Logout, คลาสนี้จะสั่งกวาดล้าง Token ของ Master Client ทิ้งทันทีเพื่อความปลอดภัย
 */
class ClearMasterTokensOnLogout
{
    public function __construct(
        private readonly MasterClientInterface $masterClient
    ) {}

    /**
     * จัดการเหตุการณ์ Logout
     */
    public function handle(Logout $event): void
    {
        // 🕵️‍♂️ [LOGGING TO VERIFY] บรรทัดพิสูจน์ความจริง: เมื่อ Logout ทำงาน จะจดบันทึกข้อความนี้ลง storage/logs ทันทีครับ!
        Log::info('🛡️ CLEAR_TOKENS_TRIGGERED: ClearMasterTokensOnLogout has been successfully activated during user logout.', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'timestamp' => now()->toDateTimeString()
        ]);

        try {
            // 🧹 กวาดล้าง Token ทั้งหมดที่ผูกกับ Client ตัวนี้ทิ้ง (รวมถึงที่จดทะเบียนไว้ใน Manifest ด้วย)
            //  $masterClient = app('slave.master');

            // 🔥 ดึง Client ที่คอนฟิกไว้สำหรับ Personal Session เพื่อดึง Token ที่ถูกต้อง
            // ยิง api ไป logout ที่ Master Backend
            //  $apiClientBackend = $this->apiClient;
            $personalClient = $this->masterClient->withFlow(TokenFlow::Personal)
                ->withTokenStore('session');
            $response = $personalClient->post('api/v1/auth/user/logout');
            //

            $this->masterClient->clearAllTokens();
            $this->masterClient->withTokenStore('session')->clearAllTokens();
            //
        } catch (\Throwable $e) {
            // ดักจับกันเหนียว เผื่อกรณีเกิดข้อผิดพลาดขณะลบ Cache จะได้ไม่ขัดขวางการ Logout หลักของ User ครับ
            Log::warning('MasterClient: Could not clear tokens on logout', [
                'error' => $e->getMessage(),
                'user_id' => $event->user?->getAuthIdentifier(),
            ]);
        }
    }
}
