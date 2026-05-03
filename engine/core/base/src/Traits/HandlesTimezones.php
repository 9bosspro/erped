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
     * ใช้ static cache เพื่อลด Auth::user() calls ใน loop ที่มี models หลายตัว
     *
     * @param  string  $value  datetime string (Y-m-d H:i:s)
     * @return string datetime ใน timezone ของ user (Y-m-d H:i:s)
     */
    protected function convertToUserTimezone(string $value): string
    {
        static $timezone = null;

        if ($timezone === null) {
            $userTz = Auth::check() ? Auth::user()?->timezone : null;
            $defaultTz = config('app.timezone', 'UTC');
            $timezone = (is_string($userTz) && $userTz !== '')
                ? $userTz
                : (is_string($defaultTz) ? $defaultTz : 'UTC');
        }

        return Carbon::parse($value)->timezone($timezone)->format('Y-m-d H:i:s');
    }
}
