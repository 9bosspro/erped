<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Array;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use InvalidArgumentException;
use JsonSerializable;
use stdClass;
use Traversable;

/**
 * ArrayTransformer — แปลง/จัดรูปแบบ array
 *
 * ความรับผิดชอบ:
 * - แปลงค่าใดๆ เป็น array (รองรับ Collection, JSON, Traversable ฯลฯ)
 * - แปลง array ↔ object (recursive)
 * - จัดรูปแบบ array พร้อม header row (สำหรับ export/table)
 * - Flatten, only, except, groupBy, pluck
 */
final class ArrayTransformer
{
    /**
     * แปลงค่าใดๆ เป็น array (robust)
     *
     * รองรับ: null, Arrayable, Collection, Traversable, JsonSerializable,
     * JSON string, array, scalar, object
     *
     * @param  mixed  $value  ค่าที่ต้องการแปลง
     *
     * @throws InvalidArgumentException สำหรับ resource หรือ callable
     */
    public function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof BaseCollection || $value instanceof EloquentCollection || $value instanceof Arrayable) {
            return (array) $value->toArray();
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        if ($value instanceof JsonSerializable) {
            $json = $value->jsonSerialize();

            return is_array($json) ? $json : (array) $json;
        }

        if (is_string($value)) {
            $trim = trim($value);
            if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $decoded;
                }
            }

            return [$value];
        }

        if (is_null($value)) {
            return [];
        }

        if (is_scalar($value)) {
            return [$value];
        }

        if (is_resource($value) || is_callable($value)) {
            throw new InvalidArgumentException('Cannot convert resource or callable to array');
        }

        return [];
    }

    /**
     * Clone array พร้อมจัดการ index ให้ถูกต้อง
     *
     * @param  mixed  $arr  array ต้นฉบับ
     * @return array array ที่ clone แล้ว
     */
    public function cloneArray(mixed $arr): array
    {
        if (! is_array($arr) || empty($arr)) {
            return [];
        }

        return Arr::isAssoc($arr) ? $arr : array_values($arr);
    }

    /**
     * แปลง array เป็น object แบบ recursive
     *
     * @param  mixed  $array  array ที่ต้องการแปลง
     * @return mixed object หรือค่าเดิม
     */
    public function arrayToObject(mixed $array): mixed
    {
        if (! is_array($array)) {
            return $array;
        }

        $obj = new stdClass;
        foreach ($array as $key => $val) {
            $obj->{$key} = $this->arrayToObject($val);
        }

        return $obj;
    }

    /**
     * แปลง object เป็น array แบบ recursive
     *
     * @param  mixed  $object  object ที่ต้องการแปลง
     * @return mixed array หรือค่าเดิม
     */
    public function objectToArray(mixed $object): mixed
    {
        if (is_object($object)) {
            $object = get_object_vars($object);
        }

        if (is_array($object)) {
            return array_map([$this, 'objectToArray'], $object);
        }

        return $object;
    }

    /**
     * แปลง associative array ให้มี header เป็นแถวแรก
     *
     * @param  array<int, array<string, mixed>>  $data  array ของ associative arrays
     * @return array<int, array<int, mixed>> array ที่มี header row
     */
    public function arrayWithHeader(array $data): array
    {
        if (empty($data) || ! isset($data[0]) || ! is_array($data[0])) {
            return [];
        }

        $header = array_keys($data[0]);
        $result = [$header];

        foreach ($data as $row) {
            $rowValues = [];
            foreach ($header as $key) {
                $rowValues[] = $row[$key] ?? null;
            }
            $result[] = $rowValues;
        }

        return $result;
    }

    /**
     * แปลง array ให้มี header ที่รวม keys ทั้งหมดจากทุกแถว
     *
     * @param  array<int, array<string, mixed>>  $data  array ของ associative arrays
     * @return array<int, array<int, mixed>> array ที่มี comprehensive header row
     */
    public function arrayWithAllHeaders(array $data): array
    {
        if (empty($data)) {
            return [];
        }

        $allKeys = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                $allKeys = array_unique(array_merge($allKeys, array_keys($row)));
            }
        }

        $result = [$allKeys];

        foreach ($data as $row) {
            $result[] = array_map(
                fn ($k) => (is_array($row) && array_key_exists($k, $row)) ? $row[$k] : null,
                $allKeys,
            );
        }

        return $result;
    }

    /**
     * Flatten multidimensional array
     *
     * @param  array<mixed>  $array  array ที่ต้องการ flatten
     * @param  int  $depth  ความลึกที่ต้องการ flatten (PHP_INT_MAX สำหรับทั้งหมด)
     * @return array<mixed> flattened array
     */
    public function flatten(array $array, int $depth = PHP_INT_MAX): array
    {
        return Arr::flatten($array, $depth);
    }

    /**
     * ดึงเฉพาะ keys ที่ระบุจาก array
     *
     * @param  array<string, mixed>  $array  array ต้นฉบับ
     * @param  array<string>  $keys  keys ที่ต้องการเก็บ
     * @return array<string, mixed> array ที่กรองแล้ว
     */
    public function only(array $array, array $keys): array
    {
        return Arr::only($array, $keys);
    }

    /**
     * ดึงทุก keys ยกเว้นที่ระบุจาก array
     *
     * @param  array<string, mixed>  $array  array ต้นฉบับ
     * @param  array<string>  $keys  keys ที่ต้องการตัดออก
     * @return array<string, mixed> array ที่กรองแล้ว
     */
    public function except(array $array, array $keys): array
    {
        return Arr::except($array, $keys);
    }

    /**
     * Map array พร้อม keys
     *
     * @param  array<mixed>  $array  array ต้นฉบับ
     * @param  callable  $callback  callback function(value, key) => [mapKey => mapValue]
     * @return array<mixed> array ที่ map แล้ว
     */
    public function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $result;
    }

    /**
     * จัดกลุ่ม array ตาม key ที่ระบุ
     *
     * @param  array<int, array<string, mixed>>  $array  array ต้นฉบับ
     * @param  string  $key  key สำหรับจัดกลุ่ม
     * @return array<string, array<int, array<string, mixed>>> array ที่จัดกลุ่มแล้ว
     */
    public function groupBy(array $array, string $key): array
    {
        $result = [];

        foreach ($array as $item) {
            if (is_array($item) && isset($item[$key])) {
                $result[$item[$key]][] = $item;
            }
        }

        return $result;
    }

    /**
     * ดึงค่าจาก array ตาม key (pluck)
     *
     * @param  array<int, array<string, mixed>>  $array  array ต้นฉบับ
     * @param  string  $value  key ของค่าที่ต้องการดึง
     * @param  string|null  $key  key สำหรับ result keys
     * @return array<mixed> ค่าที่ดึงมา
     */
    public function pluck(array $array, string $value, ?string $key = null): array
    {
        return Arr::pluck($array, $value, $key);
    }
}
