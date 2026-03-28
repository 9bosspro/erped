<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\App;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

/**
 * AppContext — รวม utilities ที่เกี่ยวกับ Application context
 */
final class AppContext
{
    /**
     * ดึงชื่อ Controller และ Action จาก Route ปัจจุบัน
     *
     * @return array{controller: string, action: string}|null
     */
    public function getControllerAction(): ?array
    {
        $action = Route::currentRouteAction();

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);

            return [
                'controller' => class_basename($controller),
                'action' => $method,
            ];
        }

        return null;
    }

    /**
     * ดึง Application Key ที่ถอด base64 แล้ว
     */
    public function getAppKey(): ?string
    {
        $appKey = config('app.key', '');

        return Str::startsWith($appKey, 'base64:')
            ? base64_decode(substr($appKey, 7)) ?: null
            : ($appKey ?: null);
    }

    /**
     * ตรวจสอบว่า Route ปัจจุบันตรงกับชื่อที่กำหนดหรือไม่
     */
    public function isActiveRoute(string|array $routeNames): bool
    {
        return request()->routeIs($routeNames);
    }

    /**
     * ดึง guard ที่ authenticated อยู่ในปัจจุบัน
     * คืนค่า null หากไม่มีการ login
     *
     * @param  string[]  $guards  รายการ guard ที่ต้องการตรวจ
     */
    public function getCurrentGuard(array $guards = ['web', 'api']): ?string
    {
        foreach ($guards as $guard) {
            try {
                if (Auth::guard($guard)->check()) {
                    return $guard;
                }
            } catch (Throwable $e) {
                Log::warning("Guard [{$guard}] check failed: ".$e->getMessage());
            }
        }

        return null;
    }

    /**
     * ตรวจสอบว่า request ปัจจุบันเป็น API request หรือไม่
     */
    public function isApiRequest(): bool
    {
        return request()->segment(1) === 'api'
            || request()->expectsJson();
    }

    /**
     * สร้าง slug จาก string (รองรับ Unicode / ภาษาไทย)
     */
    public function generateSlug(string $string): string
    {
        // ลบอักขระพิเศษ (คง Unicode letters + numbers + spaces)
        $string = preg_replace('/[^\p{L}\p{N}\s]/u', '', $string);
        // เปลี่ยน whitespace → "-"
        $string = preg_replace('/\s+/u', '-', trim($string));

        return mb_strtolower($string);
    }

    /**
     * ดึง base domain จาก URL
     * เช่น "https://example.com/path" → "https://example.com"
     */
    public function extractBaseDomain(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $parsed = parse_url($url);

        if (! isset($parsed['host'])) {
            return '';
        }

        $scheme = $parsed['scheme'] ?? 'https';

        return $scheme.'://'.$parsed['host'];
    }
}
