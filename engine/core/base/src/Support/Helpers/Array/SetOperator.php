<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Array;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

/**
 * SetOperator — ดำเนินการเชิงเซต (Set Operations) บน array
 *
 * ความรับผิดชอบ:
 * - ตรวจสอบ subset, intersection, union, difference
 * - Symmetric difference
 * - Cartesian product และ permutations
 * - ประมวลผลข้อมูลจาก database result
 */
final class SetOperator
{
    public function __construct(
        private readonly ArrayTransformer $transformer = new ArrayTransformer,
    ) {}

    /**
     * ตรวจสอบว่าทุก element ใน keys เป็น subset ของ vars
     *
     * @param  array|BaseCollection  $keys  เซตย่อยที่ต้องการตรวจ
     * @param  array|BaseCollection  $vars  เซตหลัก
     */
    public function isSubset(array|BaseCollection $keys, array|BaseCollection $vars): bool
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        if (empty($keys)) {
            return false;
        }

        return empty(array_diff($keys, $vars));
    }

    /**
     * ตรวจสอบว่ามี element ใดใน keys อยู่ใน vars บ้าง
     *
     * @param  array|BaseCollection  $keys  เซตที่ต้องการตรวจ
     * @param  array|BaseCollection  $vars  เซตเป้าหมาย
     */
    public function hasSome(array|BaseCollection $keys, array|BaseCollection $vars): bool
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        if (empty($keys) || empty($vars)) {
            return false;
        }

        return ! empty(array_intersect($keys, $vars));
    }

    /**
     * ตรวจสอบว่าไม่มี element ใดใน keys อยู่ใน vars เลย
     *
     * @param  array|BaseCollection  $keys  เซตที่ต้องการตรวจ
     * @param  array|BaseCollection  $vars  เซตเป้าหมาย
     */
    public function hasNone(array|BaseCollection $keys, array|BaseCollection $vars): bool
    {
        return ! $this->hasSome($keys, $vars);
    }

    /**
     * คืน intersection ของสอง array (ค่าที่อยู่ในทั้งสองเซต)
     *
     * @param  array|BaseCollection  $keys  เซตแรก
     * @param  array|BaseCollection  $vars  เซตที่สอง
     * @return array<mixed> ค่าที่ซ้ำกัน
     */
    public function intersect(array|BaseCollection $keys, array|BaseCollection $vars): array
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        if (empty($keys) || empty($vars)) {
            return [];
        }

        return array_values(array_intersect($keys, $vars));
    }

    /**
     * คืน union ของสอง array (ค่าที่ไม่ซ้ำ)
     *
     * @param  array|BaseCollection  $keys  เซตแรก
     * @param  array|BaseCollection  $vars  เซตที่สอง
     * @return array<mixed> ค่ารวมที่ไม่ซ้ำ
     */
    public function union(array|BaseCollection $keys, array|BaseCollection $vars): array
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        return array_values(array_unique(array_merge($keys, $vars)));
    }

    /**
     * คืน difference ของสอง array (ค่าที่อยู่ใน keys แต่ไม่อยู่ใน vars)
     *
     * @param  array|BaseCollection  $keys  เซตแรก
     * @param  array|BaseCollection  $vars  เซตที่สอง
     * @return array<mixed> ค่าที่ต่างกัน
     */
    public function diff(array|BaseCollection $keys, array|BaseCollection $vars): array
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        if (empty($keys)) {
            return [];
        }

        return array_values(array_diff($keys, $vars));
    }

    /**
     * คืน symmetric difference (ค่าที่อยู่ในเซตใดเซตหนึ่งแต่ไม่ทั้งสอง)
     *
     * @param  array|BaseCollection  $keys  เซตแรก
     * @param  array|BaseCollection  $vars  เซตที่สอง
     * @return array<mixed> ค่าที่ต่างกันสองทิศทาง
     */
    public function symmetricDiff(array|BaseCollection $keys, array|BaseCollection $vars): array
    {
        $keys = $this->transformer->toArray($keys);
        $vars = $this->transformer->toArray($vars);

        return array_values(array_merge(
            array_diff($keys, $vars),
            array_diff($vars, $keys),
        ));
    }

    /**
     * ประมวลผล database result ที่มี header row เป็นแถวแรก
     *
     * @param  array<int, array<int, mixed>>  $data  ข้อมูลดิบ (แถวแรกเป็น header)
     * @param  array<string>  $selectFields  fields ที่ต้องการดึง (ว่าง = ทั้งหมด)
     * @param  array<string>  $fields  system fields (ว่าง = ใช้ headers)
     * @param  string  $key  key สำหรับ keyed result (ว่าง = indexed)
     * @return array<mixed> ข้อมูลที่ประมวลผลแล้ว
     */
    public function processDbResult(
        array $data,
        array $selectFields = [],
        array $fields = [],
        string $key = '',
    ): array {
        if (empty($data) || ! isset($data[0]) || ! is_array($data[0])) {
            return [];
        }

        $headers = array_shift($data);
        if (empty($headers) || ! is_array($headers)) {
            return [];
        }

        if (empty($fields)) {
            $fields = $headers;
        }

        if (empty($selectFields)) {
            $selectFields = $fields;
        } else {
            $selectFields = $this->intersect($selectFields, $fields);
        }

        $content = [];
        foreach ($data as $row) {
            if (! is_array($row)) {
                continue;
            }

            // ปรับความยาวแถวให้ตรงกับ header
            if (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            } elseif (count($row) < count($headers)) {
                $row = array_merge($row, array_fill(0, count($headers) - count($row), null));
            }

            $item = array_combine($headers, $row);
            if ($item === false) {
                continue;
            }

            if ($key !== '' && (! array_key_exists($key, $item) || $item[$key] === null || $item[$key] === '')) {
                continue;
            }

            $filteredItem = ($selectFields !== $headers)
                ? Arr::only($item, $selectFields)
                : $item;

            $content[] = $filteredItem;
        }

        if ($key !== '') {
            $keyed = [];
            foreach ($content as $itm) {
                if (array_key_exists($key, $itm)) {
                    $keyed[$itm[$key]] = $itm;
                }
            }

            return $keyed;
        }

        return $content;
    }

    /**
     * คำนวณ Cartesian product ของ arrays
     *
     * @param  array<mixed>  ...$arrays  arrays ที่ต้องการรวม
     * @return array<array<mixed>> ผลลัพธ์ cartesian product
     */
    public function cartesianProduct(array ...$arrays): array
    {
        $result = [[]];

        foreach ($arrays as $array) {
            $append = [];

            foreach ($result as $product) {
                foreach ($array as $item) {
                    $append[] = array_merge($product, [$item]);
                }
            }

            $result = $append;
        }

        return $result;
    }

    /**
     * คืน permutations ทั้งหมดของ array
     *
     * @param  array<mixed>  $array  array ต้นฉบับ
     * @return array<array<mixed>> array ของทุก permutation
     */
    public function permutations(array $array): array
    {
        if (count($array) <= 1) {
            return [$array];
        }

        $result = [];

        foreach ($array as $key => $item) {
            $rest = $array;
            unset($rest[$key]);

            foreach ($this->permutations(array_values($rest)) as $permutation) {
                $result[] = array_merge([$item], $permutation);
            }
        }

        return $result;
    }
}
