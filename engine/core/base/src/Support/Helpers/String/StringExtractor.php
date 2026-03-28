<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\String;

/**
 * StringExtractor — ดึงส่วนย่อยของ string ด้วย delimiters
 *
 * ความรับผิดชอบ:
 * - ดึง string ระหว่าง delimiters (getBetween, getAllBetween)
 * - ดึงเนื้อหาระหว่าง tags
 * - ค้นหา string ด้วย offset tracking
 * - UTF-8 safe substring
 */
final class StringExtractor
{
    /**
     * ดึง string ระหว่าง delimiter เริ่มต้นและสิ้นสุด
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  delimiter เริ่มต้น
     * @param  string  $end  delimiter สิ้นสุด
     * @return string string ที่ดึงมา
     */
    public function getBetween(string $string, string $start, string $end): string
    {
        $string = ' '.$string;
        $ini = strpos($string, $start);

        if ($ini === 0 || $ini === false) {
            return '';
        }

        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;

        return substr($string, $ini, $len);
    }

    /**
     * ดึง strings ทั้งหมดระหว่าง delimiters เป็น array
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  delimiter เริ่มต้น
     * @param  string  $end  delimiter สิ้นสุด
     * @return array<int, string> array ของ strings ที่ดึงมา
     */
    public function getAllBetween(string $string, string $start, string $end): array
    {
        $arr = explode($start, $string);
        $result = [];

        for ($i = 1; $i < count($arr); $i++) {
            $parts = explode($end, $arr[$i]);
            $result[] = $parts[0] ?? '';
        }

        return $result;
    }

    /**
     * ดึง string ระหว่างคำ พร้อมตรวจสอบตำแหน่ง
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  คำเริ่มต้น
     * @param  string  $end  คำสิ้นสุด
     * @return string string ที่ดึงมา
     */
    public function getBetweenWords(string $string, string $start = '', string $end = ''): string
    {
        $startPos = strpos($string, $start);
        if ($startPos === false) {
            return '';
        }

        $endPos = strpos($string, $end);
        if ($endPos === false) {
            return '';
        }

        $startCharCount = $startPos + strlen($start);
        $firstSubStr = substr($string, $startCharCount);
        $endCharCount = strpos($firstSubStr, $end);

        if ($endCharCount === false || $endCharCount === 0) {
            $endCharCount = strlen($firstSubStr);
        }

        return substr($firstSubStr, 0, $endCharCount);
    }

    /**
     * ดึงข้อมูลระหว่างตำแหน่ง พร้อม trim
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  delimiter เริ่มต้น
     * @param  string  $end  delimiter สิ้นสุด
     * @return string string ที่ดึงมาและ trim แล้ว
     */
    public function getBetweenData(string $string, string $start, string $end): string
    {
        $posString = stripos($string, $start);
        if ($posString === false) {
            return '';
        }

        $substrData = substr($string, $posString);
        $stringTwo = substr($substrData, strlen($start));
        $secondPos = stripos($stringTwo, $end);

        if ($secondPos === false) {
            return trim($stringTwo);
        }

        return trim(substr($stringTwo, 0, $secondPos));
    }

    /**
     * ดึงเนื้อหาระหว่าง tags
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $tagOpen  tag เปิด
     * @param  string  $tagClose  tag ปิด
     * @return array<string>|string array ของเนื้อหา หรือ string ว่าง
     */
    public function tagContents(string $string, string $tagOpen, string $tagClose): array|string
    {
        if (! str_contains($string, $tagOpen) || ! str_contains($string, $tagClose)) {
            return '';
        }

        $result = [];
        foreach (explode($tagOpen, $string) as $value) {
            $closePos = strpos($value, $tagClose);
            if ($closePos !== false) {
                $result[] = $this->substring($value, 0, $closePos);
            }
        }

        return $result;
    }

    /**
     * ค้นหา strings ทั้งหมดระหว่าง delimiters พร้อม offset tracking
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  delimiter เริ่มต้น
     * @param  string  $end  delimiter สิ้นสุด
     * @param  bool  $includeDelimiters  true = รวม delimiters ในผลลัพธ์
     * @param  int  $offset  ตำแหน่งเริ่มค้นหา (ถูกอัปเดตโดย reference)
     * @return array<string>|null array ของ strings ที่พบ
     */
    public function strBetweenAll(
        string $string,
        string $start,
        string $end,
        bool $includeDelimiters = false,
        int &$offset = 0,
    ): ?array {
        $strings = [];
        $length = mb_strlen($string);

        while ($offset < $length) {
            $found = $this->strBetween($string, $start, $end, $includeDelimiters, $offset);
            if ($found === null) {
                break;
            }

            $strings[] = $found;
            $offset += mb_strlen($includeDelimiters ? $found : $start.$found.$end);
        }

        return $strings;
    }

    /**
     * ค้นหา string ระหว่าง delimiters พร้อม offset
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  string  $start  delimiter เริ่มต้น
     * @param  string  $end  delimiter สิ้นสุด
     * @param  bool  $includeDelimiters  true = รวม delimiters ในผลลัพธ์
     * @param  int  $offset  ตำแหน่งเริ่มค้นหา (ถูกอัปเดตโดย reference)
     * @return string|null string ที่พบ หรือ null
     */
    public function strBetween(
        string $string,
        string $start,
        string $end,
        bool $includeDelimiters = false,
        int &$offset = 0,
    ): ?string {
        if ($string === '' || $start === '' || $end === '') {
            return null;
        }

        $startLength = mb_strlen($start);
        $endLength = mb_strlen($end);

        $startPos = strpos($string, $start, $offset);
        if ($startPos === false) {
            return null;
        }

        $endPos = strpos($string, $end, $startPos + $startLength);
        if ($endPos === false) {
            return null;
        }

        $length = $endPos - $startPos + ($includeDelimiters ? $endLength : -$startLength);
        if (! $length) {
            return '';
        }

        $offset = $startPos + ($includeDelimiters ? 0 : $startLength);
        $result = $this->substring($string, $offset, $length);

        return $result !== false ? $result : null;
    }

    /**
     * UTF-8 safe substring
     *
     * @param  string  $string  string ต้นฉบับ
     * @param  int  $start  ตำแหน่งเริ่มต้น
     * @param  int|null  $length  ความยาว (null = จนจบ string)
     * @return string substring
     */
    public function substring(string $string, int $start, ?int $length = null): string
    {
        return mb_substr($string, $start, $length, 'UTF-8');
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /** @deprecated ใช้ getBetween() แทน */
    public function get_string_betweens(string $string, string $start, string $end): string
    {
        return $this->getBetween($string, $start, $end);
    }

    /** @deprecated ใช้ getAllBetween() แทน */
    public function get_array_strings_betweens(string $string, string $start, string $end): array
    {
        return $this->getAllBetween($string, $start, $end);
    }

    /** @deprecated ใช้ getBetweenWords() แทน */
    public function getBetweenwords(string $string, string $start = '', string $end = ''): string
    {
        return $this->getBetweenWords($string, $start, $end);
    }

    /** @deprecated ใช้ getBetweenData() แทน */
    public function get_between_data(string $string, string $start, string $end): string
    {
        return $this->getBetweenData($string, $start, $end);
    }

    /** @deprecated ใช้ tagContents() แทน */
    public function tag_contents(string $string, string $tagOpen, string $tagClose): array|string
    {
        return $this->tagContents($string, $tagOpen, $tagClose);
    }

    /** @deprecated ใช้ strBetweenAll() แทน */
    public function str_between_alls(string $string, string $start, string $end, bool $includeDelimiters = false, int &$offset = 0): ?array
    {
        return $this->strBetweenAll($string, $start, $end, $includeDelimiters, $offset);
    }

    /** @deprecated ใช้ strBetween() แทน */
    public function str_betweens(string $string, string $start, string $end, bool $includeDelimiters = false, int &$offset = 0): ?string
    {
        return $this->strBetween($string, $start, $end, $includeDelimiters, $offset);
    }
}
