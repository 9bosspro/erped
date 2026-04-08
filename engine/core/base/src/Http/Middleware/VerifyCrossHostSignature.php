<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use Closure;
use Core\Base\Services\Integration\CrossHostReceiver;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * VerifyCrossHostSignature — Middleware กรอง Request ข้ามโฮสต์
 *
 * ทำหน้าที่:
 * 1. ตรวจสอบ X-Signature + X-Timestamp + X-Nonce (HMAC + Replay Attack Protection)
 * 2. เรียกใช้ CrossHostReceiver เพื่อถอดรหัสและยืนยันตัวตน
 * 3. แนบ safe_payload ไว้กับ Request ให้ Controller ดึงต่อได้ทันที
 *    → $request->attributes->get('safe_payload')
 *
 * Usage:
 *   Route::post('/hook', ...)->middleware('crosshost.verify')         // signed
 *   Route::post('/hook', ...)->middleware('crosshost.verify:sealed')  // sealed box
 *
 * Config required (config/services.php → services.crosshost):
 *   CROSSHOST_SHARED_SECRET, CROSSHOST_PRIVATE_KEY, CROSSHOST_TIMESTAMP_TTL
 */
class VerifyCrossHostSignature
{
    /**
     * @param  string  $mode  'signed' (default) หรือ 'sealed'
     */
    public function handle(Request $request, Closure $next, string $mode = 'signed'): Response
    {
        // ดึงค่าจาก config เท่านั้น (ห้ามใช้ env() โดยตรง — จะพังหลัง config:cache)
        $sharedSecret = (string) config('services.crosshost.shared_secret', '');
        $privateKey = (string) config('services.crosshost.private_key', '');
        $timestampTtl = (int) config('services.crosshost.timestamp_ttl', 300);

        if (empty($sharedSecret)) {
            Log::critical('CrossHost middleware: CROSSHOST_SHARED_SECRET is not configured');
            throw new AccessDeniedHttpException('Access Denied.');
        }

        $receiver = new CrossHostReceiver($sharedSecret, $privateKey ?: null, $timestampTtl);

        try {
            $payload = $mode === 'sealed'
                ? $receiver->receiveSealedPayload($request)
                : $receiver->receiveSignedPayload($request);

            $request->attributes->set('safe_payload', $payload);

        } catch (AccessDeniedHttpException $e) {
            // AccessDeniedHttpException ที่โยนมาจาก Receiver มี message ที่ปลอดภัยแล้ว
            throw $e;
        } catch (Exception $e) {
            // Exception ที่ไม่คาดคิด — log ภายใน ไม่รั่วออกไปผู้ใช้
            Log::error('CrossHost middleware: unexpected error', [
                'ip' => $request->ip(),
                'path' => $request->path(),
                'error' => $e->getMessage(),
            ]);
            throw new AccessDeniedHttpException('Access Denied.');
        }

        return $next($request);
    }
}
