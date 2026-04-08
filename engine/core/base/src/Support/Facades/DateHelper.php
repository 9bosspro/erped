<?php

declare(strict_types=1);

namespace Core\Base\Support\Facades;

use Carbon\Carbon;
use Core\Base\Support\Helpers\DateTime\ThaiDateTime;

/**
 * DateHelper — Facade wrapper สำหรับ ThaiDateTime
 *
 * ออกแบบสำหรับ DI injection ใน Controller / Service
 * โดยรวม Carbon + ThaiDateTime เข้าด้วยกัน
 *
 * การใช้งาน:
 * ```php
 * class ExampleController extends Controller {
 *     public function index(DateHelper $dateHelper) {
 *         $thai = $dateHelper->toThaiDate('2025-09-09');
 *         $now  = $dateHelper->now();
 *     }
 * }
 * ```
 */
final class DateHelper
{
    private readonly ThaiDateTime $thaiDateTime;

    public function __construct(
        private readonly string $dateFormat = 'Y-m-d',
    ) {
        $this->thaiDateTime = new ThaiDateTime;
    }

    /**
     * คืนค่า Carbon instance ของเวลาปัจจุบัน
     */
    public function now(): Carbon
    {
        return Carbon::now();
    }

    /**
     * แปลงวันที่เป็นรูปแบบไทย (พ.ศ.)
     * เช่น "2024-03-15" → "15 มีนาคม 2567"
     *
     * @param  string  $date  วันที่ในรูปแบบ MySQL หรือ ISO
     * @param  bool  $shortMonth  true = ใช้ชื่อเดือนย่อ
     */
    public function toThaiDate(string $date, bool $shortMonth = false): string
    {
        return $this->thaiDateTime->toThaiDate($date, $shortMonth);
    }

    /**
     * แปลงวันที่พร้อมเวลาเป็นรูปแบบไทยสมบูรณ์
     * เช่น "วันศุกร์ ที่ 15 มีนาคม พ.ศ. 2567 เวลา 10:30:00 น."
     */
    public function toThaiDateTime(string $datetime): string
    {
        return $this->thaiDateTime->toThaiDateTime($datetime);
    }

    /**
     * Format วันที่ตามรูปแบบที่กำหนด
     *
     * @param  string  $date  วันที่
     * @param  string|null  $format  รูปแบบ (null = ใช้ค่า default)
     */
    public function format(string $date, ?string $format = null): ?string
    {
        return $this->thaiDateTime->format($date, $format ?? $this->dateFormat);
    }

    /**
     * คำนวณเวลาที่ผ่านไปแบบภาษาไทย
     * เช่น "ผ่านมา 2 ปี 3 เดือน"
     */
    public function diffForHumans(string $datetime, bool $full = false): string
    {
        return $this->thaiDateTime->diffForHumans($datetime, $full);
    }

    /**
     * ดึง ThaiDateTime instance สำหรับใช้ method อื่นๆ โดยตรง
     */
    public function thai(): ThaiDateTime
    {
        return $this->thaiDateTime;
    }
}
