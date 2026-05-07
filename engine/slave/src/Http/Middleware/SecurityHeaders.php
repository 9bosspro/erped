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
    private const string PERMISSIONS_POLICY = 'accelerometer=(), autoplay=(), camera=(), cross-origin-isolated=(), display-capture=(), encrypted-media=(), fullscreen=(self), geolocation=(), gyroscope=(), keyboard-map=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), web-share=(), xr-spatial-tracking=()';

    /**
     * CSP template สำหรับ Web routes — สร้างครั้งเดียว แทนที่ __NONCE__ ต่อ request
     *
     * ใน dev (มีไฟล์ public/hot จาก Vite) จะ rebuild ทุก request เพื่อให้ Vite host
     * ที่อาจเปลี่ยนพอร์ตได้ ถูกใส่ลง CSP ตามจริง
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
            \Illuminate\Support\Facades\Vite::useCspNonce($nonce);
        }

        $response = $next($request);

        $this->applyCommonHeaders($response, $isApi);
        $this->applyTransportHeaders($response, $request);

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
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $response->headers->set('Cross-Origin-Opener-Policy', $isApi ? 'same-origin' : 'same-origin-allow-popups');
        $response->headers->set('Cross-Origin-Resource-Policy', $isApi ? 'same-origin' : 'cross-origin');
        $response->headers->set('Permissions-Policy', self::PERMISSIONS_POLICY);
    }

    /**
     * Transport-level headers — HSTS เปิดเฉพาะ HTTPS + production
     *
     * เปิด HSTS บน HTTP จะถูก browser ละเลย และเสี่ยงทำผู้ใช้ติด cache
     * ผิดในช่วง dev — ดังนั้น guard 2 ชั้น (env=production + scheme=https)
     */
    private function applyTransportHeaders(Response $response, Request $request): void
    {
        if (! app()->environment('production')) {
            return;
        }

        if (! $request->isSecure()) {
            return;
        }

        // 1 ปี + includeSubDomains + preload-ready
        $response->headers->set(
            'Strict-Transport-Security',
            'max-age=31536000; includeSubDomains; preload',
        );
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
        // dev mode → ห้าม cache template เพราะ Vite host เปลี่ยนได้
        $template = $this->isViteHot()
            ? $this->buildWebCspTemplate()
            : (self::$webCspTemplate ??= $this->buildWebCspTemplate());

        $csp = str_replace('__NONCE__', $nonce ?? '', $template);

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('Cross-Origin-Embedder-Policy', 'unsafe-none');
    }

    /**
     * ตรวจว่า Vite dev server กำลังรันอยู่ (มีไฟล์ public/hot)
     */
    private function isViteHot(): bool
    {
        return app()->environment('local') && is_file(public_path('hot'));
    }

    /**
     * อ่าน Vite dev host จากไฟล์ public/hot และคืน sources สำหรับ CSP
     *
     * รูปแบบไฟล์: บรรทัดแรกคือ URL เช่น http://[::1]:5173 หรือ http://localhost:5173
     *
     * @return array{http: string, ws: string}|null
     */
    private function viteHotSources(): ?array
    {
        $hotFile = public_path('hot');
        if (! is_file($hotFile)) {
            return null;
        }

        $url = trim((string) @file_get_contents($hotFile));
        if ($url === '') {
            return null;
        }

        // แปลง http(s):// → ws(s)://  สำหรับ HMR WebSocket
        $wsUrl = preg_replace('#^http#i', 'ws', $url) ?? $url;

        return ['http' => $url, 'ws' => $wsUrl];
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

        $reverbHost   = $this->configString('broadcasting.connections.reverb.options.host');
        $reverbPort   = $this->configString('broadcasting.connections.reverb.options.port', '443');
        $reverbScheme = $this->configString('broadcasting.connections.reverb.options.scheme', 'https') === 'https' ? 'wss' : 'ws';

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
        /** @var list<string> $cfgFrame */
        $cfgFrame = (array) config('slave::security.csp.frame_src', []);
        /** @var list<string> $cfgMedia */
        $cfgMedia = (array) config('slave::security.csp.media_src', []);

        // Vite dev sources — เฉพาะตอน npm run dev (มีไฟล์ public/hot)
        $viteHot     = $this->viteHotSources();
        $viteHttp    = $viteHot['http'] ?? null;
        $viteWs      = $viteHot['ws'] ?? null;

        $scriptSrc = implode(' ', array_filter([
            "'self'", "'nonce-__NONCE__'",
            $viteHttp,
            ...$cfgScript,
        ]));

        // style-src / style-src-elem — ไม่ใช้ nonce สำหรับ styles
        // nonce ทำให้ 'unsafe-inline' ถูก ignore โดย CSP3 browsers ทุก directive ที่ nonce อยู่
        // script-src คือสิ่งที่ต้องการ nonce เพื่อกันการ inject — styles มีความเสี่ยงต่ำกว่ามาก
        $styleSrcElem = implode(' ', array_filter([
            "'self'", "'unsafe-inline'",
            $viteHttp,
            ...$cfgStyle,
        ]));

        $styleSrc = $styleSrcElem;

        // style-src-attr — React/Radix style={} และ element.style.setProperty()
        $styleSrcAttr = "'unsafe-inline'";

        $fontSrc = implode(' ', array_filter(["'self'", 'data:', ...$cfgFont]));

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
            $viteHttp,
            $viteWs,
            ...$cfgConnect,
        ]));

        // frame-src — 'none' ถ้าไม่มี config, มิเช่นนั้นใช้ sources จาก config
        $frameSrc = $cfgFrame !== []
            ? implode(' ', array_filter(["'self'", ...$cfgFrame]))
            : "'none'";

        // media-src — fallback เป็น https: เมื่อไม่มี minio และไม่มี extra config
        $mediaSrcParts = array_filter(["'self'", $minioUrl ?: null, ...$cfgMedia]);
        $mediaSrc = count($mediaSrcParts) > 1
            ? implode(' ', $mediaSrcParts)
            : implode(' ', $mediaSrcParts) . ' https:';

        return implode('; ', [
            "default-src 'self'",
            "script-src {$scriptSrc}",
            "script-src-attr 'none'",
            "style-src {$styleSrc}",
            "style-src-elem {$styleSrcElem}",
            "style-src-attr {$styleSrcAttr}",
            "font-src {$fontSrc}",
            "img-src {$imgSrc}",
            "connect-src {$connectSrc}",
            "media-src {$mediaSrc}",
            "worker-src 'self' blob:",
            "frame-src {$frameSrc}",
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
