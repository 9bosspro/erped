<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| JSON Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับการจัดการ JSON
|
*/
if (! function_exists('return_success')) {
    function return_success(string $message, mixed $data, int $code = 200): array
    {
        return Core\Base\DTO\ServiceResult::success($data, $message, $code)->toArray();
    }
}
if (! function_exists('return_error')) {
    function return_error(string $message, mixed $data = null, int $code = 400): array
    {
        return Core\Base\DTO\ServiceResult::error($message, $code, $data)->toArray();
    }
}
if (! function_exists('presponsesuccess')) {
    function presponsesuccess(?string $message, mixed $data = null): array
    {
        return Core\Base\DTO\ServiceResult::success($data, $message ?? 'Success')->toArray();
    }
}

if (! function_exists('presponseerror')) {
    function presponseerror(?string $message, mixed $data = null): array
    {
        return Core\Base\DTO\ServiceResult::error($message ?? 'Error', 400, $data)->toArray();
    }
}

if (! function_exists('is_jsons')) {
    /**
     * ตรวจสอบว่า string ที่รับมาเป็น JSON string ที่ถูกต้องหรือไม่
     */
    function is_jsons(string $string): bool
    {
        if ($string === '') {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }
}

if (! function_exists('json_encode_th')) {
    /**
     * json_encode ที่เหมาะสมกับภาษาไทยและการใช้งานใน Laravel
     * - รักษาตัวอักษรไทยไม่ให้เป็น \u0eXX
     * - ไม่ escape slash (สำคัญสำหรับ URL)
     * - รักษาทศนิยมที่มี .00 (เช่น ราคา 100.00)
     * - Throw exception เมื่อเกิด error (สามารถ try-catch ได้)
     *
     * @param  int  $flags  สามารถเพิ่ม flag เพิ่มเติมได้ (เช่น JSON_PRETTY_PRINT)
     *
     * @throws JsonException เมื่อ encode ไม่ได้
     */
    function json_encode_th(mixed $value = null, int $flags = 0): string
    {
        $defaultFlags = JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR;

        return json_encode($value, $defaultFlags | $flags);
    }
}

if (! function_exists('response_jsons')) {
    /**
     * @deprecated ใช้ json_response() แทน — ฟังก์ชันนี้ bypass Laravel middleware pipeline
     *             และใช้ exit() ซึ่งไม่เหมาะกับ Laravel application
     *
     * @param  mixed  $data  ข้อมูลที่จะเข้ารหัสเป็น JSON
     * @param  int  $status  รหัสสถานะ HTTP
     */
    function response_jsons(mixed $data, int $status = 200): never
    {
        trigger_error(
            'response_jsons() deprecated — ใช้ json_response() แทน',
            E_USER_DEPRECATED,
        );

        try {
            $json = json_encode_th($data);
        } catch (JsonException $e) {
            $json = json_encode(['error' => 'JSON encode failed'], JSON_THROW_ON_ERROR);
            $status = 500;
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        echo $json;
        exit();
    }
}

if (! function_exists('json_response')) {
    /**
     * ส่งการตอบสนอง JSON โดยใช้ Laravel response()
     *
     * @param  mixed  $data  ข้อมูลที่จะเข้ารหัสเป็น JSON
     * @param  int  $status  รหัสสถานะ HTTP
     */
    function json_response(mixed $data, int $status = 200): Illuminate\Http\JsonResponse
    {
        return response()->json(
            $data,
            $status,
            ['Cache-Control' => 'no-cache, no-store, must-revalidate'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
