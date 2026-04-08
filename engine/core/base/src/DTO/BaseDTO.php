<?php

declare(strict_types=1);

namespace Core\Base\DTO;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * BaseDTO — คลาสพื้นฐานสำหรับ Data Transfer Objects ในระบบ
 *
 * ช่วยให้การรับส่งข้อมูลระหว่างชั้น (Layer) มีความเข้มงวดเรื่อง Type
 * และรองรับการแปลงเป็น Array หรือ JSON ได้ทันที
 */
abstract class BaseDTO implements Arrayable, JsonSerializable
{
    /**
     * แปลง DTO เป็น Array (เฉพาะ public properties)
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /**
     * รองรับการ JSON Serialize อัตโนมัติ
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Factory Method สำหรับสร้าง DTO จาก Request หรือ Array
     */
    abstract public static function fromArray(array $data): static;
}
