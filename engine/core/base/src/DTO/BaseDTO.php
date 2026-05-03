<?php

declare(strict_types=1);

namespace Core\Base\DTO;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use JsonException;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;

/**
 * Base Data Transfer Object (DTO)
 *
 * ทำหน้าที่ห่อหุ้มข้อมูลแทนการใช้ associative arrays ($request->all()) ตรงๆ
 * ทำให้มี Type Hinting ชัดเจน (Type-safety), IDE auto-complete แนะนำถูก,
 * และป้องกันการแนบข้อมูลขยะมากับ Network payload
 */
abstract class BaseDTO implements Arrayable, JsonSerializable
{
    /**
     * สร้าง DTO จาก JSON string
     *
     * @throws JsonException เมื่อ JSON ไม่ถูกต้อง
     */
    public static function fromJson(string $json): static
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return static::fromArray($data);
    }

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

    /**
     * แปลง DTO เป็น JSON string (implement Jsonable)
     *
     * @param  int  $options  JSON encode options เช่น JSON_PRETTY_PRINT
     *
     * @throws JsonException เมื่อ encode ล้มเหลว
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options | JSON_THROW_ON_ERROR);
    }

    /**
     * คืน data สำหรับ json_encode() (implement JsonSerializable)
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
