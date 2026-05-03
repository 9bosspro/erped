<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Illuminate\Support\Facades\Log;

/**
 * LoggerTrait — ฟังก์ชัน logging อย่างง่ายสำหรับ class ที่ต้องการ log
 *
 * ใช้ Laravel Log facade ไม่ใช่ echo ป้องกันข้อมูล leak ใน production
 * รองรับทุก PSR-3 log level: debug, info, notice, warning, error, critical, alert, emergency
 *
 * @experimental ยังไม่มี usage ในโปรเจค — พร้อมใช้เมื่อ module ต้องการ
 */
trait LoggerTrait
{
    /**
     * บันทึก debug message
     *
     * @param  string  $message  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function log(string $message, array $context = []): void
    {
        Log::debug($message, $context);
    }

    /**
     * บันทึก info message
     *
     * @param  string  $message  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logInfo(string $message, array $context = []): void
    {
        Log::info($message, $context);
    }

    /**
     * บันทึก warning message
     *
     * @param  string  $message  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logWarning(string $message, array $context = []): void
    {
        Log::warning($message, $context);
    }

    /**
     * บันทึก error message
     *
     * @param  string  $message  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logError(string $message, array $context = []): void
    {
        Log::error($message, $context);
    }

    /**
     * บันทึก critical message — สำหรับ error ที่ต้องการการแก้ไขด่วน
     *
     * @param  string  $message  ข้อความที่ต้องการ log
     * @param  array<string, mixed>  $context  ข้อมูลเพิ่มเติม
     */
    public function logCritical(string $message, array $context = []): void
    {
        Log::critical($message, $context);
    }
}
