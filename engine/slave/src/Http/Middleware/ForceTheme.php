<?php

declare(strict_types=1);

namespace Slave\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ForceTheme — กำหนด theme ให้กับ web request ปัจจุบัน
 *
 * - ข้ามการทำงานอัตโนมัติเมื่อเป็น API request หรือ Console command
 * - ดึงชื่อ theme จาก config เพื่อความยืดหยุ่น ไม่ hardcode
 * - รองรับ frontend / backend theme แยกกันตาม route name pattern
 */
class ForceTheme
{
    /**
     * Default theme name เมื่อไม่มีการตั้งค่าใน config
     */
    private const string DEFAULT_THEME = 'techwind';

    /**
     * จัดการ request ที่เข้ามา — เลือก theme ตาม request context
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ข้ามการทำงานเมื่อเป็น API request หรือ Console command
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        // ระบุประเภท theme และโหลดผ่าน helper
        $themeType = $this->resolveThemeType($request);
        $themeName = $this->resolveThemeName($themeType);

        if (function_exists('setThemes')) {
            setThemes($themeName, $themeType);
        }

        return $next($request);
    }

    /**
     * ตรวจสอบว่าควรข้ามการโหลด theme หรือไม่
     *
     * ข้ามเมื่อ: running console, เป็น API route, หรือ client ต้องการ JSON
     */
    private function shouldSkip(Request $request): bool
    {
        if (app()->runningInConsole()) {
            return true;
        }

        // ใช้ helper is_api_request() ถ้ามี (registered โดย CoreServiceProvider)
        // มิฉะนั้น fallback ไปตรวจสอบด้วย Laravel native methods
        if (function_exists('is_api_request')) {
            return is_api_request();
        }

        return $request->is('api/*') || $request->wantsJson();
    }

    /**
     * ระบุประเภท theme ตาม route name pattern
     *
     * route ชื่อ '*.backend.*' → 'backend', อื่นๆ → 'frontend'
     */
    private function resolveThemeType(Request $request): string
    {
        return $request->routeIs('*.backend.*') ? 'backend' : 'frontend';
    }

    /**
     * ดึงชื่อ theme จาก config หรือใช้ค่า default
     *
     * ตั้งค่าใน config/theme.php:
     *   'frontend' => 'techwind'
     *   'backend'  => 'my-admin-theme'
     */
    private function resolveThemeName(string $themeType): string
    {
        $value = config("theme.{$themeType}", self::DEFAULT_THEME);

        return \is_string($value) ? $value : self::DEFAULT_THEME;
    }
}
