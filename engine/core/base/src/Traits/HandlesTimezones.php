<?php

declare(strict_types=1);

namespace Core\Base\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * HandlesTimezones — แปลง created_at / updated_at เป็น timezone ของ user อัตโนมัติ
 *
 * ใช้ใน Model เพื่อให้ timestamp ที่ดึงมาอยู่ใน timezone ของ user ที่ login อยู่
 * ถ้าไม่มี user หรือ user ไม่มี timezone ใช้ config('app.timezone') เป็น fallback
 */
trait HandlesTimezones
{
    /**
     * แปลง created_at เป็น timezone ของ user
     */
    public function getCreatedAtAttribute(?string $value): ?string
    {
        return $value !== null ? $this->convertToUserTimezone($value) : null;
    }

    /**
     * แปลง updated_at เป็น timezone ของ user
     */
    public function getUpdatedAtAttribute(?string $value): ?string
    {
        return $value !== null ? $this->convertToUserTimezone($value) : null;
    }

    /**
     * แปลง datetime string เป็น timezone ของ user ที่ login อยู่
     *
     * @param  string  $value  datetime string (Y-m-d H:i:s)
     * @return string datetime ใน timezone ของ user (Y-m-d H:i:s)
     */
    protected function convertToUserTimezone(string $value): string
    {
        // ตรวจ Auth::check() ก่อน เพื่อหลีกเลี่ยง user() เป็น null
        $timezone = (Auth::check() && Auth::user()?->timezone)
            ? (string) Auth::user()->timezone
            : (string) config('app.timezone', 'UTC');

        return Carbon::parse($value)->timezone($timezone)->format('Y-m-d H:i:s');
    }
}
