<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Logs;

use Core\Base\Support\Helpers\Logs\Contracts\AppLoggerInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * AppLogger — Logging Helper ครบวงจร สำหรับระบบ Production
 *
 * ═══════════════════════════════════════════════════════════════
 *  PSR-3 Log Levels (ทุก log call ได้รับ context อัตโนมัติ)
 * ═══════════════════════════════════════════════════════════════
 *  emergency($msg, $ctx)  — ระบบล่ม ต้องปลุกทีมทันที
 *  alert($msg, $ctx)      — ต้องแก้ด่วน (DB เต็ม, disk หมด)
 *  critical($msg, $ctx)   — component สำคัญล้มเหลว
 *  error($msg, $ctx)      — error ที่ต้องสอบสวน
 *  warning($msg, $ctx)    — บางอย่างผิดปกติแต่ยังทำงานได้
 *  notice($msg, $ctx)     — เหตุการณ์สำคัญแต่ไม่ใช่ error
 *  info($msg, $ctx)       — log ทั่วไป
 *  debug($msg, $ctx)      — development/diagnostic
 *
 * ═══════════════════════════════════════════════════════════════
 *  Exception Logging
 * ═══════════════════════════════════════════════════════════════
 *  exception($e, $msg, $ctx)  — log exception พร้อม stack trace (10 frames)
 *  logError($e, $ctx)         — backward compatible alias ของ exception()
 *
 * ═══════════════════════════════════════════════════════════════
 *  Security Logging → channel 'security' (fallback: default)
 * ═══════════════════════════════════════════════════════════════
 *  security($event, $ctx)                 — security event ทั่วไป
 *  loginAttempt($identifier, $ok, $ctx)   — login สำเร็จ/ล้มเหลว
 *  unauthorized($action, $ctx)            — unauthorized access
 *  suspiciousActivity($desc, $ctx)        — พฤติกรรมน่าสงสัย
 *
 * ═══════════════════════════════════════════════════════════════
 *  Audit Logging → channel 'audit' (fallback: default)
 * ═══════════════════════════════════════════════════════════════
 *  audit($action, $subject, $changes, $ctx) — user action trail (CRUD)
 *  logUserAccess($text, $ctx)               — backward compatible
 *
 * ═══════════════════════════════════════════════════════════════
 *  Performance Logging → channel 'performance' (fallback: default)
 * ═══════════════════════════════════════════════════════════════
 *  slowQuery($sql, $durationMs, $ctx)        — slow database query
 *  slowRequest($url, $method, $durationMs, $ctx) — slow HTTP request
 *
 * ═══════════════════════════════════════════════════════════════
 *  Context & Channel
 * ═══════════════════════════════════════════════════════════════
 *  withContext($ctx)   — คืน instance ใหม่พร้อม default context (immutable)
 *  channel($name)      — สลับ log channel โดยตรง
 *
 * ═══════════════════════════════════════════════════════════════
 *  Utility
 * ═══════════════════════════════════════════════════════════════
 *  mask($data)         — ซ่อน sensitive fields ก่อน log
 *
 * ─── Auto-enrichment ────────────────────────────────────────
 *  ทุก log call จะได้รับ context อัตโนมัติ:
 *  - user_id, user_name, user_email (ถ้า authenticated)
 *  - ip, method, url, request_id, user_agent (ถ้ามี HTTP request)
 *  - context ที่ส่งผ่าน withContext() หรือ parameter
 *
 * ─── Sensitive Data Masking ─────────────────────────────────
 *  Keys ที่มีคำ: password, token, secret, key, card, cvv, pin,
 *  ssn, pepper จะถูก mask เป็น "***" อัตโนมัติก่อน log ทุกครั้ง
 *
 * ─── Channel Configuration ──────────────────────────────────
 *  ตั้งค่า channel ใน config/logging.php:
 *  'security_channel'    => 'security'    (หรือ 'daily', 'slack', ฯลฯ)
 *  'audit_channel'       => 'audit'
 *  'performance_channel' => 'performance'
 */
final class AppLogger implements AppLoggerInterface
{
    // ─── Sensitive key patterns ──────────────────────────────────────

    /** @var string[] คำที่ใช้ตรวจ sensitive key (case-insensitive, partial match) */
    private const SENSITIVE_PATTERNS = [
        'password', 'passwd', 'secret', 'token', 'api_key', 'apikey',
        'private_key', 'signing_key', 'encryption_key', 'hmac_key', 'jwt_secret',
        'pepper', 'passphrase', 'access_token', 'refresh_token', 'auth_token',
        'credit_card', 'card_number', 'cvv', 'cvc', 'pin', 'ssn', 'passport',
    ];

    /** @var array<string, mixed> default context ที่ merge เข้าทุก log call */
    private array $defaultContext = [];

    // ═══════════════════════════════════════════════════════════
    //  PSR-3 Log Levels
    // ═══════════════════════════════════════════════════════════

    /**
     * ระบบล่มสมบูรณ์ — ต้องปลุกทีม on-call ทันที
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function emergency(string $message, array $context = []): void
    {
        Log::emergency($message, $this->buildContext($context));
    }

    /**
     * ต้องแก้ไขด่วน — เช่น DB เต็ม, disk หมด, external service ล้มเหลว
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function alert(string $message, array $context = []): void
    {
        Log::alert($message, $this->buildContext($context));
    }

    /**
     * Component สำคัญล้มเหลว — เช่น payment gateway, authentication service
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function critical(string $message, array $context = []): void
    {
        Log::critical($message, $this->buildContext($context));
    }

    /**
     * Runtime error — ต้องสอบสวนและแก้ไข
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function error(string $message, array $context = []): void
    {
        Log::error($message, $this->buildContext($context));
    }

    /**
     * เหตุการณ์ผิดปกติที่ไม่ใช่ error — ควรตรวจสอบ
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function warning(string $message, array $context = []): void
    {
        Log::warning($message, $this->buildContext($context));
    }

    /**
     * เหตุการณ์สำคัญที่ควรบันทึก — ไม่ใช่ error แต่น่าสังเกต
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function notice(string $message, array $context = []): void
    {
        Log::notice($message, $this->buildContext($context));
    }

    /**
     * Informational log ทั่วไป — เช่น user action, business event
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function info(string $message, array $context = []): void
    {
        Log::info($message, $this->buildContext($context));
    }

    /**
     * Debug/diagnostic log — ใช้ใน development, ปิดใน production
     *
     * @param  string  $message  ข้อความ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function debug(string $message, array $context = []): void
    {
        Log::debug($message, $this->buildContext($context));
    }

    // ═══════════════════════════════════════════════════════════
    //  Exception Logging
    // ═══════════════════════════════════════════════════════════

    /**
     * Log exception พร้อม stack trace ที่ structured
     *
     * บันทึก:
     *  - class, message, code, file, line
     *  - stack trace (10 frames แรก)
     *  - previous exception (ถ้ามี)
     *  - context ที่ส่งมา + auto-enrichment
     *
     * @param  Throwable  $e  Exception ที่ต้องการ log
     * @param  string  $message  ข้อความเพิ่มเติม (optional)
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function exception(Throwable $e, string $message = '', array $context = []): void
    {
        $logMessage = $message !== ''
            ? $message
            : $e->getMessage();

        $exceptionContext = $this->formatException($e);

        Log::error($logMessage, $this->buildContext(array_merge($exceptionContext, $context)));
    }

    /**
     * Log exception ระดับ error — backward compatible alias ของ exception()
     *
     * @param  Throwable  $throwable  Exception ที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logError(Throwable $throwable, array $context = []): void
    {
        $this->exception($throwable, '', $context);
    }

    // ═══════════════════════════════════════════════════════════
    //  Security Logging
    // ═══════════════════════════════════════════════════════════

    /**
     * Log security event ทั่วไป → channel 'security'
     *
     * ตัวอย่าง: 'rate_limit_exceeded', 'brute_force_detected', 'csrf_mismatch'
     *
     * @param  string  $event  ชื่อ security event
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function security(string $event, array $context = []): void
    {
        $this->securityChannel()->warning("[SECURITY] {$event}", $this->buildContext(array_merge(
            ['security_event' => $event],
            $context,
        )));
    }

    /**
     * Log login attempt — ทั้งสำเร็จและล้มเหลว → channel 'security'
     *
     * ควรเรียกทุกครั้งหลัง Auth::attempt() — สำหรับ brute force detection
     *
     * @param  string  $identifier  email หรือ username ที่ใช้ login
     * @param  bool  $success  true = login สำเร็จ
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม (เช่น guard, provider)
     */
    public function loginAttempt(string $identifier, bool $success, array $context = []): void
    {
        $event = $success ? 'login_success' : 'login_failed';
        $level = $success ? 'info' : 'warning';
        $message = $success
            ? "[SECURITY] Login สำเร็จ: {$identifier}"
            : "[SECURITY] Login ล้มเหลว: {$identifier}";

        $this->securityChannel()->{$level}($message, $this->buildContext(array_merge(
            ['security_event' => $event, 'identifier' => $identifier, 'success' => $success],
            $context,
        )));
    }

    /**
     * Log unauthorized access attempt → channel 'security'
     *
     * @param  string  $action  action ที่ถูกปฏิเสธ (เช่น 'delete_user', 'view_report')
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function unauthorized(string $action = '', array $context = []): void
    {
        $message = $action !== ''
            ? "[SECURITY] Unauthorized: {$action}"
            : '[SECURITY] Unauthorized access attempt';

        $this->securityChannel()->warning($message, $this->buildContext(array_merge(
            ['security_event' => 'unauthorized', 'action' => $action],
            $context,
        )));
    }

    /**
     * Log พฤติกรรมที่น่าสงสัย → channel 'security' ระดับ critical
     *
     * ตัวอย่าง: SQL injection attempt, XSS payload detected, unusual data pattern
     *
     * @param  string  $description  คำอธิบายพฤติกรรม
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function suspiciousActivity(string $description, array $context = []): void
    {
        $this->securityChannel()->critical("[SECURITY] Suspicious: {$description}", $this->buildContext(array_merge(
            ['security_event' => 'suspicious_activity', 'description' => $description],
            $context,
        )));
    }

    // ═══════════════════════════════════════════════════════════
    //  Audit Logging
    // ═══════════════════════════════════════════════════════════

    /**
     * Log user action สำหรับ audit trail → channel 'audit'
     *
     * ใช้ใน Observer, Policy, หรือ Controller เพื่อบันทึกทุก action สำคัญ
     *
     * ตัวอย่าง:
     * ```php
     * $logger->audit('create', 'User', ['name' => ['old' => null, 'new' => 'John']]);
     * $logger->audit('delete', "Order#{$id}");
     * $logger->audit('export', 'Report', [], ['format' => 'pdf', 'rows' => 1500]);
     * ```
     *
     * @param  string  $action  action ที่เกิดขึ้น (create, update, delete, export, ฯลฯ)
     * @param  string  $subject  สิ่งที่ถูก action (เช่น 'User', 'Order#42')
     * @param  array<string, mixed>  $changes  ค่าที่เปลี่ยน ['field' => ['old' => x, 'new' => y]]
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function audit(
        string $action,
        string $subject = '',
        array $changes = [],
        array $context = [],
    ): void {
        $message = $subject !== ''
            ? "[AUDIT] {$action}: {$subject}"
            : "[AUDIT] {$action}";

        $this->auditChannel()->info($message, $this->buildContext(array_merge(
            [
                'audit_action' => $action,
                'audit_subject' => $subject,
                'changes' => $this->mask($changes),
            ],
            $context,
        )));
    }

    /**
     * Log user access พร้อม auth context → backward compatible
     *
     * @param  string  $text  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logUserAccess(string $text = '', array $context = []): void
    {
        $this->auditChannel()->info(
            "[AUDIT] {$text}",
            $this->buildContext($context),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Performance Logging
    // ═══════════════════════════════════════════════════════════

    /**
     * Log slow database query → channel 'performance'
     *
     * ⚠️ หลีกเลี่ยงการส่ง raw SQL ที่มี user input โดยตรง
     *    ใช้ query string ที่มี placeholder แทน (เช่น "select * from users where id = ?")
     *
     * @param  string  $sql  SQL query string
     * @param  float  $durationMs  ระยะเวลาที่ใช้ (มิลลิวินาที)
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม (เช่น bindings, connection)
     */
    public function slowQuery(string $sql, float $durationMs, array $context = []): void
    {
        $this->performanceChannel()->warning(
            sprintf('[PERF] Slow query (%.2fms)', $durationMs),
            $this->buildContext(array_merge(
                ['sql' => $sql, 'duration_ms' => $durationMs],
                $context,
            )),
        );
    }

    /**
     * Log slow HTTP request → channel 'performance'
     *
     * @param  string  $url  URL ที่ใช้เวลานาน
     * @param  string  $method  HTTP method (GET, POST, ฯลฯ)
     * @param  float  $durationMs  ระยะเวลาที่ใช้ (มิลลิวินาที)
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม (เช่น status_code, memory_mb)
     */
    public function slowRequest(
        string $url,
        string $method,
        float $durationMs,
        array $context = [],
    ): void {
        $this->performanceChannel()->warning(
            sprintf('[PERF] Slow request %s %s (%.2fms)', $method, $url, $durationMs),
            $this->buildContext(array_merge(
                ['request_url' => $url, 'request_method' => $method, 'duration_ms' => $durationMs],
                $context,
            )),
        );
    }

    // ═══════════════════════════════════════════════════════════
    //  Context & Channel
    // ═══════════════════════════════════════════════════════════

    /**
     * คืน instance ใหม่พร้อม default context ที่ merge เข้าทุก log call (immutable)
     *
     * ใช้เมื่อต้องการ log หลาย events ที่มี context ร่วมกัน:
     * ```php
     * $log = $logger->withContext(['order_id' => 42, 'user_id' => 7]);
     * $log->info('Order created');
     * $log->info('Payment processed');
     * // ทุก log ได้ order_id และ user_id อัตโนมัติ
     * ```
     *
     * @param  array<string, mixed>  $context  Context ที่ต้องการ merge
     * @return static Instance ใหม่ที่มี default context
     */
    public function withContext(array $context): static
    {
        $clone = clone $this;
        $clone->defaultContext = array_merge($this->defaultContext, $context);

        return $clone;
    }

    /**
     * สลับ log channel โดยตรง — คืน PSR-3 Logger ของ channel นั้น
     *
     * ตัวอย่าง:
     * ```php
     * $logger->channel('slack')->critical('Payment gateway down!');
     * $logger->channel('daily')->debug('Debug info');
     * ```
     *
     * @param  string  $name  ชื่อ channel จาก config('logging.channels')
     */
    public function channel(string $name): LoggerInterface
    {
        return Log::channel($name);
    }

    // ═══════════════════════════════════════════════════════════
    //  Utility
    // ═══════════════════════════════════════════════════════════

    /**
     * Mask sensitive fields ใน array ก่อน log (recursive)
     *
     * Key ที่ตรงกับ SENSITIVE_PATTERNS (partial match, case-insensitive)
     * จะถูกแทนค่าด้วย "***"
     *
     * ตัวอย่าง:
     * ```php
     * $logger->mask(['email' => 'a@b.com', 'password' => 'secret123']);
     * // → ['email' => 'a@b.com', 'password' => '***']
     * ```
     *
     * @param  array<string, mixed>  $data  ข้อมูลที่ต้องการ mask
     * @return array<string, mixed> ข้อมูลที่ mask แล้ว
     */
    public function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mask($value);
            } elseif (is_string($key) && $this->isSensitiveKey($key)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    // ─── Private Helpers ────────────────────────────────────────────

    /**
     * สร้าง final context ที่ merge: defaultContext + requestContext + userContext + $extra
     * แล้ว mask sensitive fields ก่อน return
     *
     * @param  array<string, mixed>  $extra  context ที่ caller ส่งมา
     * @return array<string, mixed>
     */
    private function buildContext(array $extra = []): array
    {
        return $this->mask(array_merge(
            $this->defaultContext,
            $this->requestContext(),
            $this->userContext(),
            $extra,
        ));
    }

    /**
     * ดึง HTTP request context — IP, method, URL, request_id, user_agent
     *
     * คืน ['context' => 'console'] ถ้ารันใน CLI
     * คืน [] ถ้าไม่มี request object
     *
     * @return array<string, mixed>
     */
    private function requestContext(): array
    {
        if (app()->runningInConsole()) {
            return ['runtime' => 'console'];
        }

        try {
            $req = request();

            return array_filter([
                'ip' => $req->ip(),
                'method' => $req->method(),
                'url' => $req->fullUrl(),
                'request_id' => $req->header('X-Request-ID', '') ?: null,
                'user_agent' => $req->userAgent() ?: null,
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * ดึง authenticated user context — user_id, user_name, user_email
     *
     * คืน [] ถ้าไม่ได้ authenticated หรือเกิด error
     *
     * @return array<string, mixed>
     */
    private function userContext(): array
    {
        try {
            if (! Auth::check()) {
                return [];
            }

            $user = Auth::user();

            return array_filter([
                'auth_user_id' => $user->id ?? null,
                'auth_user_name' => $user->name ?? null,
                'auth_user_email' => $user->email ?? null,
            ]);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Format exception เป็น structured array สำหรับ log context
     *
     * รวม: class, message, code, file, line, trace (10 frames), caused_by
     *
     * @return array<string, mixed>
     */
    private function formatException(Throwable $e): array
    {
        $frames = array_slice($e->getTrace(), 0, 10);

        $trace = array_map(fn (array $frame): array => [
            'file' => $frame['file'] ?? '[internal]',
            'line' => $frame['line'] ?? 0,
            'function' => ($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''),
        ], $frames);

        $data = [
            'exception' => [
                'class' => $e::class,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $trace,
            ],
        ];

        if ($prev = $e->getPrevious()) {
            $data['exception']['caused_by'] = [
                'class' => $prev::class,
                'message' => $prev->getMessage(),
                'code' => $prev->getCode(),
                'file' => $prev->getFile(),
                'line' => $prev->getLine(),
            ];
        }

        return $data;
    }

    /**
     * ตรวจว่า key ชื่อนี้เป็น sensitive data หรือไม่
     * ใช้ partial match (str_contains) case-insensitive
     */
    private function isSensitiveKey(string $key): bool
    {
        $keyLower = strtolower($key);

        foreach (self::SENSITIVE_PATTERNS as $pattern) {
            if (str_contains($keyLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * คืน logger สำหรับ security channel
     * Fallback: default channel ถ้า 'security' ไม่ได้ตั้งค่าใน config/logging.php
     */
    private function securityChannel(): LoggerInterface
    {
        return $this->resolveChannel(
            config('logging.security_channel', 'security'),
        );
    }

    /**
     * คืน logger สำหรับ audit channel
     * Fallback: default channel ถ้า 'audit' ไม่ได้ตั้งค่า
     */
    private function auditChannel(): LoggerInterface
    {
        return $this->resolveChannel(
            config('logging.audit_channel', 'audit'),
        );
    }

    /**
     * คืน logger สำหรับ performance channel
     * Fallback: default channel ถ้า 'performance' ไม่ได้ตั้งค่า
     */
    private function performanceChannel(): LoggerInterface
    {
        return $this->resolveChannel(
            config('logging.performance_channel', 'performance'),
        );
    }

    /**
     * Resolve log channel พร้อม fallback ไป default channel
     * ป้องกัน crash ถ้า channel ยังไม่ได้ตั้งค่าใน config
     */
    private function resolveChannel(string $channel): LoggerInterface
    {
        try {
            return Log::channel($channel);
        } catch (Throwable) {
            return Log::channel(config('logging.default', 'stack'));
        }
    }
}
