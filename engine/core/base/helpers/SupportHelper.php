<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SupportHelper — ฟังก์ชัน utility ระดับ OS/System
|
| Path validation, encoding, string support
| หมายเหตุ: format_currency() อยู่ใน CommonHelper.php (canonical)
|--------------------------------------------------------------------------
*/

// =========================================================================
// Path Helpers
// =========================================================================

if (! function_exists('isAbsolutePaths')) {
    /**
     * ตรวจสอบว่า path ที่ส่งเข้ามาเป็น Absolute Path หรือไม่ (รองรับ cross-platform)
     *
     * Windows: รูปแบบ C:\ หรือ C:/
     * Unix/Linux/macOS: ขึ้นต้นด้วย /
     *
     * @param  mixed  $path  path ที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น absolute path
     */
    function isAbsolutePaths(mixed $path): bool
    {
        // แนะนำให้เปลี่ยนไปใช้ is_absolute_path() ตามมาตรฐาน PathHelper แทนในอนาคต
        return is_absolute_path($path);
    }
}
