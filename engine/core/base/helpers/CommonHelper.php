<?php

declare(strict_types=1);

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| CommonHelper — ฟังก์ชัน utility ที่ใช้งานทั่วทั้งโปรเจกต์
|
| Array, String, UUID, Thai Baht, Currency, Tree sorting
|--------------------------------------------------------------------------
*/

// =========================================================================
// Array Helpers
// =========================================================================

if (! function_exists('gen_result_not_in_arrays')) {
    /**
     * ค้นหาค่าที่อยู่ใน $keys แต่ไม่มีใน $vars (Array Diff)
     *
     * @param  array  $keys  array ที่ต้องการตรวจสอบ
     * @param  array  $vars  array อ้างอิง
     * @param  bool  $strict  true = เปรียบเทียบแบบ strict (type + value)
     * @return array ค่าที่อยู่ใน $keys แต่ไม่มีใน $vars (reset keys)
     */
    function gen_result_not_in_arrays(array $keys, array $vars, bool $strict = false): array
    {
        if (empty($keys)) {
            return [];
        }

        if ($strict) {
            return array_values(array_filter($keys, fn ($k) => ! in_array($k, $vars, true)));
        }

        return array_values(array_diff($keys, $vars));
    }
}

if (! function_exists('gen_result_in_arrays')) {
    /**
     * ค้นหาค่าที่อยู่ใน $keys และมีใน $vars ด้วย (Array Intersect)
     *
     * @param  array  $keys  array ที่ต้องการตรวจสอบ
     * @param  array  $vars  array อ้างอิง
     * @param  bool  $strict  true = เปรียบเทียบแบบ strict
     * @return array ค่าที่มีอยู่ในทั้งสอง array (reset keys)
     */
    function gen_result_in_arrays(array $keys, array $vars, bool $strict = false): array
    {
        if (empty($keys)) {
            return [];
        }

        if ($strict) {
            return array_values(array_filter($keys, fn ($k) => in_array($k, $vars, true)));
        }

        return array_values(array_intersect($keys, $vars));
    }
}

if (! function_exists('yn_subset_in_arrays')) {
    /**
     * ตรวจสอบว่าทุกค่าใน $keys มีอยู่ใน $vars หรือไม่ (Subset check)
     *
     * @param  array|string  $keys  ค่าหรือรายการค่าที่ต้องการตรวจ
     * @param  array  $vars  array อ้างอิง
     * @param  bool  $strict  true = เปรียบเทียบแบบ strict
     * @return bool true ถ้าทุกค่าใน $keys มีอยู่ใน $vars
     */
    function yn_subset_in_arrays(string|array $keys, array $vars, bool $strict = false): bool
    {
        $keys = (array) $keys;

        if (empty($vars) || empty($keys)) {
            return false;
        }

        foreach ($keys as $value) {
            if (! in_array($value, $vars, $strict)) {
                return false;
            }
        }

        return true;
    }
}

if (! function_exists('yn_some_in_array')) {
    /**
     * ตรวจสอบว่ามีค่าอย่างน้อยหนึ่งค่าใน $keys อยู่ใน $vars หรือไม่ (Any check)
     *
     * @param  array  $keys  รายการค่าที่ต้องการตรวจ
     * @param  array  $vars  array อ้างอิง
     * @param  bool  $strict  true = เปรียบเทียบแบบ strict
     * @return bool true ถ้ามีค่าอย่างน้อยหนึ่งค่าที่ตรงกัน
     */
    function yn_some_in_array(array $keys, array $vars, bool $strict = false): bool
    {
        if (empty($keys)) {
            return false;
        }

        foreach ($keys as $value) {
            if (in_array($value, $vars, $strict)) {
                return true;
            }
        }

        return false;
    }
}

// =========================================================================
// String Helpers
// =========================================================================

if (! function_exists('get_string_between')) {
    /**
     * ดึงข้อความระหว่าง $start และ $end จาก $string
     *
     * @param  string  $string  ข้อความต้นทาง
     * @param  string  $start  ข้อความเริ่มต้น
     * @param  string  $end  ข้อความสิ้นสุด
     * @return string ข้อความที่อยู่ระหว่าง $start และ $end
     */
    function get_string_between(string $string, string $start, string $end): string
    {
        return Str::between($string, $start, $end);
    }
}

if (! function_exists('cut_escape_strings')) {
    /**
     * ทำความสะอาด string โดยลบ newlines และ double quotes
     * พร้อม convert encoding เป็น UTF-8 ถ้าจำเป็น
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการทำความสะอาด
     * @return string ข้อมูลที่ผ่านการทำความสะอาดแล้ว
     */
    function cut_escape_strings(mixed $data = ''): string
    {
        if (is_array($data) || is_object($data)) {
            return '';
        }

        $data = (string) $data;

        if (! mb_check_encoding($data, 'UTF-8')) {
            $data = mb_convert_encoding($data, 'UTF-8', 'TIS-620');
        }

        return (string) preg_replace('/[\r\n]+/', '', str_replace('"', '', $data));
    }
}

// =========================================================================
// Currency Helpers
// =========================================================================

if (! function_exists('format_currency')) {
    /**
     * จัดรูปแบบจำนวนเงินตาม settings ที่ระบุ
     *
     * @param  float|int  $amount  จำนวนเงิน
     * @param  array  $settings  ตัวเลือกการจัดรูปแบบ:
     *                           - currency_symbol (string): สัญลักษณ์ เช่น '฿', '$'
     *                           - currency_position (string): 'left' | 'right' | 'left_space' | 'right_space'
     *                           - no_of_decimal (int): จำนวนทศนิยม (default: 2)
     *                           - decimal_separator (string): 'en-US' = '.', อื่นๆ = ','
     * @return string จำนวนเงินที่จัดรูปแบบแล้ว
     */
    function format_currency(float|int $amount, array $settings): string
    {
        $symbol = $settings['currency_symbol'] ?? '';
        $position = $settings['currency_position'] ?? 'left';
        $decimals = (int) ($settings['no_of_decimal'] ?? 2);
        $locale = $settings['decimal_separator'] ?? 'en-US';

        $decimalSep = $locale === 'en-US' ? '.' : ',';
        $thousandsSep = $locale === 'en-US' ? ',' : '.';

        $formatted = number_format((float) $amount, $decimals, $decimalSep, $thousandsSep);

        return match ($position) {
            'right' => "{$formatted}{$symbol}",
            'left_space' => "{$symbol} {$formatted}",
            'right_space' => "{$formatted} {$symbol}",
            default => "{$symbol}{$formatted}", // left
        };
    }
}

// =========================================================================
// Thai Baht Converter
// =========================================================================

if (! function_exists('baht_text')) {
    /**
     * แปลงตัวเลขจำนวนเงินเป็นคำอ่านภาษาไทย (บาทและสตางค์)
     *
     * @param  float|int  $amount  จำนวนเงิน
     * @return string คำอ่านภาษาไทย เช่น "หนึ่งร้อยบาทถ้วน"
     */
    function baht_text(float|int $amount): string
    {
        $amount = number_format((float) $amount, 2, '.', '');

        [$baht, $satang] = explode('.', $amount);

        $text = '';

        if ((int) $baht > 0) {
            $text .= convert_number_to_thai_words((int) $baht).'บาท';
        }

        if ((int) $satang > 0) {
            $text .= convert_number_to_thai_words((int) $satang).'สตางค์';
        } else {
            $text .= 'ถ้วน';
        }

        return $text;
    }
}

if (! function_exists('convert_number_to_thai_words')) {
    /**
     * แปลงตัวเลข integer เป็นคำอ่านภาษาไทย
     *
     * รองรับตัวเลขสูงสุดถึงหลักล้าน (recursive)
     *
     * @param  int  $number  ตัวเลขที่ต้องการแปลง
     * @return string คำอ่านภาษาไทย
     */
    function convert_number_to_thai_words(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        // จัดการกรณีเกินล้าน (recursive)
        if ($number > 1_000_000) {
            $millions = (int) ($number / 1_000_000);
            $remainder = $number % 1_000_000;

            $result = convert_number_to_thai_words($millions).'ล้าน';
            if ($remainder > 0) {
                $result .= convert_number_to_thai_words($remainder);
            }

            return $result;
        }

        $units = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $positions = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

        $str = (string) $number;
        $len = strlen($str);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $str[$i];
            $pos = $len - $i - 1;

            if ($digit === 0) {
                continue;
            }

            if ($digit === 2 && $pos === 1) {
                $result .= 'ยี่';
            } elseif ($digit === 1 && $pos === 1) {
                $result .= 'เอ็ด';
            } elseif ($digit === 1 && $pos === 0 && $len > 1) {
                $result .= 'เอ็ด';
            } else {
                $result .= $units[$digit];
            }

            $result .= $positions[$pos];
        }

        return $result;
    }
}

// =========================================================================
// UUID Helpers
// =========================================================================

if (! function_exists('uuid7')) {
    /**
     * สร้าง UUID v7 (time-ordered) แบบ thread-safe
     *
     * UUID v7 เรียงตามเวลา — เหมาะสำหรับใช้เป็น primary key ใน DB
     * ประสิทธิภาพดีกว่า UUID v4 เพราะ index เรียงตามเวลาได้
     *
     * หมายเหตุ: Laravel 11+ มี Str::uuid7() ในตัว
     * ควรใช้ Str::uuid7() แทนถ้า Laravel >= 11
     *
     * @return string UUID v7 format: xxxxxxxx-xxxx-7xxx-8xxx-xxxxxxxxxxxx
     */
    function uuid7(): string
    {
        $time = (int) (microtime(true) * 1000);
        $rand = random_bytes(10);
        $timeHex = sprintf('%013x', $time);
        $randHex = bin2hex($rand);

        return sprintf(
            '%08s-%04s-7%03s-8%03s-%012s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            substr($timeHex, 12, 3),
            substr($randHex, 0, 3),
            substr($randHex, 3),
        );
    }
}

if (! function_exists('normalizeUuid')) {
    /**
     * แปลง UUID แบบไม่มีขีด (32 chars) ให้เป็นรูปแบบมาตรฐาน (36 chars)
     *
     * ถ้า UUID มีขีดอยู่แล้ว จะคืนค่าเดิมโดยไม่เปลี่ยนแปลง
     *
     * @param  string  $uuid  UUID ที่ต้องการ normalize
     * @return string UUID ในรูปแบบ xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
     */
    function normalizeUuid(string $uuid): string
    {
        if (strlen($uuid) === 32) {
            return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($uuid, 4));
        }

        return $uuid;
    }
}

if (! function_exists('isUuidFlexible')) {
    /**
     * ตรวจสอบว่า string เป็น UUID ที่ valid หรือไม่ (รองรับทั้งมีและไม่มีขีด)
     *
     * @param  string  $uuid  UUID ที่ต้องการตรวจสอบ
     * @return bool true ถ้าเป็น UUID ที่ valid
     */
    function isUuidFlexible(string $uuid): bool
    {
        return Str::isUuid($uuid) || preg_match('/^[0-9a-f]{32}$/i', $uuid) === 1;
    }
}

// =========================================================================
// Tree / Hierarchy Helpers
// =========================================================================

if (! function_exists('sort_item_with_children')) {
    /**
     * จัดเรียง items แบบ hierarchical tree (parent-children) แบบ recursive
     *
     * คืน array แบบ flat ที่เรียง parent ก่อน children
     * แต่ละ item จะมี property $depth บอกระดับความลึก
     *
     * @param  array|Collection  $list  รายการ items ทั้งหมด (ต้องมี property id, parent_id)
     * @param  array  &$result  array ผลลัพธ์ (pass by reference)
     * @param  int|string|null  $parent  parent ID ที่ต้องการเริ่ม (null = root)
     * @param  int  $depth  ระดับความลึกปัจจุบัน (default: 0)
     * @return array รายการที่เรียง hierarchical แล้ว
     */
    function sort_item_with_children(
        Collection|array $list,
        array &$result = [],
        int|string|null $parent = null,
        int $depth = 0,
    ): array {
        $items = ($list instanceof Collection) ? $list->all() : $list;

        foreach ($items as $key => $object) {
            // ข้ามกรณี self-referencing (id = parent_id) เพื่อป้องกัน infinite loop
            if ($object->parent_id == $object->id) {
                $result[] = $object;
                unset($items[$key]);

                continue;
            }

            if ((string) $object->parent_id === (string) $parent) {
                $object->depth = $depth;
                $result[] = $object;

                unset($items[$key]);
                sort_item_with_children($items, $result, $object->id, $depth + 1);
            }
        }

        return $result;
    }
}
