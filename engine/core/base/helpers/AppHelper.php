<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| AppHelper — ฟังก์ชัน helper สำหรับ paths, config, และ app-level utilities
|--------------------------------------------------------------------------
*/

// =========================================================================
// Path Helpers
// =========================================================================

if (! function_exists('get_core_version')) {
    /**
     * คืน version ปัจจุบันของ core package
     *
     * @return string version string เช่น '1.0.0'
     */
    function get_core_version(): string
    {
        return '1.0.0';
    }
}

if (! function_exists('core_path')) {
    /**
     * คืน path ของ core directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath (false ถ้า path ไม่มีอยู่จริง)
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function core_path(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('core'), $path, $real);
    }
}

if (! function_exists('plugins_path')) {
    /**
     * คืน path ของ plugins directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function plugins_path(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('plugins'), $path, $real);
    }
}

if (! function_exists('modules_paths')) {
    /**
     * คืน path ของ modules directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function modules_paths(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('modules'), $path, $real);
    }
}

if (! function_exists('theme_paths')) {
    /**
     * คืน path ของ themes directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function theme_paths(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('themes'), $path, $real);
    }
}

if (! function_exists('theme_frontend')) {
    /**
     * คืน path ของ frontend themes directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function theme_frontend(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('themes'.DIRECTORY_SEPARATOR.'frontend'), $path, $real);
    }
}

if (! function_exists('theme_backend')) {
    /**
     * คืน path ของ backend themes directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function theme_backend(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('themes'.DIRECTORY_SEPARATOR.'backend'), $path, $real);
    }
}

if (! function_exists('theme_core')) {
    /**
     * คืน path ของ core themes directory
     *
     * @param  string|null  $path  optional path ต่อท้าย
     * @param  bool  $real  true = คืน realpath
     * @return bool|string path หรือ false ถ้าไม่พบ
     */
    function theme_core(?string $path = null, bool $real = false): string|bool
    {
        return gen_path(engine_path('themes'.DIRECTORY_SEPARATOR.'core'), $path, $real);
    }
}

if (! function_exists('isPathSafe')) {
    /**
     * ตรวจสอบว่า file path อยู่ภายใน allowed base path หรือไม่
     *
     * ใช้ป้องกัน Path Traversal attack (../../etc/passwd)
     * ใช้ realpath() เพื่อ resolve symlinks และ ../ ก่อนเปรียบเทียบ
     *
     * @param  string  $filePath  path ที่ต้องการตรวจสอบ
     * @param  string  $allowedBase  base path ที่อนุญาต
     * @return bool true ถ้า path ปลอดภัย
     */
    function isPathSafe(string $filePath, string $allowedBase): bool
    {
        $realFile = realpath($filePath);
        $realBase = realpath($allowedBase);

        return $realFile !== false
            && $realBase !== false
            && str_starts_with($realFile, $realBase);
    }
}

if (! function_exists('istheme_paths')) {
    /**
     * ตรวจสอบว่า theme directory มีอยู่จริงหรือไม่
     *
     * @param  string|null  $names  ชื่อ theme
     * @param  string|null  $type  ประเภท theme (เช่น 'frontend', 'backend')
     * @return bool true ถ้า directory มีอยู่
     */
    function istheme_paths(?string $names = null, ?string $type = null): bool
    {
        if ($names === null || $type === null) {
            return false;
        }

        return is_dir(theme_paths($type.'/'.$names));
    }
}

// =========================================================================
// Navigation Helpers
// =========================================================================

if (! function_exists('isActive')) {
    /**
     * ตรวจสอบว่า route/segment ปัจจุบันตรงกับ $data หรือไม่
     *
     * ใช้สำหรับ highlight active menu item ใน navigation
     *
     * @param  string  $data  ชื่อ segment ที่ต้องการตรวจสอบ
     * @param  int  $segment  segment ที่ต้องการตรวจ (default: 2 = หลัง 'pages')
     * @return string 'active' ถ้าตรงกัน, '' ถ้าไม่ตรง
     */
    function isActive(string $data, int $segment = 2): string
    {
        return request()->segment($segment) === $data ? 'active' : '';
    }
}

// =========================================================================
// OAuth Key Helpers
// =========================================================================

if (! function_exists('GetPublicKey')) {
    /**
     * อ่าน OAuth public key จาก storage
     *
     * path ของ key อ่านจาก config('passport.key_path')
     *
     * @return false|string เนื้อหา public key หรือ false ถ้าอ่านไม่ได้
     */
    function GetPublicKey(): string|false
    {
        return file_get_contents(
            storage_path(config('passport.key_path', '').'oauth-public.key'),
        );
    }
}

if (! function_exists('GetPrivateKey')) {
    /**
     * อ่าน OAuth private key จาก storage
     *
     * path ของ key อ่านจาก config('passport.key_path')
     *
     * @return false|string เนื้อหา private key หรือ false ถ้าอ่านไม่ได้
     */
    function GetPrivateKey(): string|false
    {
        return file_get_contents(
            storage_path(config('passport.key_path', '').'oauth-private.key'),
        );
    }
}

// =========================================================================
// Cache / Settings Helpers
// =========================================================================

if (! function_exists('cache_remember')) {
    /**
     * ดึงค่าจาก cache หรือรัน callback แล้ว cache ผลลัพธ์ไว้
     *
     * TTL อ่านจาก config('cache.lifetime') — ถ้าไม่มีใช้ค่า $ttl เป็น fallback
     *
     * @param  string  $key  cache key
     * @param  callable  $callback  callback สำหรับสร้างค่าถ้า cache miss
     * @param  int  $ttl  TTL (วินาที) — default 1800 (30 นาที)
     * @return mixed ค่าจาก cache
     */
    function cache_remember(string $key, callable $callback, int $ttl = 1800): mixed
    {
        return cache()->remember($key, config('cache.lifetime', $ttl), $callback);
    }
}

if (! function_exists('get_options')) {
    /**
     * ดึงค่า setting จาก DB ผ่าน cache
     *
     * ค้นหาจาก setting_key ใน Setting model
     * รองรับการกรองตาม locale ถ้า $locale = true
     *
     * @param  string  $key  setting key ที่ต้องการ
     * @param  bool  $decode  true = decode JSON value เป็น array
     * @param  mixed  $locale  true = filter ตาม locale ปัจจุบัน, false = ไม่ filter
     * @return mixed ค่าของ setting หรือ null ถ้าไม่พบ
     */
    function get_options(string $key, bool $decode = false, mixed $locale = false): mixed
    {
        $cacheKey = $locale === true ? $key.current_local() : $key;

        $option = cache_remember($cacheKey, function () use ($key, $locale): mixed {
            $query = App\Models\Setting::query();

            if ($locale !== false) {
                $query->where('lang', current_local());
            }

            return $query->where('setting_key', $key)->first();
        });

        return $decode
            ? json_decode($option->value ?? '', true)
            : ($option->value ?? null);
    }
}
