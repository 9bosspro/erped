<?php

// namespace App\Http\Middleware;

namespace Core\Base\Http\Middleware;

// use App\Models\Setting;  // ต้องเพิ่มตางนี้
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\Response;

class SetLanguage
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (config('app.installed')) {
            $lang = null;
            $userChooseLang = session()->get('lang');
            // TODO: App\Models\Setting ยังไม่มีในระบบ หากเปิดใช้งาน Middleware นี้จะทำให้เกิด Error!
            // ต้องสร้าง Model Setting หรือเปลี่ยนไปดึงค่า Default จาก config('app.locale') แทน
            // $defaultLang = config('app.locale');
            $defaultLang = Setting::pull('default_lang');

            $filteredLang = isset($request->filter['lang']);

            if ($filteredLang) {
                $lang = $request->filter['lang'];
            } else {
                if ($userChooseLang) {
                    $lang = $userChooseLang;
                } elseif ($defaultLang) {
                    $lang = $defaultLang;
                }
            }

            // แก้ไข Deprecated String Interpolation ใน PHP 8.2+ (เปลี่ยนจาก "${lang}" เป็น "{$lang}")
            $langFilePath = base_path("lang/{$lang}.json");

            if (! File::exists($langFilePath)) {
                $lang = $defaultLang;
                session()->put('lang', $lang);
            }
            
            app()->setLocale($lang);
        }

        return $next($request);
    }
}
