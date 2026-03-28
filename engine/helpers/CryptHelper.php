<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Global Helper Functions (Laravel Standard Edition 2025)
|--------------------------------------------------------------------------
|
| ชุดฟังก์ชันช่วยเหลือหลักที่ใช้บ่อยในโปรเจกต์ Laravel
| ฟังก์ชันเฉพาะทางถูกแยกไปยังไฟล์ย่อย:
|
| - ArrayHelper.php    : Array utilities
| - StringHelper.php   : String utilities
| - PathHelper.php     : Path utilities
| - SecurityHelper.php : Security/Encryption utilities
| - JsonHelper.php     : JSON utilities
| - ThaiHelper.php     : Thai-specific utilities
| - DebugHelper.php    : Debug utilities
|
*/

if (! function_exists('is_valid_base64')) {
    /**
     * ตรวจสอบว่าข้อมูลที่ระบุเป็น base64 string ที่ถูกต้องหรือไม่
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการตรวจสอบ
     * @param  bool  $strict  ตรวจสอบ pattern และ padding อย่างเข้มงวด
     * @return bool true ถ้าเป็น base64 ที่ถูกต้อง
     */
    function is_valid_base64(mixed $data, bool $strict = true): bool
    {
        $commonService = app('core.base.common');
        return $commonService->is_valid_base64($data, $strict);
    }
}

if (! function_exists('base64_url_encode')) {
    /**
     * เข้ารหัสข้อมูลเป็น base64 แบบ URL-safe
     *
     * @param  string  $data  ข้อมูลที่ต้องการเข้ารหัส
     * @param  bool  $padding  ลบ padding (=) ออกหรือไม่
     * @return string ข้อมูลที่เข้ารหัสแล้ว
     */
    function base64_url_encode(string $data, bool $padding = true): string
    {
        $commonService = app('core.base.common');
        return $commonService->base64UrlEncode($data, $padding);
    }
}

if (! function_exists('base64_url_decode')) {
    /**
     * ถอดรหัส base64 แบบ URL-safe กลับเป็นข้อมูลปกติ
     *
     * @param  string  $data  ข้อมูลที่ต้องการถอดรหัส
     * @return string ข้อมูลที่ถอดรหัสแล้ว
     */
    function base64_url_decode(string $data): string
    {
        $commonService = app('core.base.common');
        return $commonService->base64UrlDecode($data);
    }
}
