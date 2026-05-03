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

        $path = theme_paths($type.'/'.$names);

        return is_string($path) && is_dir($path);
    }
}
if (! function_exists('normalizeData')) {
    /**
     * Normalize mixed data to string
     *
     * @param  mixed  $data  Data to normalize
     * @return string Normalized string
     */
    function normalizeData(mixed $data): string
    {
        if (is_string($data)) {
            return $data;
        }

        if (is_numeric($data)) {
            return (string) $data;
        }

        $result = canonicalize($data);

        return \is_string($result) ? $result : (string) json_encode($result);
    }
}

if (! function_exists('canonicalize')) {
    /**
     * Normalize mixed data to a canonical JSON string (sorted keys)
     *
     * @param  mixed  $data  Data to normalize
     * @return array<array-key, mixed>|string
     */
    function canonicalize(mixed $data, bool $checkstring = false, bool $encode = true): string|array
    {
        if ($checkstring && is_string($data)) {
            return $data;
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            ksort($data);

            foreach ($data as &$item) {
                if (is_array($item) || is_object($item)) {
                    $item = canonicalize($item, $checkstring, false); // sort only
                }
            }
            unset($item);
        }

        if ($encode) {
            return (string) json_encode(
                $data,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
            );
        }

        return is_array($data) ? $data : (is_scalar($data) ? (string) $data : '');
    }
}

if (! function_exists('deriveKey')) {
    /**
     * Derive a sub-key from the master key using HKDF-SHA3-256
     *
     * @param  string  $purpose  Context/Purpose (e.g., 'encryption', 'hashing')
     * @param  string  $system  System identifier or salt
     * @return string Raw binary key (32 bytes)
     */
    function deriveKey(string $purpose, string $system, int $length = 32): string
    {
        $configValue = config('core.base::security.masterkey');
        $masterKey = is_string($configValue) ? $configValue : '';

        if (empty($masterKey)) {
            throw new RuntimeException('Security Master Key is not configured in core.base::security.');
        }

        return app(Core\Base\Support\Helpers\Crypto\Contracts\HashHelperInterface::class)->hkdf(
            $masterKey,
            $length,
            $purpose,
            $system,
            'sha3-256',
        );
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
        $lifetime = config('cache.lifetime', $ttl);

        return cache()->remember(
            $key,
            is_int($lifetime) ? $lifetime : $ttl,
            Closure::fromCallable($callback),
        );
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

        $value = is_object($option) ? ($option->value ?? null) : null;

        return $decode
            ? json_decode(is_string($value) ? $value : '', true)
            : $value;
    }
}

if (! function_exists('encodeb64')) {
    /**
     * Encode binary data to Base64 (Standard)
     */
    function encodeb64(string $rawBinary): string
    {
        return Core\Base\Support\Helpers\Crypto\HashHelper::encodeb64($rawBinary);
    }
}

if (! function_exists('decodeb64')) {
    /**
     * Decode Base64 string to binary
     */
    function decodeb64(string $base64): string
    {
        try {
            $result = Core\Base\Support\Helpers\Crypto\HashHelper::decodeb64($base64);

            return $result === false ? '' : $result;
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('encodeb64UrlSafe')) {
    /**
     * Encode binary data to Base64URL (Safe for URLs, no padding)
     */
    function encodeb64UrlSafe(string $rawBinary): string
    {
        return Core\Base\Support\Helpers\Crypto\HashHelper::encodeUrlSafe($rawBinary);
    }
}
if (! function_exists('decodeb64UrlSafe')) {
    /**
     * Decode Base64URL string to binary
     */
    function decodeb64UrlSafe(string $base64Url): string
    {
        try {
            $result = Core\Base\Support\Helpers\Crypto\HashHelper::decodeUrlSafe($base64Url);

            return $result === false ? '' : $result;
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (! function_exists('is_base64')) {
    /**
     * Validate if a string is valid Base64
     */
    function is_base64(string $string): bool
    {
        return Core\Base\Support\Helpers\Crypto\HashHelper::isBase64($string);
    }
}

if (! function_exists('is_json')) {
    /**
     * Validate if a string is a valid JSON
     *
     * @param  bool  $allowEmpty  Allow {}, [], null or empty string
     */
    function is_json(?string $value, bool $allowEmpty = false): bool
    {
        return Core\Base\Support\Helpers\Crypto\HashHelper::isJson($value, $allowEmpty);
    }
}
if (! function_exists('encodeKey')) {
    /**
     * Encode binary data to Hex or Base64URL
     */
    function encodeKey(string $binaryData, bool $useBinary = false): string
    {
        return $useBinary
            ? $binaryData
            : Core\Base\Support\Helpers\Crypto\HashHelper::encodeUrlSafe($binaryData);
    }
}

if (! function_exists('decodeKey')) {
    /**
     * Decode Hex or Base64URL to binary data
     */
    function decodeKey(string $encodedKey, bool $useBinary = false): ?string
    {
        try {
            return $useBinary
                ? $encodedKey
                : Core\Base\Support\Helpers\Crypto\HashHelper::decodeKey($encodedKey);
        } catch (Exception $e) {
            return null;
        }
    }
}

if (! function_exists('parseKey')) {
    /**
     * Parse key from Base64 or Hex
     */
    function parseKey(?string $key): ?string
    {
        try {
            if ($key === null) {
                return null;
            }
            $hashHelper = new Core\Base\Support\Helpers\Crypto\HashHelper;

            return $hashHelper->parseKey($key);
        } catch (Throwable $e) {
            return null;
        }
    }
}
if (! function_exists('resolveKey')) {
    /**
     * Resolve key from Base64 or Hex
     */
    function resolveKey(?string $keyBase64, int $length = 32): ?string
    {
        try {
            $hashHelper = new Core\Base\Support\Helpers\Crypto\HashHelper;

            return $hashHelper->resolveKey($keyBase64, $length);
        } catch (Throwable $e) {
            return null;
        }
    }
}
if (! function_exists('genHashByName')) {
    /**
     * Generate hash by name
     */
    function genHashByName(string $name, ?string $masterKey, int $length = SODIUM_CRYPTO_SECRETBOX_KEYBYTES, int $subkeyId = 1): ?string
    {
        if ($masterKey === null) {
            return null;
        }
        $hashHelper = new Core\Base\Support\Helpers\Crypto\HashHelper;

        return $hashHelper->genHashByName($name, $masterKey, $length, $subkeyId);
    }
}
if (! function_exists('base64url_encode')) {
    function base64url_encode(string $data): string
    {
        return rtrim(
            strtr(base64_encode($data), '+/', '-_'),
            '=',
        );
    }
}
if (! function_exists('base64url_decode')) {
    /**
     * Decode Base64URL
     */
    function base64url_decode(string $data): string
    {
        $padding = strlen($data) % 4;

        if ($padding) {
            $data .= str_repeat('=', 4 - $padding);
        }

        return base64_decode(
            strtr($data, '-_', '+/'),
        );
    }
}
if (! function_exists('coreEncrypt')) {
    /**
     * Encrypt a value using SodiumHelper
     */
    function coreEncrypt(string $value, ?string $key = null): ?string
    {
        /** @var Core\Base\Support\Helpers\Crypto\SodiumHelper $sodium */
        $sodium = app('core.crypto.sodium');

        return $sodium->coreEncrypt($value, $key);
    }
}
if (! function_exists('coreDecrypt')) {
    /**
     * Decrypt a value using SodiumHelper
     */
    function coreDecrypt(string $payload, ?string $key = null): ?string
    {
        /** @var Core\Base\Support\Helpers\Crypto\SodiumHelper $sodium */
        $sodium = app('core.crypto.sodium');

        return $sodium->coreDecrypt($payload, $key);
    }
}
