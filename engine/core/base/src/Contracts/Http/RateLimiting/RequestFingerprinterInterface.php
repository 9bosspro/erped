<?php

declare(strict_types=1);

namespace Core\Base\Contracts\Http\RateLimiting;

use Illuminate\Http\Request;

/**
 * RequestFingerprinterInterface — สัญญาสำหรับการสร้าง Request Fingerprint
 *
 * ใช้ใน Rate Limiting เพื่อระบุตัวตน client อย่างแม่นยำ
 * แม้จะเปลี่ยน IP (VPN/proxy) เพราะใช้หลาย signal รวมกัน
 */
interface RequestFingerprinterInterface
{
    /**
     * สร้าง fingerprint จาก request signals
     *
     * @param  Request  $request  HTTP request ที่ต้องการ fingerprint
     * @return string hash string ที่ใช้เป็น rate limit key
     */
    public function generate(Request $request): string;
}
