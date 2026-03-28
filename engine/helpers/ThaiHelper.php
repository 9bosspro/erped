<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Thai Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับภาษาไทยและข้อมูลเฉพาะประเทศไทย
|
*/

if (! function_exists('remaining_time_text')) {
    /**
     * แปลงเวลาเหลือเป็นข้อความภาษาไทยที่อ่านง่าย
     *
     * @param  Carbon\Carbon|DateTimeInterface|string  $expiresAt  เวลาหมดอายุ
     * @return string ข้อความแสดงเวลาที่เหลือ
     */
    function remaining_time_text(Carbon\Carbon|DateTimeInterface|string $expiresAt): string
    {
        $expiresAt = Carbon\Carbon::parse($expiresAt);
        $now = Carbon\Carbon::now();

        if ($now->greaterThanOrEqualTo($expiresAt)) {
            return 'หมดอายุแล้ว';
        }

        $diff = $now->diff($expiresAt);
        $parts = [];

        if ($diff->d > 0) {
            $parts[] = $diff->d.' วัน';
        }
        if ($diff->h > 0 || $diff->d > 0) {
            $parts[] = $diff->h.' ชั่วโมง';
        }
        if ($diff->i > 0 || $diff->h > 0 || $diff->d > 0) {
            $parts[] = $diff->i.' นาที';
        }
        if ($diff->d === 0) {
            $parts[] = $diff->s.' วินาที';
        }

        return 'เหลือเวลา '.implode(' ', $parts);
    }
}

if (! function_exists('gen_citizen_ids')) {
    /**
     * สร้างเลข ID แบบกำหนดเอง (G + 12 หลัก)
     *
     * @param  int|string  $number  ตัวเลขที่ต้องการ (รองรับ leading zeros ถ้าเป็น string)
     * @return string ID ในรูปแบบ G000000000001
     *
     * @throws InvalidArgumentException ถ้า input ไม่ใช่ตัวเลข
     */
    function gen_citizen_ids(int|string $number = '1'): string
    {
        // แปลง int เป็น string
        $number = (string) $number;

        // ตรวจสอบว่าเป็นตัวเลขเท่านั้น
        if (! ctype_digit($number)) {
            throw new InvalidArgumentException('Input ต้องเป็นตัวเลขเท่านั้น');
        }

        // จำกัดความยาวสูงสุด 12 หลัก (ตัดด้านซ้ายหากเกิน)
        if (strlen($number) > 12) {
            $number = substr($number, -12);
        }

        return 'G'.str_pad($number, 12, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('random_citizen_id')) {
    /**
     * สร้างเลขบัตรประชาชนไทย 13 หลักแบบสุ่มที่ถูกต้องตามอัลกอริทึม
     *
     * หมายเหตุ: หลักแรกเป็น 9 (สำหรับต่างด้าว) เพื่อไม่ให้ชนกับเลขจริง
     *
     * @return string เลขบัตรประชาชน 13 หลัก
     *
     * @throws Exception ถ้า random_int() ล้มเหลว
     */
    function random_citizen_id(): string
    {
        $digits = [9]; // หลักแรกเป็น 9 (ต่างด้าว)

        // หลักที่ 2-12 สุ่ม 0-9
        for ($i = 1; $i <= 11; $i++) {
            $digits[] = random_int(0, 9);
        }

        // คำนวณ weighted sum
        $sum = 0;
        $multiplier = 13;
        foreach ($digits as $digit) {
            $sum += $digit * $multiplier;
            $multiplier--;
        }

        // คำนวณ check digit
        $checkDigit = (11 - ($sum % 11)) % 10;

        return implode('', $digits).$checkDigit;
    }
}

if (! function_exists('check_citizen_id')) {
    /**
     * ตรวจสอบความถูกต้องของเลขบัตรประชาชนไทย
     *
     * @param  string  $id  เลขบัตรประชาชน 13 หลัก
     * @return bool true ถ้าถูกต้องตามอัลกอริทึม
     */
    function check_citizen_id(string $id): bool
    {
        // ต้องมี 13 หลัก และเป็นตัวเลขทั้งหมด
        if (strlen($id) !== 13 || ! ctype_digit($id)) {
            return false;
        }

        // คำนวณ weighted sum (หลัก 1-12)
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $id[$i] * (13 - $i);
        }

        // คำนวณ check digit และเปรียบเทียบกับหลักที่ 13
        $checkDigit = (11 - ($sum % 11)) % 10;

        return $checkDigit === (int) $id[12];
    }
}

