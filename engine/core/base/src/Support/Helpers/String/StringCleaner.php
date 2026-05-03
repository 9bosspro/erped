<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\String;

/**
 * StringCleaner — ทำความสะอาดและกรอง string
 *
 * ความรับผิดชอบ:
 * - ลบ &nbsp; และ whitespace ที่ไม่จำเป็น
 * - ลบอักขระพิเศษ
 * - Censor คำหยาบ
 * - ลบ line breaks และ BR tags
 * - แปลง encoding (UTF-8/TIS-620)
 * - ทำความสะอาด string สำหรับ URL
 */
final class StringCleaner
{
    /**
     * ลบ &nbsp; และ trim whitespace
     *
     * @param  string  $str  string ที่ต้องการทำความสะอาด
     * @return string string ที่สะอาดแล้ว
     */
    public function cleanNbsp(string $str): string
    {
        return trim((string) preg_replace('/&nbsp;/', ' ', $str));
    }

    /**
     * เตรียมข้อมูลโดยทำความสะอาดและลบ slashes
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการเตรียม
     * @return string ข้อมูลที่สะอาดแล้ว
     */
    public function dataReady(mixed $data): string
    {
        $dataStr = is_scalar($data) ? (string) $data : '';

        return stripslashes($this->cleanNbsp($dataStr));
    }

    /**
     * ลบอักขระพิเศษ (เก็บเฉพาะ a-z, A-Z, 0-9, _, -, space)
     *
     * @param  string  $value  string ที่ต้องการกรอง
     * @return string string ที่กรองแล้ว
     */
    public function removeSpecialChars(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9_ -]/s', '', $value);
    }

    /**
     * เซ็นเซอร์คำหยาบใน string
     *
     * @param  string  $str  string ที่ต้องการเซ็นเซอร์
     * @param  array<string>  $censored  รายการคำที่ต้องการเซ็นเซอร์
     * @param  string  $replacement  ตัวอักษรแทนที่ (ว่าง = ใช้ #)
     * @return string string ที่เซ็นเซอร์แล้ว
     */
    public function censorWords(string $str, array $censored, string $replacement = ''): string
    {
        if (empty($censored)) {
            return $str;
        }

        $str = ' '.$str.' ';
        $delim = '[-_\'\"`(){}<>\[\]|!?@#%&,.:;^~*+=\/ 0-9\n\r\t]';

        foreach ($censored as $badword) {
            $badword = str_replace('\*', '\w*?', preg_quote($badword, '/'));

            if ($replacement !== '') {
                $str = (string) preg_replace(
                    "/({$delim})(".$badword.")({$delim})/i",
                    "\\1{$replacement}\\3",
                    $str,
                );
            } elseif (preg_match_all(
                "/{$delim}(".$badword."){$delim}/i",
                $str,
                $matches,
                PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE,
            )) {
                $matches = $matches[1];

                for ($i = count($matches) - 1; $i >= 0; $i--) {
                    $length = strlen($matches[$i][0]);
                    $str = (string) substr_replace($str, str_repeat('#', $length), $matches[$i][1], $length);
                }
            }
        }

        return trim($str);
    }

    /**
     * Trim whitespace ซ้ำซ้อน พร้อม option strip_tags
     *
     * @param  mixed  $str  string ที่ต้องการ trim
     * @param  string  $ch  ตัวอักษรแทนที่ whitespace
     * @param  bool  $enableStrip  true = strip HTML tags ด้วย
     * @return string string ที่ trim แล้ว
     */
    public function trimNull(mixed $str = '', string $ch = ' ', bool $enableStrip = false): string
    {
        if (is_numeric($str)) {
            $str = (string) ($str + 0);
        }

        if (! is_string($str) || $str === '') {
            return '';
        }

        $str = (string) preg_replace('/[[:space:]]+/', $ch, trim($str));

        if ($enableStrip) {
            $str = strip_tags($str);
        }

        return $str;
    }

    /**
     * เก็บเฉพาะตัวอักษร ตัวเลข และภาษาไทย
     *
     * @param  mixed  $str  string ที่ต้องการกรอง
     * @return string string ที่กรองแล้ว
     */
    public function trimExtraStrings(mixed $str = ''): string
    {
        if (is_numeric($str)) {
            $str = (string) ($str + 0);
        }

        if (! is_string($str) || $str === '') {
            return '';
        }

        return (string) preg_replace('/[^A-Za-z0-9ก-๙]/', '', trim($str));
    }

    /**
     * ลบ line breaks และ BR tags
     *
     * @param  mixed  $source  string ที่ต้องการลบ
     * @return string string ที่ลบแล้ว
     */
    public function deleteLineBreaks(mixed $source = ''): string
    {
        if (! is_string($source)) {
            return '';
        }

        return str_replace(
            ['<br/>', '<br>', "\r\n", "\n", "\r"],
            '',
            $source,
        );
    }

    /**
     * ทำความสะอาด escape strings พร้อมจัดการ encoding
     *
     * @param  mixed  $data  ข้อมูลที่ต้องการทำความสะอาด
     * @return string string ที่สะอาดแล้ว
     */
    public function cutEscapeStrings(mixed $data = ''): string
    {
        if (is_array($data) || is_object($data)) {
            return '';
        }

        $data = is_scalar($data) ? (string) $data : '';
        mb_internal_encoding('utf-8');
        $currentEncoding = mb_detect_encoding($data, 'auto');

        $data = ($currentEncoding !== false && $currentEncoding !== '')
            ? mb_convert_encoding($data, $currentEncoding, 'UTF-8')
            : mb_convert_encoding($data, 'TIS-620', 'UTF-8');

        return (string) preg_replace("/(^[\r\n]*|[\r\n]+)[\s\t]*[\r\n]+/", '', str_replace('"', '', $data));
    }

    /**
     * ทำความสะอาด string สำหรับใช้ใน URL (URL-safe)
     *
     * @param  string  $string  string ที่ต้องการทำความสะอาด
     * @return string string ที่ปลอดภัยสำหรับ URL
     */
    public function cleanString(string $string): string
    {
        $string = str_replace(' ', '-', $string);
        $string = (string) preg_replace('/[^A-Za-z0-9\-]/', '', $string);

        return (string) preg_replace('/-+/', '-', $string);
    }

    // ─── Backward Compatibility Aliases ──────────────────────────────

    /** @deprecated ใช้ cleanNbsp() แทน */
    public function clean_nbsp(string $str): string
    {
        return $this->cleanNbsp($str);
    }

    /** @deprecated ใช้ removeSpecialChars() แทน */
    public function removespecialchar(string $value): string
    {
        return $this->removeSpecialChars($value);
    }

    /** @deprecated ใช้ censorWords() แทน */
    /** @param array<string> $censored */
    public function word_censors(string $str, array $censored, string $replacement = ''): string
    {
        return $this->censorWords($str, $censored, $replacement);
    }

    /** @deprecated ใช้ trimNull() แทน */
    public function trim_null(mixed $str = '', string $ch = ' ', bool $enable_strip = false): string
    {
        return $this->trimNull($str, $ch, $enable_strip);
    }

    /** @deprecated ใช้ trimExtraStrings() แทน */
    public function trim_extra_strings(mixed $str = ''): string
    {
        return $this->trimExtraStrings($str);
    }

    /** @deprecated ใช้ deleteLineBreaks() แทน */
    public function del_enter(mixed $source = ''): string
    {
        return $this->deleteLineBreaks($source);
    }

    /** @deprecated ใช้ cutEscapeStrings() แทน */
    public function cut_escape_strings(mixed $data = ''): string
    {
        return $this->cutEscapeStrings($data);
    }

    /** @deprecated ใช้ cleanString() แทน */
    public function clean_string(string $string): string
    {
        return $this->cleanString($string);
    }
}
