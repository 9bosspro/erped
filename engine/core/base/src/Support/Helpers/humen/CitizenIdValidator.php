<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\humen;

/**
 * CitizenIdValidator — ตรวจสอบและสร้างเลขบัตรประชาชน 13 หลัก
 *
 * Consolidated จาก MyHuman + MyValidable:
 * - ลบ duplicate 3 เวอร์ชัน (ValidCitizenId, checkCitizenID, validateCitizenID) → 1 เวอร์ชัน
 * - อัลกอริทึมเดียวกัน เขียน clean ขึ้น พร้อม PHPDoc ชัดเจน
 */
final class CitizenIdValidator
{
    private const LENGTH = 13;

    /**
     * ตรวจสอบความถูกต้องของเลขบัตรประชาชน 13 หลัก
     *
     * อัลกอริทึม checksum ของ สำนักทะเบียน:
     *   sum = Σ (digit[i] × (13 - i))  สำหรับ i = 0..11
     *   checkDigit = (11 - (sum mod 11)) mod 10
     *   ต้องตรงกับ digit[12]
     *
     * @param  string  $id  เลขบัตรประชาชน (ตัวเลขล้วน ไม่มี dash)
     */
    public function validate(string $id): bool
    {
        if (strlen($id) !== self::LENGTH || ! ctype_digit($id)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $id[$i] * (13 - $i);
        }

        $checkDigit = (11 - ($sum % 11)) % 10;

        return $checkDigit === (int) $id[12];
    }

    /**
     * สร้างเลขบัตรประชาชน random ที่ผ่าน checksum
     * (ใช้สำหรับ testing / seed data เท่านั้น)
     */
    public function generate(): string
    {
        // หลักแรก: 1-8 (ไม่ใช้ 0 และ 9 ตาม spec ของสำนักทะเบียน)
        $digits = [random_int(1, 8)];

        for ($i = 1; $i < 12; $i++) {
            $digits[] = random_int(0, 9);
        }

        // คำนวณ check digit
        $sum = 0;
        foreach ($digits as $i => $digit) {
            $sum += $digit * (13 - $i);
        }

        $digits[] = (11 - ($sum % 11)) % 10;

        return implode('', $digits);
    }

    /**
     * Format เลขบัตรประชาชนให้อ่านง่าย: X-XXXX-XXXXX-XX-X
     * คืนค่าต้นฉบับหากไม่ valid
     */
    public function format(string $id): string
    {
        if (! $this->validate($id)) {
            return $id;
        }

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($id, 0, 1),
            substr($id, 1, 4),
            substr($id, 5, 5),
            substr($id, 10, 2),
            substr($id, 12, 1),
        );
    }

    /**
     * ลบ dash ออกจาก formatted citizen ID
     * เช่น "1-2345-67890-12-3" → "1234567890123"
     */
    public function normalize(string $id): string
    {
        return str_replace('-', '', $id);
    }
}
