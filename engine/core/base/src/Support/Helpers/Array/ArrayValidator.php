<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Array;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

/**
 * ArrayValidator — ตรวจสอบประเภทของ array และ collection
 *
 * ความรับผิดชอบ:
 * - ตรวจสอบ Collection types (Support, Eloquent)
 * - ตรวจสอบโครงสร้าง array (associative, sequential, multidimensional)
 * - ตรวจสอบ JSON validity
 * - ตรวจสอบ keys และ values
 */
final class ArrayValidator
{
    /**
     * ตรวจสอบว่าเป็น Illuminate Collection หรือ Eloquent Collection
     */
    public function isSupportCollection(mixed $value): bool
    {
        return $value instanceof BaseCollection || $value instanceof EloquentCollection;
    }

    /**
     * ตรวจสอบว่าเป็น Illuminate Support Collection
     */
    public function isCollection(mixed $value): bool
    {
        return $value instanceof BaseCollection;
    }

    /**
     * ตรวจสอบว่าเป็น Eloquent Collection
     */
    public function isEloquentCollection(mixed $value): bool
    {
        return $value instanceof EloquentCollection;
    }

    /**
     * ตรวจสอบว่าเป็น associative array (มี string keys)
     */
    public function isAssociativeArray(mixed $value): bool
    {
        if (! is_array($value) || empty($value)) {
            return false;
        }

        return Arr::isAssoc($value);
    }

    /**
     * ตรวจสอบว่าเป็น multidimensional array (ทุก element เป็น array)
     */
    public function isMultidimensionalArray(mixed $value): bool
    {
        if (! is_array($value) || empty($value)) {
            return false;
        }

        foreach ($value as $elm) {
            if (! is_array($elm)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ตรวจสอบว่าเป็น sequential/indexed array (0, 1, 2, ...)
     */
    public function isSequentialArray(mixed $value): bool
    {
        if (! is_array($value) || empty($value)) {
            return false;
        }

        return ! Arr::isAssoc($value);
    }

    /**
     * ตรวจสอบว่า string เป็น JSON ที่ถูกต้อง
     */
    public function isJson(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        json_decode($value);

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * ตรวจสอบว่า array/collection ว่างเปล่า
     */
    public function isEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }

        if ($this->isSupportCollection($value)) {
            return $value->isEmpty();
        }

        return true;
    }

    /**
     * ตรวจสอบว่า array/collection ไม่ว่างเปล่า
     */
    public function isNotEmpty(mixed $value): bool
    {
        return ! $this->isEmpty($value);
    }

    /**
     * ตรวจสอบว่าเป็น array
     */
    public function isArray(mixed $value): bool
    {
        return is_array($value);
    }

    /**
     * ตรวจสอบว่า array มี key ที่ระบุ
     *
     * @param  array<mixed>  $array  array ที่ต้องการตรวจ
     * @param  int|string  $key  key ที่ต้องการหา
     */
    public function hasKey(array $array, string|int $key): bool
    {
        return array_key_exists($key, $array);
    }

    /**
     * ตรวจสอบว่า array มีทุก keys ที่ระบุ
     *
     * @param  array<mixed>  $array  array ที่ต้องการตรวจ
     * @param  array<int|string>  $keys  keys ที่ต้องการหา
     */
    public function hasAllKeys(array $array, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $array)) {
                return false;
            }
        }

        return true;
    }

    /**
     * ตรวจสอบว่า array มี key ใดก็ได้จากที่ระบุ
     *
     * @param  array<mixed>  $array  array ที่ต้องการตรวจ
     * @param  array<int|string>  $keys  keys ที่ต้องการหา
     */
    public function hasAnyKey(array $array, array $keys): bool
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ตรวจสอบว่า array มีค่าที่ระบุ
     *
     * @param  array<mixed>  $array  array ที่ต้องการตรวจ
     * @param  mixed  $value  ค่าที่ต้องการหา
     * @param  bool  $strict  true = เปรียบเทียบ type ด้วย
     */
    public function contains(array $array, mixed $value, bool $strict = false): bool
    {
        return in_array($value, $array, $strict);
    }
}
