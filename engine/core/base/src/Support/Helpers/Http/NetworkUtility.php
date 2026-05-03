<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Http;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * NetworkUtility — เครื่องมือตรวจสอบเครือข่าย
 *
 * หมายเหตุ: ไม่ใช้ shell_exec() เพื่อความปลอดภัย
 * ใช้ fsockopen() แทนซึ่งปลอดภัยและ portable กว่า
 */
final class NetworkUtility
{
    /**
     * ตรวจสอบการเชื่อมต่อ TCP และวัด response time
     *
     * @param  string  $host  hostname หรือ IP (ผ่าน validation อัตโนมัติ)
     * @param  int  $port  port (1-65535)
     * @param  int  $timeout  timeout ในวินาที
     * @return int response time (ms) หรือ -1 หากเชื่อมต่อไม่ได้
     */
    public function ping(string $host, int $port = 80, int $timeout = 3): int
    {
        // Validate host ก่อนเสมอ — ป้องกัน injection ทุกรูปแบบ
        if (! $this->isValidHost($host)) {
            Log::warning("NetworkUtility::ping — invalid host: {$host}");

            return -1;
        }

        if ($port < 1 || $port > 65535) {
            return -1;
        }

        $start = microtime(true);

        try {
            $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (! $connection) {
                return -1;
            }

            fclose($connection);

            return (int) round((microtime(true) - $start) * 1000);
        } catch (Throwable) {
            return -1;
        }
    }

    /**
     * ดึง Client IP จริงจาก request headers
     * รองรับ Cloudflare, Load Balancer, Reverse Proxy
     *
     * ลำดับความน่าเชื่อถือจากสูงไปต่ำ:
     * Cloudflare CF-Connecting-IP > X-Real-IP > X-Forwarded-For > REMOTE_ADDR
     */
    public function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',   // Cloudflare (น่าเชื่อถือที่สุดหาก deploy บน Cloudflare)
            'HTTP_X_REAL_IP',          // Nginx reverse proxy
            'HTTP_X_FORWARDED_FOR',    // Load Balancer / Proxy (อาจมีหลาย IP)
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            $value = $_SERVER[$header] ?? null;

            if (empty($value) || ! is_scalar($value)) {
                continue;
            }

            // X-Forwarded-For อาจมีหลาย IP: "client, proxy1, proxy2"
            $ip = trim(explode(',', (string) $value)[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip === '::1' ? '127.0.0.1' : $ip;
            }
        }

        return '127.0.0.1';
    }

    /**
     * ดึง Public IP ของ Server จาก external service
     * ใช้ fallback หลายตัวเพื่อความน่าเชื่อถือ
     *
     * @param  int  $timeout  timeout ต่อ service (วินาที)
     */
    public function getPublicIp(int $timeout = 3): string
    {
        $fallback = request()->ip() ?? '127.0.0.1';

        // ลำดับ fallback services
        $services = [
            'https://api.ipify.org',
            'https://ifconfig.me/ip',
            'https://icanhazip.com',
        ];

        foreach ($services as $service) {
            try {
                $response = Http::timeout($timeout)->get($service);

                if ($response->successful()) {
                    $ip = trim($response->body());
                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                }
            } catch (Throwable $e) {
                Log::debug("Public IP service [{$service}] failed: ".$e->getMessage());
            }
        }

        return $fallback;
    }

    /**
     * ดึงข้อมูล OS และ Browser จาก User Agent
     *
     * @param  string  $userAgent  User-Agent string (default: จาก $_SERVER)
     * @return array{os: string, browser: string}
     */
    public function parseUserAgent(string $userAgent = ''): array
    {
        $serverUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $serverUaStr = is_scalar($serverUa) ? (string) $serverUa : '';
        $userAgent = $userAgent ?: $serverUaStr;

        return [
            'os' => $this->detectOs($userAgent),
            'browser' => $this->detectBrowser($userAgent),
        ];
    }

    /**
     * ดึง registered domain จาก URL
     * เช่น "https://sub.example.co.th/path" → "example.co.th"
     */
    public function extractDomain(string $url): string
    {
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? $url);

        preg_match('/[a-z0-9\-]{1,63}\.[a-z\.]{2,}$/i', $host, $matches);

        return $matches[0] ?? $host;
    }

    // ──────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────

    /**
     * ตรวจสอบว่า host string ปลอดภัย (ป้องกัน command/shell injection)
     * รับเฉพาะ valid IP หรือ valid hostname เท่านั้น
     */
    private function isValidHost(string $host): bool
    {
        return filter_var($host, FILTER_VALIDATE_IP) !== false
            || filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }

    /**
     * ตรวจหา OS จาก User Agent string
     */
    private function detectOs(string $ua): string
    {
        $patterns = [
            'Windows 11' => '/windows nt 11/i',
            'Windows 10' => '/windows nt 10/i',
            'Windows 8.1' => '/windows nt 6.3/i',
            'Windows 8' => '/windows nt 6.2/i',
            'Windows 7' => '/windows nt 6.1/i',
            'Mac OS X' => '/macintosh|mac os x/i',
            'iOS' => '/iphone|ipad|ipod/i',
            'Android' => '/android/i',
            'Ubuntu' => '/ubuntu/i',
            'Linux' => '/linux/i',
            'BlackBerry' => '/blackberry/i',
        ];

        foreach ($patterns as $os => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $os;
            }
        }

        return 'Unknown OS';
    }

    /**
     * ตรวจหา Browser จาก User Agent string
     */
    private function detectBrowser(string $ua): string
    {
        // Edge ต้องตรวจก่อน Chrome เพราะ Edge UA มี "Chrome" ด้วย
        $patterns = [
            'Edge' => '/edg\//i',
            'Chrome' => '/chrome/i',
            'Firefox' => '/firefox/i',
            'Safari' => '/safari/i',
            'Opera' => '/opr|opera/i',
            'IE' => '/msie|trident/i',
        ];

        foreach ($patterns as $browser => $pattern) {
            if (preg_match($pattern, $ua)) {
                return $browser;
            }
        }

        return 'Unknown Browser';
    }
}
