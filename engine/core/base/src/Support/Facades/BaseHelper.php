<?php

declare(strict_types=1);

namespace Core\Base\Support\Facades;

/**
 * BaseHelper — ดึง format settings จาก config กลาง
 *
 * ใช้สำหรับดึง date format ที่กำหนดไว้ใน core.base.general
 * เพื่อให้ทุก module ใช้ format เดียวกัน
 */
final class BaseHelper
{
    /**
     * ดึงรูปแบบวันที่ (เฉพาะวันที่) จาก config
     * เช่น "d/m/Y" หรือ "Y-m-d"
     */
    public function getDateFormat(): string
    {
        return (string) config('core.base.general.date_format.date', 'Y-m-d');
    }

    /**
     * ดึงรูปแบบวันที่+เวลา จาก config
     * เช่น "d/m/Y H:i:s" หรือ "Y-m-d H:i:s"
     */
    public function getDateTimeFormat(): string
    {
        return (string) config('core.base.general.date_format.date_time', 'Y-m-d H:i:s');
    }
}
