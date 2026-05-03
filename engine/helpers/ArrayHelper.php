<?php

declare(strict_types=1);
// use Illuminate\Support\Arr;

/*
|--------------------------------------------------------------------------
| Array Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับการจัดการ Array
|
*/

if (! function_exists('is_indexed_array')) {
    /**
     * ตรวจสอบว่าเป็น Indexed Array (List) หรือไม่
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น indexed array (list)
     */
    function is_indexed_array(mixed $array): bool
    {
        return is_array($array) && array_is_list($array);
    }
}

if (! function_exists('is_associative_array')) {
    /**
     * ตรวจสอบว่าเป็น Associative Array หรือไม่
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น associative array
     */
    function is_associative_array(mixed $array): bool
    {
        return is_array($array) && ! array_is_list($array);
    }
}

if (! function_exists('is_string_key_array')) {
    /**
     * ตรวจสอบว่าเป็น Associative Array ที่มี key เป็น string ทั้งหมด
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น array ที่มี key เป็น string ทั้งหมด
     */
    function is_string_key_array(mixed $array): bool
    {
        if (! is_array($array) || $array === []) {
            return false;
        }

        foreach (array_keys($array) as $key) {
            if (! is_string($key)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('is_mixed_array')) {
    /**
     * ตรวจสอบว่าเป็น Mixed Array (มีทั้ง integer และ string keys)
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้ามีทั้ง integer และ string keys
     */
    function is_mixed_array(mixed $array): bool
    {
        if (! is_array($array) || $array === [] || array_is_list($array)) {
            return false;
        }

        $hasInt = false;
        $hasString = false;

        foreach (array_keys($array) as $key) {
            if (is_int($key)) {
                $hasInt = true;
            } else {
                $hasString = true;
            }

            // ถ้าเจอทั้งคู่แล้ว ไม่ต้องวนต่อ
            if ($hasInt && $hasString) {
                return true;
            }
        }

        return $hasInt && $hasString;
    }
}

if (! function_exists('is_multidimensional_array')) {
    /**
     * ตรวจสอบว่าเป็น Multidimensional Array หรือไม่
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้ามี array ย่อยอย่างน้อยหนึ่งตัว
     */
    function is_multidimensional_array(mixed $array): bool
    {
        if (! is_array($array) || $array === []) {
            return false;
        }

        foreach ($array as $value) {
            if (is_array($value)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('is_multidimensional_with_string_keys')) {
    /**
     * ตรวจสอบว่าเป็น Multidimensional Array และทุก sub-array มี key เป็น string เท่านั้น
     *
     * @param  mixed  $array  ค่าที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น multidimensional และ sub-arrays ทุกตัวมี string keys
     */
    function is_multidimensional_with_string_keys(mixed $array): bool
    {
        if (! is_array($array) || $array === []) {
            return false;
        }

        $hasSubArray = false;

        foreach ($array as $value) {
            if (is_array($value)) {
                $hasSubArray = true;

                // ตรวจสอบว่า sub-array ไม่ใช่ list และมี key เป็น string ทั้งหมด
                if (array_is_list($value) || ! is_string_key_array($value)) {
                    return false;
                }
            }
        }

        return $hasSubArray;
    }
}

if (! function_exists('array_some')) {
    /**
     * ตรวจสอบว่ามีค่าใดค่าหนึ่งใน $needles อยู่ใน $haystack หรือไม่
     *
     * @param  array<mixed>  $needles  อาเรย์ของค่าที่ต้องการตรวจสอบ
     * @param  array<mixed>  $haystack  อาเรย์ที่ต้องการเปรียบเทียบ
     * @param  bool  $strict  ใช้การเปรียบเทียบแบบ strict หรือไม่ (ค่าเริ่มต้น: false)
     * @return bool true ถ้ามีค่าใดค่าหนึ่งตรงกัน
     */
    function array_some(array $needles, array $haystack, bool $strict = false): bool
    {
        foreach ($needles as $needle) {
            if (in_array($needle, $haystack, $strict)) {
                return true;
            }
        }

        return false;
    }
}

if (! function_exists('array_every')) {
    /**
     * ตรวจสอบว่าทุกค่าใน $needles อยู่ใน $haystack หรือไม่ (subset)
     *
     * @param  array<mixed>  $needles  อาเรย์ของค่าที่ต้องการตรวจสอบ
     * @param  array<mixed>  $haystack  อาเรย์ที่ต้องการเปรียบเทียบ
     * @param  bool  $strict  ใช้การเปรียบเทียบแบบ strict หรือไม่ (ค่าเริ่มต้น: false)
     * @return bool true ถ้าทุกค่าตรงกัน
     */
    function array_every(array $needles, array $haystack, bool $strict = false): bool
    {
        foreach ($needles as $needle) {
            if (! in_array($needle, $haystack, $strict)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('array_missing')) {
    /**
     * คืนค่าที่ขาดหายไปจาก $haystack เมื่อเปรียบเทียบกับ $required
     *
     * @param  array<mixed>  $required  อาเรย์ของค่าที่จำเป็นต้องมี
     * @param  array<mixed>  $haystack  อาเรย์ที่ต้องการเปรียบเทียบ
     * @param  bool  $strict  ใช้การเปรียบเทียบแบบ strict หรือไม่ (ค่าเริ่มต้น: false)
     * @return array<mixed> อาเรย์ของค่าที่ขาดหายไป
     */
    function array_missing(array $required, array $haystack, bool $strict = false): array
    {
        $missing = [];
        foreach ($required as $value) {
            if (! in_array($value, $haystack, $strict)) {
                $missing[] = $value;
            }
        }

        return $missing;
    }
}

// ─── Backward Compatibility Aliases ──────────────────────────────

if (! function_exists('is_asso')) {
    /** @deprecated ใช้ is_associative_array() แทน */
    function is_asso(mixed $array): bool
    {
        return is_associative_array($array);
    }
}

if (! function_exists('gen_subset_arrays')) {
    /**
     * กรองอาร์เรย์แรกให้เหลือเฉพาะสมาชิกที่มีอยู่ในอาร์เรย์ที่สอง
     *
     * @param  array<int|string>  $array1
     * @param  array<int|string>  $array2
     * @return array<int|string>
     */
    function gen_subset_arrays(array $array1, array $array2): array
    {
        return array_values(array_intersect($array1, $array2));
    }
}

if (! function_exists('gen_union_arrays')) {
    /**
     * คืนค่า Unique Union ของอาเรย์ที่ส่งเข้ามา
     *
     * @param  array<int|string>  $array1
     * @param  array<int|string>  $array2
     * @return array<int|string>
     */
    function gen_union_arrays(array $array1, array $array2): array
    {
        return array_values(array_unique([...$array1, ...$array2]));
    }
}

if (! function_exists('gen_diff_arrays')) {
    /**
     * คืนค่าที่ต่างกัน (เฉพาะใน array1)
     *
     * @param  array<int|string>  $array1
     * @param  array<int|string>  $array2
     * @return array<int|string>
     */
    function gen_diff_arrays(array $array1, array $array2): array
    {
        return array_values(array_diff($array1, $array2));
    }
}
