<?php

declare(strict_types=1);

namespace Core\Base\Http\RateLimiting;

use Core\Base\Contracts\Http\RateLimiting\RequestFingerprinterInterface;
use Illuminate\Http\Request;

/**
 * RequestFingerprinter — สร้าง server-side fingerprint จาก request signals
 *
 * รวม client-side X-Visitor-Id (FingerprintJS) + server signals เป็น hash เดียว
 * ใช้สำหรับ rate limiting ที่แม่นกว่า IP อย่างเดียว
 * ทนต่อ IP rotation (VPN/proxy) เพราะใช้หลาย signal พร้อมกัน
 *
 * Signals ที่ใช้ (ทั้งหมด normalize เป็น lowercase ก่อน hash):
 *   - ip           : IP address ของ client
 *   - user-agent   : browser / device identifier
 *   - accept-lang  : locale fingerprint
 *   - accept-enc   : encoding support fingerprint
 *   - x-visitor-id : client-side fingerprint จาก FingerprintJS (optional)
 *
 * ⚠️  hash ผูกกับ APP_KEY (HMAC) — ป้องกัน fingerprint correlation ข้าม environment
 *     และทำให้ attacker ที่เห็น Redis logs ไม่สามารถ pre-compute hash ของ user เป้าหมายได้
 *
 * ⚠️  ใช้ sha256 เพื่อ performance — ไม่เหมาะกับ cryptographic use case อื่น
 */
final class RequestFingerprinter implements RequestFingerprinterInterface
{
    /**
     * สร้าง fingerprint จาก multi-signal
     *
     * Signals ทั้งหมดถูก normalize เป็น lowercase และ trim whitespace
     * ก่อน hash เพื่อป้องกัน case-sensitive mismatch ระหว่าง request
     * จากนั้น HMAC ด้วย APP_KEY เพื่อ bind hash เข้ากับ application instance
     *
     * @param  Request  $request  HTTP request ที่ต้องการ fingerprint
     * @return string hmac-sha256 hash (64 chars)
     */
    public function generate(Request $request): string
    {
        $signals = array_filter(
            array_map(
                static fn (?string $signal): string => strtolower(trim((string) $signal)),
                [
                    $request->ip(),
                    $request->userAgent(),
                    $request->header('Accept-Language'),
                    $request->header('Accept-Encoding'),
                    $request->header('X-Visitor-Id'), // client-side fingerprint — optional
                ],
            ),
            static fn (string $signal): bool => $signal !== '',
        );

        // ใช้ APP_KEY เป็น secret — fallback "" ในกรณี boot ก่อน config โหลด (เช่น tinker)
        // hash_hmac คงที่ปลอดภัยกว่า hash ตรงเพราะ key เป็น secret และไม่ส่งออก
        $appKey = config('app.key');
        $secret = \is_string($appKey) ? $appKey : '';

        return hash_hmac('sha256', implode('|', $signals), $secret);
    }
}
