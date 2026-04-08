<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| String Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับการจัดการ String
|
*/

if (! function_exists('ppp_strlen')) {
    /**
     * คำนวณความยาวของ string โดยใช้ mb_strlen ที่รองรับ multi-byte (UTF-8)
     *
     * @param  string  $str  ข้อความที่ต้องการนับความยาว
     * @return int ความยาวของข้อความ
     */
    function ppp_strlen(string $str): int
    {
        return mb_strlen($str, 'UTF-8');
    }
}

if (! function_exists('trim_null')) {
    /**
     * ตัด whitespace และแทนที่ช่องว่างภายในด้วยตัวเชื่อมที่กำหนด
     *
     * ลำดับการประมวลผล:
     *  1. trim ขอบทั้งสองข้าง
     *  2. แทนที่ whitespace (space, tab, newline) ด้วย $replacement
     *  3. ลบ HTML/PHP tags (ถ้า $stripTags = true)
     *  4. escape HTML (ถ้า $escapeHtml = true)
     *
     * @param  mixed  $str  ข้อความที่ต้องการตัด (non-string จะคืน '')
     * @param  string  $replacement  อักขระแทนช่องว่างภายใน (ค่าเริ่มต้น: space)
     * @param  bool  $stripTags  ลบ HTML และ PHP tags หรือไม่
     * @param  bool  $escapeHtml  escape HTML entities หรือไม่
     * @return string ข้อความที่ตัดแล้ว
     */
    function trim_null(mixed $str = '', string $replacement = ' ', bool $stripTags = false, bool $escapeHtml = false): string
    {
        if (is_numeric($str)) {
            $str = (string) $str;
        }

        if (! is_string($str) || $str === '') {
            return '';
        }

        // preg_replace คืน null เมื่อ PCRE error — fallback เป็น trim ธรรมดา
        $str = preg_replace('/\s+/u', $replacement, trim($str)) ?? trim($str);

        if ($stripTags) {
            $str = strip_tags($str);
        }

        if ($escapeHtml) {
            $str = e($str);
        }

        return $str;
    }
}

if (! function_exists('trim_all')) {
    /**
     * ตัด whitespace ทุกตำแหน่งจาก string และ normalize เป็น single space
     *
     * @param  mixed  $value  ข้อความที่ต้องการตัด (non-string จะคืน '')
     * @param  bool  $stripTags  ลบ HTML และ PHP tags หรือไม่
     * @param  bool  $escapeHtml  escape HTML entities หรือไม่
     * @return string ข้อความที่ตัดแล้ว
     */
    function trim_all(mixed $value, bool $stripTags = false, bool $escapeHtml = false): string
    {
        if (! is_string($value)) {
            return '';
        }

        $value = preg_replace('/\s+/', ' ', trim($value)) ?? trim($value);

        if ($stripTags) {
            $value = strip_tags($value);
        }

        if ($escapeHtml) {
            $value = e($value);
        }

        return $value;
    }
}

if (! function_exists('clean_html')) {
    /**
     * ทำความสะอาด HTML โดยลบ &nbsp; (และ UTF-8 equivalent) และ normalize whitespace
     *
     * @param  string  $value  ข้อความที่ต้องการทำความสะอาด
     * @return string ข้อความที่สะอาดแล้ว
     */
    function clean_html(string $value): string
    {
        // \xc2\xa0 คือ UTF-8 encoding ของ U+00A0 (Non-Breaking Space) ซึ่งก็คือ &nbsp;
        $value = str_replace(['&nbsp;', "\xc2\xa0"], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }
}

if (! function_exists('data_ready')) {
    /**
     * เตรียมข้อมูลก่อนแสดงผลใน HTML อย่างปลอดภัย
     *
     * ลำดับ: clean_html → html_entity_decode → e()
     *
     * หมายเหตุ: ไม่ใช้ stripslashes() เพราะ magic_quotes ถูกลบตั้งแต่ PHP 5.4
     *           การใช้ stripslashes() จะทำลายข้อมูลที่มี backslash ตั้งใจ เช่น
     *           Windows paths, regex patterns, JSON strings
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเตรียม (scalar จะถูกแปลงเป็น string)
     * @return string ข้อมูลที่ปลอดภัยสำหรับแสดงผลใน HTML
     */
    function data_ready(mixed $data): string
    {
        if (! is_string($data)) {
            $data = is_scalar($data) ? (string) $data : '';
        }

        $data = clean_html($data);
        $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return e($data);
    }
}

if (! function_exists('remove_special_chars')) {
    /**
     * ลบอักขระพิเศษออกจาก string
     *
     * เก็บเฉพาะ: a-z, A-Z, 0-9, ก-๙ (ภาษาไทย), space, ., _, -
     *
     * @param  string  $value  ข้อความที่ต้องการกรอง
     * @return string ข้อความที่ลบอักขระพิเศษแล้ว
     */
    function remove_special_chars(string $value): string
    {
        return preg_replace('/[^a-zA-Z0-9ก-๙\s._-]/u', '', $value) ?? $value;
    }
}

if (! function_exists('remove_bom')) {
    /**
     * ลบ BOM (Byte Order Mark, U+FEFF) ออกจาก string
     *
     * @param  string  $str  ข้อความที่ต้องการลบ BOM
     * @return string ข้อความที่ลบ BOM แล้ว
     */
    function remove_bom(string $str): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $str) ?? $str;
    }
}

if (! function_exists('un_escape_html')) {
    /**
     * แปลง HTML entities กลับเป็นอักขระปกติ
     *
     * ใช้ html_entity_decode() ครอบคลุมทุก entity ทั้ง named และ numeric
     * แทนการ str_replace ทีละตัว ซึ่งไม่ครบและช้ากว่า
     *
     * @param  string  $string  ข้อความที่ต้องการแปลง
     * @return string ข้อความที่แปลงแล้ว
     */
    function un_escape_html(string $string): string
    {
        return html_entity_decode($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
