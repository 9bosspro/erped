<?php

declare(strict_types=1);

namespace Core\Base\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetLanguage Middleware — กำหนด locale ของ request ปัจจุบัน
 *
 * ลำดับการตรวจสอบ locale:
 *  1. URL filter parameter (?filter[lang]=th)
 *  2. Session value (user เลือกเอง)
 *  3. config('app.locale') — default จาก .env APP_LOCALE
 *
 * ใช้ static cache เก็บรายชื่อ locale ที่มีไฟล์แปลภาษา
 * เพื่อหลีกเลี่ยงการ I/O ซ้ำทุก request
 *
 * NOTE: เดิมใช้ Setting::pull('default_lang') แต่ Setting model
 *       ยังไม่ถูก implement ใน codebase นี้ จึง fallback ไป config แทน
 *       เมื่อ Setting model พร้อมแล้วให้เปลี่ยน $defaultLang ด้านล่าง
 */
class SetLanguage
{
    /**
     * รายชื่อ locale ที่มีไฟล์แปลภาษา (cache ข้าม request ใน process เดียวกัน)
     *
     * @var array<string, bool>|null
     */
    private static ?array $availableLocales = null;

    /**
     * จัดการ request ที่เข้ามา
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('core.base::myapp.installed')) {
            $defaultLangValue = config('app.locale', 'th');
            $defaultLang = is_string($defaultLangValue) ? $defaultLangValue : 'th';

            $lang = $request->input('filter.lang')
                ?? session()->get('lang')
                ?? $defaultLang;

            $langStr = is_string($lang) ? $lang : $defaultLang;

            if (! $this->isLocaleAvailable($langStr)) {
                $langStr = $defaultLang;
                session()->put('lang', $langStr);
            }

            app()->setLocale($langStr);
        }

        return $next($request);
    }

    /**
     * ตรวจสอบว่า locale มีไฟล์แปลภาษารองรับหรือไม่
     * ผลลัพธ์ถูก cache ใน static property เพื่อลด disk I/O
     */
    private function isLocaleAvailable(string $locale): bool
    {
        if (self::$availableLocales === null) {
            self::$availableLocales = $this->resolveAvailableLocales();
        }

        return isset(self::$availableLocales[$locale]);
    }

    /**
     * อ่านรายชื่อ locale ที่มีไฟล์ JSON ใน lang/ directory (รันครั้งเดียวต่อ process)
     *
     * @return array<string, bool>
     */
    private function resolveAvailableLocales(): array
    {
        $files = glob(base_path('lang/*.json'));

        if ($files === false || $files === []) {
            return [];
        }

        $locales = [];
        foreach ($files as $file) {
            $locales[basename($file, '.json')] = true;
        }

        return $locales;
    }
}
