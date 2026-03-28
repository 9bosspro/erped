<?php

declare(strict_types=1);

use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Path Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับการจัดการ Path
|
*/

if (! function_exists('gen_path')) {
    /**
     * สร้างและคืนค่าพาธแบบเต็มโดยรวมพาธฐานและพาธเสริม
     *
     * @param  bool|string  $full  พาธฐาน หรือ `false` เพื่อคืนค่า `false`
     * @param  string|null  $path  พาธเสริมที่ต้องการต่อท้าย (ไม่บังคับ)
     * @param  bool  $real  ระบุว่าจะคืนค่าเป็น realpath หรือไม่
     * @return bool|string พาธแบบเต็มที่สร้างขึ้นมา หรือ `false` หาก `$full` เป็น `false`
     */
    function gen_path(string|bool $full = '', ?string $path = '', bool $real = false): string|bool
    {
        if ($full === false) {
            return false;
        }

        $full = Str::finish($full, DIRECTORY_SEPARATOR);

        if (! empty($path)) {
            $path = normalize_path($path);
            $path = trim($path, DIRECTORY_SEPARATOR);
            $combined = $full.$path;

            if (is_dir($combined)) {
                $full = Str::finish($combined, DIRECTORY_SEPARATOR);
            } else {
                // ทั้ง file ที่มีอยู่ และ path ที่ยังไม่มี
                $full = $combined;
            }
        }

        return $real ? realpath($full) : $full;
    }
}

if (! function_exists('base_engine_path')) {
    /**
     * ดึง path ของ engine
     *
     * @param  bool  $real  คืนค่า path ที่ถูกต้องตาม OS
     * @return string
     */
    function base_engine_path(bool $real = false): string|bool
    {
        $full = base_path('engine');

        return $real ? realpath($full) : $full;
    }
}

if (! function_exists('engine_path')) {
    /**
     * ดึง path ของ engine
     *
     * @param  string|null  $path  path ของ engine
     * @param  bool  $real  คืนค่า path ที่ถูกต้องตาม OS
     * @return string
     */
    function engine_path(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(base_engine_path(), $path, $real);
    }
}

if (! function_exists('is_absolute_path')) {
    /**
     * ตรวจสอบว่าเป็น Absolute Path หรือไม่ (รองรับทั้ง Windows และ Unix)
     */
    function is_absolute_path(mixed $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        // Windows: C:\ หรือ C:/
        if (preg_match('/^[a-zA-Z]:[\\\\\/]/', $path)) {
            return true;
        }

        // Unix-like: เริ่มด้วย /
        return str_starts_with($path, '/');
    }
}

if (! function_exists('absolute_path')) {
    /**
     * แปลง relative path เป็น absolute path (ใช้ realpath อย่างปลอดภัย)
     *
     * @param  string  $path  เส้นทางที่ต้องการแปลง
     * @param  string|null  $base  เส้นทางฐาน (default: base_path())
     */
    function absolute_path(string $path, ?string $base = null): ?string
    {
        if (is_absolute_path($path)) {
            $real = realpath($path);

            return $real !== false ? $real : null;
        }

        $base ??= base_path();

        if (is_file($base)) {
            $base = dirname($base);
        }

        $combined = rtrim($base, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.ltrim($path, DIRECTORY_SEPARATOR);
        $real = realpath($combined);

        return $real !== false ? $real : null;
    }
}

if (! function_exists('relative_path')) {
    /**
     * แปลง absolute path เป็น relative path เทียบกับ base path
     */
    function relative_path(string $path, ?string $base = null): ?string
    {
        $base ??= base_path();
        if (is_file($base)) {
            $base = dirname($base);
        }

        $from = realpath($base);
        $to = realpath($path);

        if ($from === false || $to === false) {
            return null;
        }

        if (! str_starts_with($to, $from.DIRECTORY_SEPARATOR)) {
            return null; // path ไม่ได้อยู่ภายใต้ base
        }

        return Str::after($to, $from.DIRECTORY_SEPARATOR);
    }
}

if (! function_exists('normalize_path')) {
    /**
     * แปลง path ให้ใช้ DIRECTORY_SEPARATOR ที่ถูกต้องตาม OS
     * และลบ duplicate slashes
     */
    //  $path = base_path('app/Helpers');
    // ถ้า path มีอยู่จริงในระบบ
    // $normalized = realpath($path); // return false ถ้าไม่มี
    function normalize_path(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }

        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

        // ลบ duplicate separators
        $path = preg_replace('#'.preg_quote(DIRECTORY_SEPARATOR, '#').'{2,}#', DIRECTORY_SEPARATOR, $path);

        // ลบ trailing separator (ยกเว้น root path เช่น "/" หรือ "C:\")
        return rtrim($path, DIRECTORY_SEPARATOR) ?: $path;
    }
}
