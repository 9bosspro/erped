<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\ResetsUserPasswords;
use Slave\Services\Master\MasterClientService;
use Throwable;

/**
 * รีเซ็ตรหัสผ่านของผู้ใช้ผ่านระบบ Master-Slave
 *
 * ลำดับการทำงาน: ตรวจสอบรหัสผ่าน → ซิงค์ Master (Fail-Fast) → บันทึก Slave
 */
class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * รีเซ็ตรหัสผ่านที่ลืม โดยซิงค์กับ Master ก่อนบันทึกฝั่ง Slave เสมอ
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException เมื่อ validation ล้มเหลว, Master ปฏิเสธ, หรือ network ขัดข้อง
     */
    public function reset(User $user, array $input): void
    {
        Validator::make($input, [
            'password' => $this->passwordRules(),
        ])->validate();

        /** @var MasterClientService $masterClient */
        $masterClient = app('slave.master');

        $payload = [
            'backend_user_id' => $user->backend_user_id,
            'password'        => $input['password'],
        ];

        $headers                = $masterClient->generateSignedHeaders($payload);
        $headers['X-For-slave'] = 'true';
        $encryptedPayload       = $masterClient->encryptedpayload($payload);

        $this->syncWithMaster($masterClient, $headers, $encryptedPayload, $user);

        $user->forceFill(['password' => $input['password']])->save();
    }

    /**
     * ซิงค์การรีเซ็ตรหัสผ่านกับ Master Node (Fail-Fast)
     *
     * แยกออกมาเพื่อให้ try-catch ครอบเฉพาะ network error
     * และ ValidationException ไม่ถูกกลืนโดยไม่ตั้งใจ
     *
     * @param  array<string, string>  $headers
     *
     * @throws ValidationException เมื่อ Master ปฏิเสธ request หรือ network ขัดข้อง
     */
    private function syncWithMaster(
        MasterClientService $masterClient,
        array $headers,
        string $encryptedPayload,
        User $user,
    ): void {
        try {
            $response = $masterClient->withHeaders($headers)
                ->sendRequest('POST', '/api/v1/clients/reset-password-forget', [
                    'encrypted_payload' => $encryptedPayload,
                ]);
        } catch (Throwable $e) {
            Log::critical('Master Server API Unreachable', ['error' => $e->getMessage()]);

            /*  throw ValidationException::withMessages([
                'email' => ['ระบบเครือข่ายส่วนกลางขัดข้อง กรุณาลองใหม่อีกครั้ง'],
            ]); */
        }

        if ($response->failed()) {
            Log::error('Master Sync Password Failed', [
                'backend_user_id' => $user->backend_user_id,
                'status'          => $response->status(),
                'error'           => $response->json('message') ?? 'Unknown Error',
            ]);

            throw ValidationException::withMessages([
                'password' => ['ไม่สามารถอัปเดตรหัสผ่านกับเซิร์ฟเวอร์ส่วนกลางได้'],
            ]);
        }
    }
}
