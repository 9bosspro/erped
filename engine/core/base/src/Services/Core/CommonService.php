<?php

declare(strict_types=1);

namespace Core\Base\Services\Core;

use Ramsey\Uuid\Uuid;
use Throwable;
use RuntimeException;

class CommonService
{
    //
    public function __construct()
    {
        //
    }
    /**
     * ขาไป: แปลงข้อมูลให้ปลอดภัยสำหรับส่งผ่าน URL
     *
     * @param  string  $data     ข้อมูลที่ต้องการแปลง (เช่น ข้อมูลที่ผ่านการ Encrypt มาแล้ว)
     * @param  bool    $padding  true = ตัด padding (=) ออก
     * @return string  ข้อมูลรูปแบบ Base64URL ที่ไม่มี +, / และ =
     */
    public function base64UrlEncode(string $data, bool $padding = true): string
    {
        // 1. แปลงเป็น Base64 มาตรฐาน
        $base64 = base64_encode($data);

        // 2. แปลงตัวอักษรที่เป็นปัญหาใน URL (+ และ /) เป็น - และ _
        // 3. ตัด Padding (=) ด้านท้ายทิ้งทั้งหมด
        return rtrim(strtr($base64, '+/', '-_'), $padding ? '=' : '');
    }

    /**
     * ขากลับ: แปลงข้อมูลจาก URL กลับเป็นต้นฉบับ
     *
     * @param  string  $base64Url  ข้อมูล Base64URL ที่รับมาจาก URL
     * @return string|false คืนค่าเป็น String ต้นฉบับ หรือ false หากรูปแบบข้อมูลผิด
     */
    public function base64UrlDecode(string $base64Url): string|false
    {
        // 1. แปลง - และ _ กลับไปเป็น + และ / ให้ตรงตามมาตรฐาน Base64
        $base64 = strtr($base64Url, '-_', '+/');

        // 2. เติม Padding (=) กลับเข้าไป (สำคัญมาก เพื่อป้องกัน base64_decode ทำงานผิดพลาด)
        // Base64 ที่ถูกต้อง ความยาวอักขระต้องหาร 4 ลงตัวเสมอ
        $padding = strlen($base64) % 4;
        if ($padding !== 0) {
            $base64 .= str_repeat('=', 4 - $padding);
        }

        // 3. ถอดรหัสกลับ (ใช้ strict parameter เป็น true เพื่อให้เช็คอักขระขยะที่อาจปนมา)
        return base64_decode($base64, true);
    }
    public function data_to_url(string $data, bool $removePadding = true): string
    {
        $result = strtr($data, '+/', '-_');

        return $removePadding ? rtrim($result, '=') : $result;
    }
    public function url_to_data(string $data, bool $addPadding = false): string
    {
        $data = strtr($data, '-_', '+/');
        if ($addPadding) {
            $remainder = strlen($data) % 4;
            if ($remainder > 0) {
                $data .= str_repeat('=', 4 - $remainder);
            }
        }

        return $data;
    }
    public function is_valid_base64(mixed $data, bool $strict = true): bool
    {
        if (! is_string($data) || $data === '') {
            return false;
        }

        if ($strict) {
            // ตรวจสอบ pattern และ padding
            if (! preg_match('/^[A-Za-z0-9+\/]*={0,2}$/', $data)) {
                return false;
            }
            if (strlen($data) % 4 !== 0) {
                return false;
            }
        }

        $decoded = base64_decode($data, true);

        return $decoded !== false && base64_encode($decoded) === $data;
    }
    public function generateId(int $version = 4, bool $includeDash = false): string
    {
        try {
            $uuid = match ($version) {
                1 => Uuid::uuid1(),
                2 => Uuid::uuid2(Uuid::DCE_DOMAIN_PERSON),
                3 => Uuid::uuid3(Uuid::NAMESPACE_DNS, php_uname('n')),
                5 => Uuid::uuid5(Uuid::NAMESPACE_DNS, php_uname('n')),
                6 => Uuid::uuid6(),
                7 => Uuid::uuid7(),
                default => Uuid::uuid4(),
            };
        } catch (Throwable $e) {
            throw new RuntimeException("UUID v{$version} generation failed: {$e->getMessage()}", previous: $e);
        }

        $string = $uuid->toString();

        return $includeDash ? $string : str_replace('-', '', $string);
    }
    /**
     * สร้าง UUID version 5 (deterministic — input เดิม = UUID เดิมเสมอ)
     *
     * @param  string  $name  input string (ใช้ hostname ถ้าว่าง)
     * @param  bool  $includeDash  true = มี dash เช่น 550e8400-e29b-...
     */
    public function uuid5(string $name = '', bool $includeDash = false): string
    {
        $uuid = Uuid::uuid5(Uuid::NAMESPACE_DNS, $name ?: php_uname('n'))->toString();

        return $includeDash ? $uuid : str_replace('-', '', $uuid);
    }
}
