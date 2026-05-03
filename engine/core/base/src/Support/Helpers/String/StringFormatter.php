<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\String;

use Illuminate\Support\Str;

/**
 * StringFormatter — จัดรูปแบบ string
 *
 * ความรับผิดชอบ:
 * - JSON encode แบบสวย (pretty print + Unicode)
 * - แปลง case: camelCase, snake_case, kebab-case, PascalCase, Title Case
 * - สร้าง slug สำหรับ URL
 * - Truncate, wrap, pad, mask
 * - จัดรูปแบบตัวเลข
 */
final class StringFormatter
{
    /**
     * JSON encode แบบสวยพร้อม Unicode ไม่ถูก escape
     *
     * @param  array<mixed>|string|null  $data  ข้อมูลที่ต้องการ encode
     * @return string JSON string
     */
    public function jsonEncodePrettify(array|string|null $data): string
    {
        return json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ).PHP_EOL;
    }

    /**
     * แปลง string เป็น Label Case (Title Case พร้อม spaces)
     * เช่น "user_name" → "User Name"
     *
     * @param  string  $text  ข้อความที่ต้องการแปลง
     * @return string ข้อความที่แปลงแล้ว
     */
    public function labelCase(string $text): string
    {
        $newText = Str::title(str_replace(['_', '-'], ' ', $text));

        return (string) preg_replace('!\s+!', ' ', trim($newText));
    }

    /**
     * จัดรูปแบบ string เป็น URL slug
     *
     * @param  string  $string  string ที่ต้องการแปลง
     * @return string slug
     */
    public function slugFormat(string $string): string
    {
        $string = (string) preg_replace('/\s+/u', '-', trim($string));
        $string = str_replace(['/', '\\'], '-', $string);

        return strtolower($string);
    }

    /**
     * แปลงเป็น kebab-case
     */
    public function toKebabCase(string $string): string
    {
        return Str::kebab($string);
    }

    /**
     * แปลงเป็น snake_case
     */
    public function toSnakeCase(string $string): string
    {
        return Str::snake($string);
    }

    /**
     * แปลงเป็น camelCase
     */
    public function toCamelCase(string $string): string
    {
        return Str::camel($string);
    }

    /**
     * แปลงเป็น StudlyCase (PascalCase)
     */
    public function toStudlyCase(string $string): string
    {
        return Str::studly($string);
    }

    /**
     * แปลงเป็น Title Case
     */
    public function toTitleCase(string $string): string
    {
        return Str::title($string);
    }

    /**
     * ตัด string พร้อม ellipsis
     *
     * @param  string  $string  string ที่ต้องการตัด
     * @param  int  $length  ความยาวสูงสุด
     * @param  string  $end  ข้อความต่อท้าย (default: "...")
     * @return string string ที่ตัดแล้ว
     */
    public function truncate(string $string, int $length = 100, string $end = '...'): string
    {
        return Str::limit($string, $length, $end);
    }

    /**
     * ล้อม string ด้วยตัวอักษรที่กำหนด
     *
     * @param  string  $string  string ที่ต้องการล้อม
     * @param  string  $before  ตัวอักษรเปิด
     * @param  string|null  $after  ตัวอักษรปิด (null = ใช้ตัวเดียวกับเปิด)
     * @return string string ที่ล้อมแล้ว
     */
    public function wrap(string $string, string $before, ?string $after = null): string
    {
        return $before.$string.($after ?? $before);
    }

    /**
     * เติม string ให้ครบความยาวที่กำหนด
     *
     * @param  string  $string  string ที่ต้องการเติม
     * @param  int  $length  ความยาวเป้าหมาย
     * @param  string  $pad  ตัวอักษรเติม
     * @param  int  $type  STR_PAD_RIGHT, STR_PAD_LEFT, หรือ STR_PAD_BOTH
     * @return string string ที่เติมแล้ว
     */
    public function pad(string $string, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        return str_pad($string, $length, $pad, $type);
    }

    /**
     * ปิดบัง (mask) ส่วนหนึ่งของ string
     *
     * @param  string  $string  string ที่ต้องการ mask
     * @param  string  $character  ตัวอักษร mask (default: *)
     * @param  int  $index  ตำแหน่งเริ่มต้น
     * @param  int|null  $length  ความยาวที่ต้องการ mask (null = จนจบ)
     * @return string string ที่ mask แล้ว
     */
    public function mask(string $string, string $character = '*', int $index = 0, ?int $length = null): string
    {
        return Str::mask($string, $character, $index, $length);
    }

    /**
     * จัดรูปแบบตัวเลขพร้อม thousands separator
     *
     * @param  float|int|string  $number  ตัวเลข
     * @param  int  $decimals  จำนวนทศนิยม
     * @param  string  $decimalSeparator  ตัวคั่นทศนิยม
     * @param  string  $thousandsSeparator  ตัวคั่นหลักพัน
     * @return string ตัวเลขที่จัดรูปแบบแล้ว
     */
    public function formatNumber(
        float|int|string $number,
        int $decimals = 0,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ',',
    ): string {
        return number_format((float) $number, $decimals, $decimalSeparator, $thousandsSeparator);
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /** @deprecated ใช้ labelCase() แทน */
    public function label_case(string $text): string
    {
        return $this->labelCase($text);
    }

    /** @deprecated ใช้ slugFormat() แทน */
    public function slug_format(string $string): string
    {
        return $this->slugFormat($string);
    }
}
