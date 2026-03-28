<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\DateTime;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTime;

/**
 * ThaiDateTime — จัดการวันที่/เวลาแบบไทย (พ.ศ.) และ Carbon
 *
 * แก้ไข bugs จาก MyDateTime เดิม:
 * - $this->carbon ไม่ได้ initialize (fixed: ใช้ Carbon::parse() แทน)
 * - getDateFormat() ไม่มีอยู่ (fixed: ใส่ default format)
 * - fiscalYear() ใช้ echo โดยตรง (fixed: return แทน)
 * - ข้อมูล THAI_MONTHS, THAI_DAYS นิยามซ้ำหลายที่ (fixed: constants)
 */
final class ThaiDateTime
{
    /** @var array<int, string> เดือนเต็มภาษาไทย (index 1-12) */
    private const THAI_MONTHS_FULL = [
        1 => 'มกราคม',    2 => 'กุมภาพันธ์',  3 => 'มีนาคม',
        4 => 'เมษายน',    5 => 'พฤษภาคม',     6 => 'มิถุนายน',
        7 => 'กรกฎาคม',   8 => 'สิงหาคม',     9 => 'กันยายน',
        10 => 'ตุลาคม',   11 => 'พฤศจิกายน',   12 => 'ธันวาคม',
    ];

    /** @var array<int, string> เดือนย่อภาษาไทย (index 1-12) */
    private const THAI_MONTHS_SHORT = [
        1 => 'ม.ค.',   2 => 'ก.พ.',  3 => 'มี.ค.',
        4 => 'เม.ย.',  5 => 'พ.ค.',  6 => 'มิ.ย.',
        7 => 'ก.ค.',   8 => 'ส.ค.',  9 => 'ก.ย.',
        10 => 'ต.ค.',  11 => 'พ.ย.',  12 => 'ธ.ค.',
    ];

    /** @var string[] ชื่อวันภาษาไทย (index 0=อาทิตย์ ... 6=เสาร์) */
    private const THAI_DAYS = [
        'อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ',
        'พฤหัสบดี', 'ศุกร์', 'เสาร์',
    ];

    /** @var string format วันที่ default */
    private const DEFAULT_DATE_FORMAT = 'Y-m-d';

    /** @var string format วันที่+เวลา default */
    private const DEFAULT_DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * แปลงวันที่ MySQL เป็นวันที่ไทย (พ.ศ.)
     * เช่น "2024-03-15" → "15 มีนาคม 2567"
     *
     * @param  string  $date  วันที่ในรูปแบบ MySQL หรือ ISO
     * @param  bool  $shortMonth  true = ใช้ชื่อเดือนย่อ (ม.ค., ก.พ., ...)
     */
    public function toThaiDate(string $date, bool $shortMonth = false): string
    {
        $carbon = Carbon::parse($date);
        $year = $carbon->year + 543;
        $month = $carbon->month;
        $day = $carbon->day;

        $monthName = $shortMonth
            ? self::THAI_MONTHS_SHORT[$month]
            : self::THAI_MONTHS_FULL[$month];

        return "{$day} {$monthName} {$year}";
    }

    /**
     * แปลงวันที่พร้อมเวลา เป็นรูปแบบไทยสมบูรณ์
     * เช่น "2024-03-15 10:30:00"
     *   → "วันศุกร์ ที่ 15 มีนาคม พ.ศ. 2567 เวลา 10:30:00 น."
     */
    public function toThaiDateTime(string $datetime): string
    {
        $carbon = Carbon::parse($datetime);
        $year = $carbon->year + 543;
        $month = self::THAI_MONTHS_FULL[$carbon->month];
        $day = $carbon->day;
        $dayName = self::THAI_DAYS[$carbon->dayOfWeek];
        $time = $carbon->format('H:i:s');

        return "วัน{$dayName} ที่ {$day} {$month} พ.ศ. {$year} เวลา {$time} น.";
    }

    /**
     * แปลงวันที่เป็นรูปแบบสั้น: "15 มี.ค. 2567"
     */
    public function toThaiDateShort(string $date): string
    {
        return $this->toThaiDate($date, shortMonth: true);
    }

    /**
     * คำนวณเวลาที่ผ่านไปหรือเหลืออยู่ ในรูปแบบภาษาไทย
     * เช่น "ไปแล้ว 2 ปี 3 เดือน" หรือ "เหลืออีก 5 วัน"
     *
     * @param  CarbonInterface|string  $datetime  วันที่เป้าหมาย
     * @param  bool  $full  true = แสดงทุก unit, false = แสดงแค่ 2 unit หลัก
     */
    public function diffForHumans(string|CarbonInterface $datetime, bool $full = false): string
    {
        $now = Carbon::now();
        $date = $datetime instanceof CarbonInterface
            ? $datetime
            : Carbon::parse($datetime);

        $diff = $now->diff($date);
        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' ปี';
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' เดือน';
        }
        if ($diff->d > 0) {
            $parts[] = $diff->d.' วัน';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h.' ชั่วโมง';
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i.' นาที';
        }
        if ($diff->s > 0) {
            $parts[] = $diff->s.' วินาที';
        }

        if (empty($parts)) {
            return 'ตอนนี้';
        }

        if (! $full) {
            $parts = array_slice($parts, 0, 2);
        }

        $text = implode(' ', $parts);

        return $now->lte($date)
            ? "เหลืออีก {$text}"
            : "ผ่านมา {$text}";
    }

    /**
     * คำนวณปีงบประมาณไทย
     * ต.ค.(10) - ธ.ค.(12) = ปีถัดไป
     *
     * @param  string  $date  วันที่ในรูปแบบ Y-m-d
     * @return int ปี ค.ศ. ของปีงบประมาณ
     */
    public function fiscalYear(string $date): int
    {
        $carbon = Carbon::parse($date);
        $year = $carbon->year;

        if ($carbon->month >= 10) {
            $year++;
        }

        return $year;
    }

    /**
     * ตรวจสอบว่า string เป็น time format ที่ถูกต้อง
     * รองรับ HH:MM และ HH:MM:SS
     */
    public function isValidTime(string $time): bool
    {
        return (bool) preg_match(
            '/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/',
            $time,
        );
    }

    /**
     * ตรวจสอบว่า string เป็น date/datetime ที่ถูกต้องตาม format
     */
    public function isValidDate(string $date, string $format = 'Y-m-d'): bool
    {
        $d = DateTime::createFromFormat($format, $date);

        return $d !== false && $d->format($format) === $date;
    }

    /**
     * Format วันที่จาก Carbon/string/timestamp ให้ตามรูปแบบที่กำหนด
     * คืนค่า null หาก date ว่างหรือ zero
     *
     * @param  CarbonInterface|int|string|null  $date  วันที่
     * @param  string|null  $format  รูปแบบ (default: Y-m-d)
     * @param  bool  $translated  true = แปล locale ด้วย Carbon
     */
    public function format(
        CarbonInterface|string|int|null $date,
        ?string $format = null,
        bool $translated = false,
    ): ?string {
        if (empty($date)) {
            return null;
        }

        $format = $format ?? self::DEFAULT_DATE_FORMAT;

        $carbon = $date instanceof CarbonInterface
            ? $date
            : Carbon::parse($date);

        // ป้องกัน zero date (Carbon จะ parse "0000-00-00" ได้ แต่ไม่มีความหมาย)
        if ($carbon->year <= 0) {
            return null;
        }

        return $translated
            ? $carbon->translatedFormat($format)
            : $carbon->format($format);
    }

    /**
     * Format วันที่พร้อมเวลา (Y-m-d H:i:s)
     */
    public function formatDateTime(
        CarbonInterface|string|int|null $date,
        ?string $format = null,
        bool $translated = false,
    ): ?string {
        return $this->format($date, $format ?? self::DEFAULT_DATETIME_FORMAT, $translated);
    }

    /**
     * แปลง timestamp (unix) เป็น MySQL datetime string
     * เช่น 1710464400 → "2024-03-15 10:00:00"
     */
    public function timestampToDatetime(int $timestamp): string
    {
        return Carbon::createFromTimestamp($timestamp)->format(self::DEFAULT_DATETIME_FORMAT);
    }

    /**
     * แปลง MySQL datetime เป็น unix timestamp
     * เช่น "2024-03-15 10:00:00" → 1710464400
     */
    public function datetimeToTimestamp(string $datetime): int
    {
        return Carbon::parse($datetime)->timestamp;
    }

    /**
     * คืนค่าวันที่วันนี้ในรูปแบบที่อ่านง่าย (ใช้ Carbon locale)
     * เช่น "Friday, March 15, 2024"
     */
    public function today(string $format = 'dddd, LL'): string
    {
        return Carbon::now()->isoFormat($format);
    }
}
