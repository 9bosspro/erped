<?php

declare(strict_types=1);

namespace Slave\Listeners;

use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Log;
use Slave\Contracts\Master\MasterClientInterface;
use Slave\Contracts\Master\TokenFlow;
use Throwable;

/**
 * Listener ที่คอยดักจับการออกจากระบบของผู้ใช้
 * เมื่อผู้ใช้คลิก Logout, คลาสนี้จะสั่งกวาดล้าง Token ของ Master Client ทิ้งทันทีเพื่อความปลอดภัย
 */
class SyncLogoutToMaster
{
    public function __construct(
        private readonly MasterClientInterface $masterClient,
    ) {}

    /**
     * จัดการเหตุการณ์ Logout
     */
    public function handle(Logout $event): void
    {

        // 🕵️‍♂️ [LOGGING TO VERIFY] บรรทัดพิสูจน์ความจริง: เมื่อ Logout ทำงาน จะจดบันทึกข้อความนี้ลง storage/logs ทันทีครับ!
        Log::info('🛡️ CLEAR_TOKENS_TRIGGERED: ClearMasterTokensOnLogout has been successfully activated during user logout.', [
            'user_id' => $event->user?->getAuthIdentifier(),
            'timestamp' => now()->toDateTimeString(),
        ]);

        try {
            $typeLogin = (string) (session()->get('type_login') ?? '');

            if ($typeLogin === 'personal') {
                $this->masterClient->withFlow(TokenFlow::Personal)
                    ->withTokenStore('session')
                    ->sendRequest('POST', '/api/v1/auth/user/logout');
                //   $this->masterClient->withTokenStore('session')->clearAllTokens();
            }

            if ($typeLogin === 'password') {
                $this->masterClient->withFlow(TokenFlow::Password)
                    ->withTokenStore('session')
                    ->sendRequest('POST', '/api/v1/auth/user/logout');
                //  $this->masterClient->withTokenStore('session')->clearAllTokens();
            }
            // ล้าง Session Laravel
            if (request()->hasSession()) {
                // ออกจากระบบ Web Guard
                //  Auth::guard('web')->logout();

                // ล้างค่า Session เก่าทิ้งเพื่อป้องกัน Session Fixation
                request()->session()->invalidate();

                // สร้าง CSRF Token ใหม่เพื่อความปลอดภัยสูงสุด
                request()->session()->regenerateToken();
            }
        } catch (Throwable $e) {
            // ดักจับกันเหนียว เผื่อกรณีเกิดข้อผิดพลาดขณะลบ Cache จะได้ไม่ขัดขวางการ Logout หลักของ User ครับ
            Log::warning('MasterClient: Could not clear tokens on logout', [
                'error' => $e->getMessage(),
                'user_id' => $event->user?->getAuthIdentifier(),
            ]);
        } finally {
            $this->masterClient->withTokenStore('session')->clearAllTokens(); // ← เสมอ
        }
    }
}
