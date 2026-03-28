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
if (! function_exists('presponsesuccess')) {
    /**
     * คืนค่าโครงสร้างข้อมูล ฺArray แบบ Response Success
     *
     * @param  string|null  $message  ข้อความแจ้งเตือน (เช่น "สำเร็จ")
     * @param  mixed  $data  ข้อมูลที่ต้องการแนบกลับ
     * @return array{success: true, message: string|null, data: mixed, timestamp: int}
     */
    function presponsesuccess(?string $message, mixed $data = null): array
    {
        return [
            'success'   => true,
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),
        ];
    }
}

if (! function_exists('presponseerror')) {
    /**
     * คืนค่าโครงสร้างข้อมูล ฺArray แบบ Response Error
     *
     * @param  string|null  $message  ข้อความแจ้งสาเหตุความผิดพลาด
     * @param  mixed  $data  ข้อมูลชี้แจงเพิ่มเติม
     * @return array{success: false, message: string|null, data: mixed, timestamp: int}
     */
    function presponseerror(?string $message, mixed $data = null): array
    {
        return [
            'success'   => false,
            'status'    => 'error',
            'message'   => $message,
            'data'      => $data,
            'timestamp' => time(),

        ];
        /*
         "meta": {
    "timestamp": 1774398351,
    "request_id": "req_67f3ab12",
    "path": "/api/v1/login"
  }
        */
    }
}

if (! function_exists('is_jsons')) {
    /**
     * ตรวจสอบว่า string เป็น JSON ที่ถูกต้องตามมาตรฐานหรือไม่
     * คล้าย json_validate() ตรวจสอบว่า string ที่ส่งมาเป็น JSON ที่ถูกต้องหรือไม่
     * ป้องกัน error trim(null) ใน PHP 8.1+
     *
     * @param  string|null  $value  ข้อความที่ต้องการตรวจสอบ
     * @param  bool  $allowEmpty  อนุญาตให้เป็น {}, [], null หรือ string ว่างได้
     */
    function is_jsons(?string $value, bool $allowEmpty = false): bool
    {
        if ($value === null || $value === '') {
            return $allowEmpty;
        }

        $value = trim($value);

        // อนุญาต JSON ว่างพื้นฐานทันทีเพื่อความเร็ว
        if ($allowEmpty && in_array($value, ['{}', '[]', 'null'], true)) {
            return true;
        }

        // PHP 8.3+ มีฟังก์ชันในตัวที่เร็วและแม่นยำกว่า
        if (function_exists('json_validate')) {
            return json_validate($value);
        }

        // Fallback สำหรับ PHP < 8.3
        try {
            json_decode($value, false, 512, JSON_THROW_ON_ERROR);
            return true;
        } catch (JsonException) {
            return false;
        }
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
     * @param  mixed  $data    ข้อมูลที่จะเข้ารหัสเป็น JSON
     * @param  int    $status  รหัสสถานะ HTTP
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
    function json_response(mixed $data, int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json(
            $data,
            $status,
            ['Cache-Control' => 'no-cache, no-store, must-revalidate'],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION,
        );
    }
}
