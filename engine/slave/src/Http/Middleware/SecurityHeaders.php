<?php

declare(strict_types=1);

namespace Slave\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SecurityHeaders — เพิ่ม Security Headers มาตรฐานทุก response
 *
 * แยก headers เป็น 3 กลุ่ม: Common, API, Web
 * Web routes ได้รับ CSP แบบ full พร้อม nonce สำหรับ inline scripts
 * API routes ได้รับ CSP แบบ minimal (default-src 'none')
 *
 * Performance:
 *   - PERMISSIONS_POLICY เป็น constant — ไม่ต้อง implode ทุก request
 *   - $webCspTemplate คำนวณครั้งเดียวต่อ FPM worker — แทนที่เฉพาะ nonce ต่อ request
 *
 * ปรับแต่ง CSP sources ได้ใน config/security.php โดยไม่ต้องแก้ Middleware
 */
class SecurityHeaders
{
    /**
     * Permissions-Policy header value — static ทุก request
     */
    private const string PERMISSIONS_POLICY = 'accelerometer=(), ambient-light-sensor=(), autoplay=(), battery=(), camera=(), cross-origin-isolated=(), display-capture=(), document-domain=(), encrypted-media=(), execution-while-not-rendered=(), execution-while-out-of-viewport=(), fullscreen=(self), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), navigation-override=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), web-share=(), xr-spatial-tracking=()';

    /**
     * CSP template สำหรับ Web routes — สร้างครั้งเดียว แทนที่ __NONCE__ ต่อ request
     */
    private static ?string $webCspTemplate = null;

    /**
     * จัดการ request ที่เข้ามา
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isApi = $request->is('api', 'api/*');

        // สร้าง nonce สำหรับ inline scripts เฉพาะ web routes
        $nonce = $isApi ? null : base64_encode(random_bytes(16));

        if ($nonce !== null) {
            app()->instance('csp-nonce', $nonce);
        }

        $response = $next($request);

        $this->applyCommonHeaders($response, $isApi);

        if ($isApi) {
            $this->applyApiHeaders($response);
        } else {
            $this->applyWebHeaders($response, $nonce);
        }

        return $response;
    }

    /**
     * Headers ที่ใช้ทั้ง API และ Web
     */
    private function applyCommonHeaders(Response $response, bool $isApi): void
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('X-Download-Options', 'noopen');
        $response->headers->set('Cross-Origin-Opener-Policy', $isApi ? 'same-origin' : 'same-origin-allow-popups');
        $response->headers->set('Cross-Origin-Resource-Policy', $isApi ? 'same-origin' : 'cross-origin');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
    }

    /**
     * Headers เฉพาะ API routes
     */
    private function applyApiHeaders(Response $response): void
    {
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Content-Security-Policy', "default-src 'none'");
    }

    /**
     * Headers เฉพาะ Web routes พร้อม CSP แบบ full
     *
     * $nonce จะไม่เป็น null ใน Web branch — เพราะ handle() สร้างทุกครั้งที่ !$isApi
     */
    private function applyWebHeaders(Response $response, ?string $nonce): void
    {
        if (self::$webCspTemplate === null) {
            self::$webCspTemplate = $this->buildWebCspTemplate();
        }

        $csp = str_replace('__NONCE__', $nonce ?? '', self::$webCspTemplate);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');
    }

    /**
     * สร้าง CSP template สำหรับ Web routes
     *
     * Dynamic sources (app.url, minio, imgproxy, reverb) ผสานกับ
     * static sources จาก slave::security.csp.* config
     */
    private function buildWebCspTemplate(): string
    {
        $appUrl   = rtrim($this->configString('app.url'), '/');
        $minioUrl = rtrim($this->configString('filesystems.disks.minio.endpoint'), "' ");
        $imgProxy = rtrim($this->configString('slave::security.imgproxy_url', 'https://imgprox.ppp-online.com'), '/');

        $reverbHost   = $this->configString('reverb.servers.reverb.host');
        $reverbPort   = $this->configString('reverb.servers.reverb.port', '8080');
        $reverbScheme = $this->configString('broadcasting.connections.reverb.options.scheme', 'http') === 'https' ? 'wss' : 'ws';

        $wsUrl = ($reverbHost && $reverbHost !== '0.0.0.0')
            ? "{$reverbScheme}://{$reverbHost}:{$reverbPort}"
            : null;

        /** @var list<string> $cfgScript */
        $cfgScript = (array) config('slave::security.csp.script_src', []);
        /** @var list<string> $cfgStyle */
        $cfgStyle = (array) config('slave::security.csp.style_src', []);
        /** @var list<string> $cfgFont */
        $cfgFont = (array) config('slave::security.csp.font_src', []);
        /** @var list<string> $cfgImg */
        $cfgImg = (array) config('slave::security.csp.img_src', []);
        /** @var list<string> $cfgConnect */
        $cfgConnect = (array) config('slave::security.csp.connect_src', []);

        $scriptSrc = implode(' ', array_filter(["'self'", "'nonce-__NONCE__'", ...$cfgScript]));
        $styleSrc  = implode(' ', array_filter(["'self'", "'unsafe-inline'", ...$cfgStyle]));
        $fontSrc   = implode(' ', array_filter(["'self'", 'data:', ...$cfgFont]));

        $imgSrc = implode(' ', array_filter([
            "'self'", 'data:', 'blob:',
            $minioUrl ?: null,
            $imgProxy ?: null,
            ...$cfgImg,
        ]));

        $connectSrc = implode(' ', array_filter([
            "'self'",
            $appUrl ?: null,
            $minioUrl ?: null,
            $wsUrl,
            ...$cfgConnect,
        ]));

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "style-src {$styleSrc}",
            "font-src {$fontSrc}",
            "img-src {$imgSrc}",
            "connect-src {$connectSrc}",
            "media-src 'self' " . ($minioUrl ?: 'https:'),
            "worker-src 'self' blob:",
            "frame-src 'none'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            'upgrade-insecure-requests',
        ]);
    }

    /**
     * ดึงค่า config เป็น string — ป้องกัน mixed type ที่ PHPStan ตรวจจับ
     */
    private function configString(string $key, string $default = ''): string
    {
        $value = config($key, $default);

        return \is_string($value) ? $value : $default;
    }
}
