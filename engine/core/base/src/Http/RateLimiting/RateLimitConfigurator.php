<?php

declare(strict_types=1);

namespace Core\Base\Http\RateLimiting;

use Core\Base\Contracts\Http\RateLimiting\RateLimiterConfiguratorInterface;
use Core\Base\Contracts\Http\RateLimiting\RequestFingerprinterInterface;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * RateLimitConfigurator — ตั้งค่า Rate Limiting ทั้งหมดของระบบ
 *
 * Config-driven ผ่าน oauth2.rate_limits — ไม่มี hardcode
 * ทุก config() call มี fallback จาก DEFAULTS เพื่อป้องกัน null → TypeError
 * ใช้ RequestFingerprinterInterface สำหรับ login layer ที่ทนต่อ IP rotation
 *
 * Rate Limiters ที่ลงทะเบียน:
 *   - api       : throttle:api       — API requests ตาม user/guest
 *   - web       : throttle:web       — web requests ตาม user/guest
 *   - login     : throttle:login     — 3-layer brute-force (IP + fingerprint + email)
 *   - register  : throttle:register  — ป้องกัน spam account
 *   - uploads   : throttle:uploads   — file upload ตาม user/guest
 *   - resource  : throttle:resource  — แยก read/write สำหรับ resource endpoints
 *   - sensitive : throttle:sensitive — sensitive operations (change password ฯลฯ)
 */
final class RateLimitConfigurator implements RateLimiterConfiguratorInterface
{
    /**
     * ค่า default สำหรับกรณี config ยังไม่ถูก load
     *
     * ⚠️  ต้องตรงกับ config/oauth2.php rate_limits เสมอ เพื่อป้องกัน behavior drift
     *
     * @var array<string, array<string, int>|int>
     */
    private const DEFAULTS = [
        'api' => ['user' => 120, 'guest' => 10],
        'web' => ['user' => 120, 'guest' => 5],
        'login' => ['ip' => 20, 'fingerprint' => 10, 'email' => 5],
        'register' => ['ip' => 5],
        'uploads' => ['user' => 100, 'guest' => 10],
        'resource' => ['read' => 60, 'write' => 20],
        'sensitive' => 5,
    ];

    /**
     * @param  RequestFingerprinterInterface  $fingerprinter  inject ผ่าน Laravel DI อัตโนมัติ
     */
    public function __construct(
        private readonly RequestFingerprinterInterface $fingerprinter,
    ) {}

    /**
     * ลงทะเบียน rate limiter ทั้งหมดเข้า Laravel RateLimiter
     *
     * เรียกจาก ServiceProvider::boot() เท่านั้น
     */
    public function configure(): void
    {
        $this->forApi();
        $this->forWeb();
        $this->forLogin();
        $this->forRegister();
        $this->forUploads();
        $this->forResource();
        $this->forSensitive();
    }

    /**
     * API: จำกัดตาม user ID (authenticated) หรือ guest IP
     *
     * ใช้กับ route middleware: throttle:api
     * default: 120 rpm per user, 10 rpm per guest IP
     */
    private function forApi(): void
    {
        $limits = config('core.base::myapp.rate_limits.api', self::DEFAULTS['api']);
        $user = (int) ($limits['user'] ?? self::DEFAULTS['api']['user']);
        $guest = (int) ($limits['guest'] ?? self::DEFAULTS['api']['guest']);

        RateLimiter::for(
            'api',
            static fn (Request $request) => $request->user()
                ? Limit::perMinute($user)->by('api:user:'.$request->user()->id)
                : Limit::perMinute($guest)->by('api:guest:'.$request->ip()),
        );
    }

    /**
     * Web: จำกัดตาม user ID หรือ guest IP
     *
     * ใช้กับ route middleware: throttle:web
     * default: 120 rpm per user, 5 rpm per guest IP
     */
    private function forWeb(): void
    {
        $limits = config('core.base::myapp.rate_limits.web', self::DEFAULTS['web']);
        $user = (int) ($limits['user'] ?? self::DEFAULTS['web']['user']);
        $guest = (int) ($limits['guest'] ?? self::DEFAULTS['web']['guest']);

        RateLimiter::for(
            'web',
            static fn (Request $request) => $request->user()
                ? Limit::perMinute($user)->by('web:user:'.$request->user()->id)
                : Limit::perMinute($guest)->by('web:guest:'.$request->ip()),
        );
    }

    /**
     * Login: ป้องกัน brute-force แบบ 3 layer
     *
     * ใช้กับ route middleware: throttle:login
     *
     * Layer 1 — per IP (20 rpm):
     *   กรองกว้าง ป้องกัน bot จาก IP เดียวกัน
     *
     * Layer 2 — per Fingerprint (10 rpm):
     *   multi-signal hash (IP + UA + Accept-Language + Accept-Encoding + X-Visitor-Id)
     *   ทนต่อ IP rotation จาก VPN/proxy
     *
     * Layer 3 — per Email (5 rpm):
     *   ป้องกัน account enumeration และ credential stuffing
     */
    private function forLogin(): void
    {
        $limits = config('core.base::myapp.rate_limits.login', self::DEFAULTS['login']);
        $ip = (int) ($limits['ip'] ?? self::DEFAULTS['login']['ip']);
        $fingerprint = (int) ($limits['fingerprint'] ?? self::DEFAULTS['login']['fingerprint']);
        $email = (int) ($limits['email'] ?? self::DEFAULTS['login']['email']);

        RateLimiter::for('login', function (Request $request) use ($ip, $fingerprint, $email) {
            $fp = $this->fingerprinter->generate($request);

            return [
                Limit::perMinute($ip)->by('login-ip:'.$request->ip()),
                Limit::perMinute($fingerprint)->by('login-fp:'.$fp),
                Limit::perMinute($email)->by('login-email:'.strtolower($request->input('email', ''))),
            ];
        });
    }

    /**
     * Register: จำกัดต่อ IP ป้องกัน spam account creation
     *
     * ใช้กับ route middleware: throttle:register
     * default: 5 rpm per IP
     */
    private function forRegister(): void
    {
        $limits = config('core.base::myapp.rate_limits.register', self::DEFAULTS['register']);
        $limit = (int) ($limits['ip'] ?? self::DEFAULTS['register']['ip']);

        RateLimiter::for(
            'register',
            static fn (Request $request) => Limit::perMinute($limit)->by('register:'.$request->ip()),
        );
    }

    /**
     * Resource CRUD: แยก read/write สำหรับ resource endpoints (users, profiles)
     *
     * ใช้กับ route middleware: throttle:resource
     *
     * - read  (GET):                    60 rpm per user
     * - write (POST/PUT/PATCH/DELETE):  20 rpm per user
     *
     * ถ้าไม่ authenticated ใช้ IP แทน user ID
     */
    private function forResource(): void
    {
        $limits = config('core.base::myapp.rate_limits.resource', self::DEFAULTS['resource']);
        $read = (int) ($limits['read'] ?? self::DEFAULTS['resource']['read']);
        $write = (int) ($limits['write'] ?? self::DEFAULTS['resource']['write']);

        RateLimiter::for('resource', static function (Request $request) use ($read, $write) {
            $userId = $request->user()?->id ?? $request->ip();
            $isWrite = in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], strict: true);

            return $isWrite
                ? Limit::perMinute($write)->by('resource:write:'.$userId)
                : Limit::perMinute($read)->by('resource:read:'.$userId);
        });
    }

    /**
     * Sensitive: จำกัด operations ที่มีความสำคัญสูง
     *
     * ใช้กับ route middleware: throttle:sensitive
     * เหมาะสำหรับ: change password, delete account, export data
     *
     * ถ้าไม่ authenticated ใช้ IP แทน user ID
     * default: 5 rpm per user
     */
    private function forSensitive(): void
    {
        $limit = (int) config('core.base::myapp.rate_limits.sensitive', self::DEFAULTS['sensitive']);

        RateLimiter::for(
            'sensitive',
            static fn (Request $request) => Limit::perMinute($limit)->by('sensitive:'.($request->user()?->id ?? $request->ip())),
        );
    }

    /**
     * Uploads: จำกัดตาม user ID หรือ guest IP
     *
     * ใช้กับ route middleware: throttle:uploads
     * default: 100 rpm per user, 10 rpm per guest IP
     */
    private function forUploads(): void
    {
        $limits = config('core.base::myapp.rate_limits.uploads', self::DEFAULTS['uploads']);
        $user = (int) ($limits['user'] ?? self::DEFAULTS['uploads']['user']);
        $guest = (int) ($limits['guest'] ?? self::DEFAULTS['uploads']['guest']);

        RateLimiter::for(
            'uploads',
            static fn (Request $request) => $request->user()
                ? Limit::perMinute($user)->by('upload:user:'.$request->user()->id)
                : Limit::perMinute($guest)->by('upload:guest:'.$request->ip()),
        );
    }
}
