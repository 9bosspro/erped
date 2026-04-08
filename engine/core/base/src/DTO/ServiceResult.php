<?php

declare(strict_types=1);

namespace Core\Base\DTO;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * ServiceResult — สำหรับจัดรูปแบบค่าที่ส่งกลับจาก Service Layer
 *
 * ช่วยแก้ปัญหา "Undefined index" หรือ "Unexpected array shape"
 * และรักษามาตรฐานของผลลัพธ์ (Response) ให้เหมือนกันทั่วทั้งโปรเจ็กต์
 *
 * @template T
 */
class ServiceResult implements Arrayable, JsonSerializable
{
    public readonly int $timestamp;

    /**
     * @param  bool  $success  สถานะการทำรายการ (Success/Failure)
     * @param  string  $message  ข้อความอธิบายผลลัพธ์ (Thai/English)
     * @param  mixed|T  $data  ข้อมูลที่ส่งกลับ (Entity, DTO, หรือ Collection)
     * @param  int  $code  HTTP Status Code ที่แนะนำ (ความหมายเชิงลึก)
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly mixed $data = null,
        public readonly int $code = 200,
    ) {
        $this->timestamp = time();
    }

    /**
     * ผลลัพธ์สำเร็จ
     */
    public static function success(mixed $data = null, string $message = 'Success', int $code = 200): self
    {
        return new self(true, $message, $data, $code);
    }

    /**
     * ผลลัพธ์ล้มเหลว (Error/Fail)
     */
    public static function error(string $message = 'Error', int $code = 400, mixed $data = null): self
    {
        return new self(false, $message, $data, $code);
    }

    /**
     * แปลงผลลัพธ์เป็น Array สำหรับ Controller response
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'data' => $this->data,
            'code' => $this->code,
            'timestamp' => $this->timestamp,
        ];
    }

    /**
     * JSON Serialize
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
