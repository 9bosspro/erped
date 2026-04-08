<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Localization;

/**
 * ThaiBahtHelper — คลาสสำหรับจัดการแปลงตัวเลขเป็นคำอ่านภาษาไทย (บาท/สตางค์)
 */
class ThaiBahtHelper
{
    /**
     * แปลงตัวเลขจำนวนเงินเป็นคำอ่านภาษาไทย (บาทและสตางค์)
     *
     * @param  float|int  $amount  จำนวนเงิน
     * @return string คำอ่านภาษาไทย เช่น "หนึ่งร้อยบาทถ้วน"
     */
    public static function convert(float|int $amount): string
    {
        $amount = number_format((float) $amount, 2, '.', '');
        [$baht, $satang] = explode('.', $amount);

        $text = '';

        if ((int) $baht > 0) {
            $text .= self::numberToThaiWords((int) $baht).'บาท';
        }

        if ((int) $satang > 0) {
            $text .= self::numberToThaiWords((int) $satang).'สตางค์';
        } else {
            $text .= 'ถ้วน';
        }

        return $text;
    }

    /**
     * แปลงตัวเลข integer เป็นคำอ่านภาษาไทย
     *
     * @param  int  $number  ตัวเลขที่ต้องการแปลง
     * @return string คำอ่านภาษาไทย
     */
    public static function numberToThaiWords(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        // จัดการกรณีเกินล้าน (recursive)
        if ($number > 1_000_000) {
            $millions = (int) ($number / 1_000_000);
            $remainder = $number % 1_000_000;

            $result = self::numberToThaiWords($millions).'ล้าน';
            if ($remainder > 0) {
                $result .= self::numberToThaiWords($remainder);
            }

            return $result;
        }

        $units = ['', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $positions = ['', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน'];

        $str = (string) $number;
        $len = strlen($str);
        $result = '';

        for ($i = 0; $i < $len; $i++) {
            $digit = (int) $str[$i];
            $pos = $len - $i - 1;

            if ($digit === 0) {
                continue;
            }

            if ($digit === 2 && $pos === 1) {
                $result .= 'ยี่';
            } elseif ($digit === 1 && $pos === 1) {
                $result .= 'เอ็ด';
            } elseif ($digit === 1 && $pos === 0 && $len > 1) {
                $result .= 'เอ็ด';
            } elseif ($digit >= 0 && $digit <= 9) {
                $result .= $units[$digit];
            }

            if ($pos >= 0 && $pos <= 5) {
                $result .= $positions[$pos];
            }
        }

        return $result;
    }
}
