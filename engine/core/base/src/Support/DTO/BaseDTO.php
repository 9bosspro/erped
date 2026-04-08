<?php

declare(strict_types=1);

namespace Core\Base\Support\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base Data Transfer Object (DTO)
 *
 * ทำหน้าที่ห่อหุ้มข้อมูลแทนการใช้ associative arrays ($request->all()) ตรงๆ
 * ทำให้มี Type Hinting ชัดเจน (Type-safety), IDE auto-complete แนะนำถูก,
 * และป้องกันการแนบข้อมูลขยะมากับ Network payload
 */
abstract class BaseDTO implements Arrayable
{
    /**
     * สร้าง DTO สุ่มจาก Array ของข้อมูล
     * จะดึงข้อมูลมาเฉพาะ property ที่ class นี้ประกาศไว้เท่านั้น
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $dto = new static;

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();

            if (array_key_exists($name, $data)) {
                $property->setValue($dto, $data[$name]);
            }
        }

        return $dto;
    }

    /**
     * สร้าง DTO โดยตรงจาก Request
     */
    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->all());
    }

    /**
     * แปลง DTO เป็น Array เฉพาะ public properties
     * (ใช้ตอนจะแปลง DTO กลับเพื่อ save ลง Database หรือโยนไปกับ Guzzle)
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $reflection = new ReflectionClass($this);
        $array = [];
        // | ReflectionProperty::IS_PROTECTED  ReflectionProperty::IS_PRIVATE ReflectionProperty::IS_STATIC
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $array[$property->getName()] = $property->getValue($this);
        }

        return $array;
    }
}
