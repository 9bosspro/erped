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

if (! function_exists('current_local')) {
    /**
     * ดึง language locale ปัจจุบันของ application
     */
    function current_local(): string
    {
        return app()->getLocale();
    }
}

if (! function_exists('is_provider_loaded')) {
    /**
     * ตรวจสอบว่า Service Provider ถูกโหลด (registered & booted) แล้วหรือยัง
     *
     * @param  class-string  $providerClass  FQCN ของ Service Provider
     */
    function is_provider_loaded(string $providerClass): bool
    {
        return array_key_exists($providerClass, app()->getLoadedProviders());
    }
}

if (! function_exists('is_api_request')) {
    /**
     * ตรวจสอบว่า Request ปัจจุบันเป็น API Request หรือไม่
     *
     * ตรวจสอบจาก: route prefix, named route, และ Accept header
     *
     * @return bool true ถ้าเป็น API request
     */
    function is_api_request(): bool
    {
        $request = request();

        return $request->expectsJson()
            || $request->is('api')
            || $request->is('api/*')
            || $request->routeIs('api.*');
    }
}

if (! function_exists('is_production')) {
    /**
     * ตรวจสอบว่า Environment ปัจจุบันเป็น production หรือไม่
     */
    function is_production(): bool
    {
        return app()->environment('production');
    }
}

if (! function_exists('get_file_extension')) {
    /**
     * ดึงนามสกุลไฟล์เป็นตัวพิมพ์เล็ก
     *
     * @param  string  $filename  ชื่อไฟล์หรือ path
     * @return string นามสกุลไฟล์ (lowercase) หรือ '' ถ้าไม่มี
     */
    function get_file_extension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);

        return $extension !== '' ? Str::lower($extension) : '';
    }
}

if (! function_exists('get_ip_from_third_party')) {
    /**
     * ดึง Public IP Address ของ server จากบริการภายนอก พร้อม cache 10 นาที
     *
     * ลอง fallback หลายบริการตามลำดับ ถ้าบริการแรกล้มเหลว
     * cache เฉพาะเมื่อได้ IP จริงเท่านั้น
     *
     * @return string|null IP Address หรือ null ถ้าทุกบริการล้มเหลว
     */
    function get_ip_from_third_party(): ?string
    {
        $ttlRaw = config('services.ip_lookup.cache_ttl', 600);
        $ttl = \is_int($ttlRaw) ? $ttlRaw : 600;

        $timeoutRaw = config('services.ip_lookup.timeout', 5);
        $timeout = \is_int($timeoutRaw) ? $timeoutRaw : 5;
        $servicesRaw = config('services.ip_lookup.services', [
            'https://ipecho.net/plain',
            'https://api.ipify.org',
            'https://ifconfig.me/ip',
        ]);
        $services = \is_array($servicesRaw) ? $servicesRaw : [];

        return Cache::remember('server_public_ip', $ttl, function () use ($services, $timeout): ?string {
            foreach ($services as $service) {
                if (! \is_string($service)) {
                    continue;
                }

                try {
                    $ip = trim(Http::timeout($timeout)->get($service)->body());

                    if (filter_var($ip, FILTER_VALIDATE_IP)) {
                        return $ip;
                    }
                } catch (Throwable $e) {
                    Log::warning("Failed to get IP from {$service}: {$e->getMessage()}");
                }
            }

            return null;
        });
    }
}

if (! function_exists('dispatch_safe')) {
    /**
     * ส่ง Job เข้า queue ที่ระบุ โดยตรวจสอบว่า queue ชื่อนั้นอยู่ใน whitelist หรือไม่
     *
     * ถ้า queue ที่ระบุไม่อยู่ใน list ที่อนุญาต จะ fallback เป็น 'default'
     *
     * @param  object  $job  Job instance ที่ต้องการส่ง
     * @param  string|null  $queue  ชื่อ queue ('high-priority' | 'default' | 'low-priority')
     */
    function dispatch_safe(object $job, ?string $queue = null): void
    {
        $allowedRaw = config('queue.allowed_queues', ['high-priority', 'default', 'low-priority']);
        $allowedQueues = \is_array($allowedRaw) ? $allowedRaw : ['default'];

        if (! \in_array($queue, $allowedQueues, strict: true)) {
            $queue = 'default';
        }

        dispatch($job)->onQueue($queue);
    }
}
