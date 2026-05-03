<?php

declare(strict_types=1);

namespace Core\Base\Http\RateLimiting;

use Core\Base\Contracts\Http\RateLimiting\RateLimiterConfiguratorInterface;
use Core\Base\Contracts\Http\RateLimiting\RequestFingerprinterInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * RateLimitConfigurator — ตั้งค่า Rate Limiting ครอบคลุมทุก scenario ของระบบ
 *
 * Config-driven ผ่าน core.base::myapp.rate_limits — ไม่มี hardcode
 * ทุก limiter รองรับ "window": second | minute | hour | day
 * ทุก limiter รองรับ "decay": กำหนด window ย่อย เช่น decay=15 + window=minute = per 15 นาที
 *
 * ─── Standard Web/API ──────────────────────────────────────────
 *   throttle:api            — API requests ตาม user/guest IP
 *   throttle:web            — Web requests ตาม user/guest IP
 *   throttle:uploads        — File upload ตาม user/guest IP
 *   throttle:resource       — CRUD: แยก read/write
 *
 * ─── Auth & Identity ───────────────────────────────────────────
 *   throttle:oauth          — OAuth token: per-IP + per-client_id (2-layer)
 *   throttle:login          — Brute-force: IP + fingerprint + identifier (3-layer)
 *   throttle:register       — Spam account prevention (per hour)
 *   throttle:otp            — OTP/2FA verification (per 15 min)
 *   throttle:password-reset — Password reset: per-IP + per-email (per hour)
 *   throttle:sensitive      — Sensitive operations (per hour)
 *
 * ─── Cross-Host & Service ──────────────────────────────────────
 *   throttle:service        — M2M/cross-host service calls + burst control
 *   throttle:webhook        — Incoming webhooks: per-IP + per-Origin
 *   throttle:public         — Public API: per X-Api-Key + per-IP fallback
 */
final class RateLimitConfigurator implements RateLimiterConfiguratorInterface
{
    /**
     * ค่า fallback สำหรับกรณี config ยังไม่ถูก load
     *
     * ⚠️  ต้องตรงกับ config/myapp.php rate_limits เสมอ เพื่อป้องกัน behavior drift
     *
     * window: "second" | "minute" | "hour" | "day"
     * decay:  จำนวน window หน่วย — ค่า >1 เปิดใช้ perMinutes/perHours/perSeconds
     * burst:  max per second สำหรับ service limiter (ป้องกัน spike)
     *
     * @var array<string, array<string, int|string>>
     */
    private const array DEFAULTS = [
        // ── Standard ────────────────────────────────────────────
        'api' => ['user' => 120,  'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'web' => ['user' => 120,  'guest' => 30,  'window' => 'minute', 'decay' => 1],
        'uploads' => ['user' => 100,  'guest' => 10,  'window' => 'minute', 'decay' => 1],
        'resource' => ['read' => 60,   'write' => 20,  'window' => 'minute', 'decay' => 1],
        // ── Auth ────────────────────────────────────────────────
        'oauth' => ['ip' => 20,    'client' => 10,  'window' => 'minute', 'decay' => 1],
        'login' => ['ip' => 20,    'fingerprint' => 10, 'email' => 5, 'window' => 'minute', 'decay' => 1],
        'register' => ['ip' => 5,     'window' => 'hour',   'decay' => 1],
        'otp' => ['limit' => 5,  'window' => 'minute', 'decay' => 15],   // 5 ครั้ง / 15 นาที
        'password_reset' => ['ip' => 5,    'email' => 3,    'window' => 'hour',   'decay' => 1],
        'sensitive' => ['limit' => 5,  'window' => 'hour',   'decay' => 1],
        // ── Cross-Host & Service ─────────────────────────────────
        'service' => ['limit' => 600, 'burst' => 30,  'window' => 'minute', 'decay' => 1],
        'webhook' => ['ip' => 60,    'origin' => 120, 'window' => 'minute', 'decay' => 1],
        'public' => ['key' => 60,   'ip' => 10,      'window' => 'minute', 'decay' => 1],
    ];

    /**
     * @param  RequestFingerprinterInterface  $fingerprinter  inject ผ่าน Laravel DI
     */
    public function __construct(
        private readonly RequestFingerprinterInterface $fingerprinter,
    ) {}

    /**
     * ระบุ service identity สำหรับ M2M/cross-host requests
     *
     * Resolution order (ใช้อันแรกที่มีค่า):
     *   1. X-Service-Id  header    — explicit service identifier
     *   2. client_id     body      — OAuth client credentials
     *   3. Basic Auth    username  — Authorization: Basic base64(client_id:secret)
     *   4. Bearer token  hash      — sha256(token) เพื่อ fixed-length key
     *   5. IP address    fallback  — กรณีไม่มี identifier ใดเลย
     */
    private static function resolveServiceId(Request $request): string
    {
        $token = $request->bearerToken();

        $serviceId = $request->header('X-Service-Id')
            ?? $request->input('client_id')
            ?? $request->getUser()
            ?? ($token !== null ? hash('sha256', $token) : null)
            ?? $request->ip()
            ?? '';

        return is_scalar($serviceId) ? (string) $serviceId : '';
    }

    /**
     * ลงทะเบียน rate limiter ทั้งหมดเข้า Laravel RateLimiter
     *
     * เรียกจาก ServiceProvider::boot() เท่านั้น
     */
    public function configure(): void
    {
        // ── Standard ──────────────────────────────────────────
        $this->forUserGuestLimiter('api');
        $this->forUserGuestLimiter('web');
        $this->forUserGuestLimiter('uploads');
        $this->forResource();
        // ── Auth ──────────────────────────────────────────────
        $this->forOauth();
        $this->forLogin();
        $this->forRegister();
        $this->forOtp();
        $this->forPasswordReset();
        $this->forSensitive();
        // ── Cross-Host & Service ───────────────────────────────
        $this->forService();
        $this->forWebhook();
        $this->forPublic();
    }

    // ═══════════════════════════════════════════════════════════
    // Helpers (boot-time only — ไม่มี per-request overhead)
    // ═══════════════════════════════════════════════════════════

    /**
     * อ่านค่า int จาก config พร้อม fallback จาก DEFAULTS
     *
     * @param  string  $limiter  ชื่อ limiter เช่น "api", "service"
     * @param  string  $key  ชื่อ sub-key เช่น "user", "limit", "burst"
     */
    private function cfg(string $limiter, string $key): int
    {
        $defaults = self::DEFAULTS[$limiter] ?? [];
        $default = (int) ($defaults[$key] ?? 0);

        $val = config("core.base::myapp.rate_limits.{$limiter}.{$key}");

        return is_scalar($val) ? (int) $val : $default;
    }

    /**
     * อ่าน time window จาก config
     *
     * @return string "second" | "minute" | "hour" | "day"
     */
    private function window(string $limiter): string
    {
        $defaults = self::DEFAULTS[$limiter] ?? [];
        $default = (string) ($defaults['window'] ?? 'minute');

        $val = config("core.base::myapp.rate_limits.{$limiter}.window");

        return is_scalar($val) ? (string) $val : $default;
    }

    /**
     * อ่าน decay (จำนวน window unit) เช่น decay=15 + window=minute → per 15 minutes
     *
     * @return int >= 1
     */
    private function decay(string $limiter): int
    {
        $defaults = self::DEFAULTS[$limiter] ?? [];
        $default = (int) ($defaults['decay'] ?? 1);

        $val = config("core.base::myapp.rate_limits.{$limiter}.decay");
        $decay = is_scalar($val) ? (int) $val : $default;

        return max(1, $decay);
    }

    /**
     * สร้าง Limit object ตาม max, window และ decay
     *
     * decay > 1 เปิดใช้ perMinutes/perHours/perSeconds แบบ custom window:
     *   makeLimit(5, 'minute', 15)  → Limit::perMinutes(15, 5)   = 5 req / 15 นาที
     *   makeLimit(10, 'hour',  2)   → Limit::perHours(2, 10)     = 10 req / 2 ชั่วโมง
     *   makeLimit(30, 'second', 1)  → Limit::perSecond(30)       = 30 req / second
     *
     * @param  int  $max  จำนวน requests สูงสุดใน window นั้น
     * @param  string  $window  "second" | "minute" | "hour" | "day"
     * @param  int  $decay  จำนวน window unit (default=1)
     */
    private function makeLimit(int $max, string $window, int $decay = 1): Limit
    {
        return match ($window) {
            'second' => Limit::perSecond($max, $decay),
            'hour' => Limit::perHour($max, $decay * 60),  // decay=2 → 120 นาที = 2 ชั่วโมง
            'day' => Limit::perDay($max),
            default => $decay > 1 ? Limit::perMinutes($decay, $max) : Limit::perMinute($max),
        };
    }

    // ═══════════════════════════════════════════════════════════
    // Shared Patterns
    // ═══════════════════════════════════════════════════════════

    /**
     * User/Guest pattern: ใช้ร่วมกันระหว่าง api, web, uploads
     *
     * authenticated → จำกัดต่อ user ID  (ผ่อนปรนกว่า)
     * anonymous     → จำกัดต่อ IP       (เข้มงวดกว่า)
     */
    private function forUserGuestLimiter(string $name): void
    {
        $userLimit = $this->cfg($name, 'user');
        $guestLimit = $this->cfg($name, 'guest');
        $window = $this->window($name);
        $decay = $this->decay($name);

        RateLimiter::for(
            $name,
            fn (Request $request) => $request->user()
                ? $this->makeLimit($userLimit, $window, $decay)->by("{$name}:user:".((string) $request->user()->id))
                : $this->makeLimit($guestLimit, $window, $decay)->by("{$name}:guest:".((string) $request->ip())),
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Standard Web / API
    // ═══════════════════════════════════════════════════════════

    /**
     * Resource CRUD: แยก read/write
     *
     * ใช้กับ route middleware: throttle:resource
     * read  (GET)                   → ผ่อนปรน per user/IP
     * write (POST/PUT/PATCH/DELETE) → เข้มงวด per user/IP
     */
    private function forResource(): void
    {
        $read = $this->cfg('resource', 'read');
        $write = $this->cfg('resource', 'write');
        $window = $this->window('resource');
        $decay = $this->decay('resource');

        RateLimiter::for('resource', function (Request $request) use ($read, $write, $window, $decay) {
            $key = (string) ($request->user()?->id ?? $request->ip());
            $isWrite = \in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

            return $isWrite
                ? $this->makeLimit($write, $window, $decay)->by("resource:write:{$key}")
                : $this->makeLimit($read, $window, $decay)->by("resource:read:{$key}");
        });
    }

    // ═══════════════════════════════════════════════════════════
    // Auth & Identity
    // ═══════════════════════════════════════════════════════════

    /**
     * OAuth Token Endpoint: ป้องกัน credential brute-force (2-layer)
     *
     * ใช้กับ route middleware: throttle:oauth
     *
     * Layer 1 — per IP      : กลองกว้าง ป้องกัน bot flood
     * Layer 2 — per client  : ป้องกัน brute-force client_secret แม้เปลี่ยน IP
     *
     * client_id resolution: body → Basic Auth → IP fallback
     */
    private function forOauth(): void
    {
        $ipLimit = $this->cfg('oauth', 'ip');
        $clientLimit = $this->cfg('oauth', 'client');
        $window = $this->window('oauth');
        $decay = $this->decay('oauth');

        RateLimiter::for('oauth', function (Request $request) use ($ipLimit, $clientLimit, $window, $decay) {
            $clientIdRaw = $request->input('client_id') ?? $request->getUser() ?? $request->ip() ?? '';
            $clientId = is_scalar($clientIdRaw) ? (string) $clientIdRaw : '';

            return [
                $this->makeLimit($ipLimit, $window, $decay)->by('oauth:ip:'.((string) $request->ip())),
                $this->makeLimit($clientLimit, $window, $decay)->by("oauth:client:{$clientId}"),
            ];
        });
    }

    /**
     * Login: ป้องกัน brute-force (3-layer)
     *
     * ใช้กับ route middleware: throttle:login
     *
     * Layer 1 — per IP          : กรองกว้าง ป้องกัน bot
     * Layer 2 — per Fingerprint : ทนต่อ IP rotation (VPN/proxy)
     * Layer 3 — per Identifier  : email / username / client_id — ป้องกัน per-account stuffing
     */
    private function forLogin(): void
    {
        $perIp = $this->cfg('login', 'ip');
        $perFingerprint = $this->cfg('login', 'fingerprint');
        $perIdentifier = $this->cfg('login', 'email');
        $window = $this->window('login');
        $decay = $this->decay('login');

        RateLimiter::for('login', function (Request $request) use ($perIp, $perFingerprint, $perIdentifier, $window, $decay) {
            $fp = (string) $this->fingerprinter->generate($request);
            $idRaw = $request->input('email')
                ?? $request->input('username')
                ?? $request->input('client_id')
                ?? $request->ip()
                ?? '';
            $identifier = strtolower(is_scalar($idRaw) ? (string) $idRaw : '');

            return [
                $this->makeLimit($perIp, $window, $decay)->by('login:ip:'.((string) $request->ip())),
                $this->makeLimit($perFingerprint, $window, $decay)->by("login:fp:{$fp}"),
                $this->makeLimit($perIdentifier, $window, $decay)->by("login:id:{$identifier}"),
            ];
        });
    }

    /**
     * Register: ป้องกัน spam account creation
     *
     * ใช้กับ route middleware: throttle:register
     * default: 5 per hour per IP
     */
    private function forRegister(): void
    {
        $limit = $this->cfg('register', 'ip');
        $window = $this->window('register');
        $decay = $this->decay('register');

        RateLimiter::for(
            'register',
            fn (Request $request) => $this->makeLimit($limit, $window, $decay)->by('register:'.((string) $request->ip())),
        );
    }

    /**
     * OTP / 2FA Verification: ป้องกัน brute-force รหัส OTP
     *
     * ใช้กับ route middleware: throttle:otp
     * default: 5 ครั้ง / 15 นาที ต่อ user/email/phone/IP
     *
     * identifier resolution: user_id → email → phone → IP fallback
     */
    private function forOtp(): void
    {
        $limit = $this->cfg('otp', 'limit');
        $window = $this->window('otp');
        $decay = $this->decay('otp');

        RateLimiter::for('otp', function (Request $request) use ($limit, $window, $decay) {
            $idRaw = $request->user()?->id
                ?? $request->input('email')
                ?? $request->input('phone')
                ?? $request->ip()
                ?? '';
            $identifier = is_scalar($idRaw) ? (string) $idRaw : '';

            return $this->makeLimit($limit, $window, $decay)->by("otp:{$identifier}");
        });
    }

    /**
     * Password Reset: ป้องกัน email enumeration และ brute-force reset link
     *
     * ใช้กับ route middleware: throttle:password-reset
     *
     * Layer 1 — per IP    : 5 per hour  (กรองกว้าง)
     * Layer 2 — per Email : 3 per hour  (ป้องกัน per-account flooding)
     */
    private function forPasswordReset(): void
    {
        $perIp = $this->cfg('password_reset', 'ip');
        $perEmail = $this->cfg('password_reset', 'email');
        $window = $this->window('password_reset');
        $decay = $this->decay('password_reset');

        RateLimiter::for('password-reset', function (Request $request) use ($perIp, $perEmail, $window, $decay) {
            $emailRaw = $request->input('email') ?? $request->ip() ?? '';
            $email = strtolower(is_scalar($emailRaw) ? (string) $emailRaw : '');

            return [
                $this->makeLimit($perIp, $window, $decay)->by('pwd-reset:ip:'.((string) $request->ip())),
                $this->makeLimit($perEmail, $window, $decay)->by("pwd-reset:email:{$email}"),
            ];
        });
    }

    /**
     * Sensitive Operations: จำกัด operations ที่มีความสำคัญสูง
     *
     * ใช้กับ route middleware: throttle:sensitive
     * เหมาะสำหรับ: change password, delete account, 2FA reset, export data
     * default: 5 per hour per user/IP
     */
    private function forSensitive(): void
    {
        $limit = $this->cfg('sensitive', 'limit');
        $window = $this->window('sensitive');
        $decay = $this->decay('sensitive');

        RateLimiter::for(
            'sensitive',
            fn (Request $request) => $this->makeLimit($limit, $window, $decay)
                ->by('sensitive:'.((string) ($request->user()?->id ?? $request->ip()))),
        );
    }

    // ═══════════════════════════════════════════════════════════
    // Cross-Host & Service
    // ═══════════════════════════════════════════════════════════

    /**
     * M2M / Cross-Host Service Calls: รองรับ machine-to-machine
     *
     * ใช้กับ route middleware: throttle:service
     * เหมาะสำหรับ: erped → pppportal, microservice internal calls
     *
     * Layer 1 — burst     : Limit::perSecond(30)    — ป้องกัน spike ชั่วคราว
     * Layer 2 — sustained : Limit::perMinute(600)   — ceiling รายนาที
     *
     * Service identity resolution (resolveServiceId):
     *   X-Service-Id → client_id (body) → Basic Auth → Bearer token hash → IP
     *
     * ⚠️  ควรใช้คู่กับ crosshost.verify หรือ passport.signed middleware เสมอ
     *     เพื่อให้แน่ใจว่า service identity ได้รับการ authenticate ก่อน
     */
    private function forService(): void
    {
        $limit = $this->cfg('service', 'limit');
        $burst = $this->cfg('service', 'burst');
        $window = $this->window('service');
        $decay = $this->decay('service');

        RateLimiter::for('service', function (Request $request) use ($limit, $burst, $window, $decay) {
            $serviceId = self::resolveServiceId($request);

            return [
                Limit::perSecond($burst)->by("service:burst:{$serviceId}"),
                $this->makeLimit($limit, $window, $decay)->by("service:sustained:{$serviceId}"),
            ];
        });
    }

    /**
     * Incoming Webhooks: จำกัด webhook ขาเข้าจาก external providers
     *
     * ใช้กับ route middleware: throttle:webhook
     * เหมาะสำหรับ: Stripe, GitHub, LINE, SCB, KBank webhooks
     *
     * Layer 1 — per IP     : 60 rpm  (กรองระดับ IP — ป้องกัน flood)
     * Layer 2 — per Origin : 120 rpm (ผ่อนปรนกว่าเพราะ provider ส่งจาก CDN หลาย IP)
     *
     * Origin resolution: Origin header → Referer → X-Forwarded-Host → IP
     * Normalize: ตัด path/query ออก เก็บเฉพาะ hostname
     */
    private function forWebhook(): void
    {
        $ipLimit = $this->cfg('webhook', 'ip');
        $originLimit = $this->cfg('webhook', 'origin');
        $window = $this->window('webhook');
        $decay = $this->decay('webhook');

        RateLimiter::for('webhook', function (Request $request) use ($ipLimit, $originLimit, $window, $decay) {
            $source = (string) (
                $request->header('Origin')
                ?? $request->header('Referer')
                ?? $request->header('X-Forwarded-Host')
                ?? $request->ip()
            );
            $host = (string) (parse_url($source, PHP_URL_HOST) ?? $source);

            return [
                $this->makeLimit($ipLimit, $window, $decay)->by('webhook:ip:'.((string) $request->ip())),
                $this->makeLimit($originLimit, $window, $decay)->by("webhook:host:{$host}"),
            ];
        });
    }

    /**
     * Public API: สำหรับ external developers / third-party integrations
     *
     * ใช้กับ route middleware: throttle:public
     *
     * มี X-Api-Key   → จำกัดต่อ API key: 60 rpm (hash key เพื่อป้องกัน key leaking ใน logs)
     * ไม่มี X-Api-Key → fallback ต่อ IP: 10 rpm  (เข้มงวดกว่า)
     *
     * ⚠️  X-Api-Key ต้องผ่านการ validate ใน middleware ก่อน throttle นี้
     *     ห้ามใช้ throttle:public กับ endpoint ที่ไม่ต้องการ authentication
     */
    private function forPublic(): void
    {
        $perKey = $this->cfg('public', 'key');
        $perIp = $this->cfg('public', 'ip');
        $window = $this->window('public');
        $decay = $this->decay('public');

        RateLimiter::for('public', function (Request $request) use ($perKey, $perIp, $window, $decay) {
            $apiKey = (string) ($request->header('X-Api-Key') ?? '');

            if ($apiKey !== '') {
                // hash key ป้องกัน raw API key ปรากฏใน Redis key (log safety)
                return $this->makeLimit($perKey, $window, $decay)->by('public:key:'.hash('sha256', $apiKey));
            }

            return $this->makeLimit($perIp, $window, $decay)->by('public:ip:'.((string) $request->ip()));
        });
    }
}
