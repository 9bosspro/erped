<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\String;

/**
 * ThaiTextProcessor — จัดการข้อความภาษาไทย
 *
 * ความรับผิดชอบ:
 * - แปลงภาษาไทยเป็น slug (transliteration)
 * - ตัด substring ภาษาไทยที่ถูกต้อง (จัดการสระลอย/วรรณยุกต์)
 * - แปลงตัวเลขเป็น "บาทถ้วน" (Thai Baht text)
 */
final class ThaiTextProcessor
{
    /**
     * ตารางแปลงตัวอักษรไทยเป็นภาษาอังกฤษ (Thai-to-English mapping)
     *
     * @var array<string, string>
     */
    private const THAI_TO_ENG = [
        // พยัญชนะ
        'ก' => 'k', 'ข' => 'kh', 'ฃ' => 'kh', 'ค' => 'kh', 'ฅ' => 'kh',
        'ฆ' => 'kh', 'ง' => 'ng', 'จ' => 'ch', 'ฉ' => 'ch', 'ช' => 'ch',
        'ซ' => 's', 'ฌ' => 'ch', 'ญ' => 'y', 'ฎ' => 'd', 'ฏ' => 't',
        'ฐ' => 'th', 'ฑ' => 'th', 'ฒ' => 'th', 'ณ' => 'n', 'ด' => 'd',
        'ต' => 't', 'ถ' => 'th', 'ท' => 'th', 'ธ' => 'th', 'น' => 'n',
        'บ' => 'b', 'ป' => 'p', 'ผ' => 'ph', 'ฝ' => 'f', 'พ' => 'ph',
        'ฟ' => 'f', 'ภ' => 'ph', 'ม' => 'm', 'ย' => 'y', 'ร' => 'r',
        'ล' => 'l', 'ว' => 'w', 'ศ' => 's', 'ษ' => 's', 'ส' => 's',
        'ห' => 'h', 'ฬ' => 'l', 'อ' => 'a', 'ฮ' => 'h',
        // สระเดี่ยว
        'ะ' => 'a', 'า' => 'a', 'ิ' => 'i', 'ี' => 'i', 'ุ' => 'u',
        'ู' => 'u', 'เะ' => 'e', 'แะ' => 'ae', 'โะ' => 'o', 'ไ' => 'ai',
        'ใ' => 'ai', 'ำ' => 'am',
        // สระผสม
        'เ' => 'e', 'แ' => 'ae', 'โ' => 'o', 'ๅ' => 'ao',
        // วรรณยุกต์และอื่นๆ
        '่' => '', '้' => '', '๊' => '', '๋' => '', '์' => '',
        'ฯ' => '', 'ๆ' => '', 'ํ' => 'am', 'ั' => 'a',
    ];

    /**
     * แปลงข้อความภาษาไทยเป็น URL slug ที่ไม่ซ้ำ
     *
     * @param  string  $text  ข้อความภาษาไทย
     * @param  bool  $unique  true = เพิ่ม unique suffix (6 chars)
     * @return string slug ที่ปลอดภัยสำหรับ URL
     */
    public function thaiToUniqueSlug(string $text, bool $unique = true): string
    {
        $characters = mb_str_split($text);
        $result = '';
        $prevChar = '';

        foreach ($characters as $index => $char) {
            $nextChar = $characters[$index + 1] ?? '';

            if (isset(self::THAI_TO_ENG[$char])) {
                if ($char === 'เ' && $nextChar === 'ี') {
                    $result .= 'ia';
                    $characters[$index + 1] = '';
                } elseif ($char === 'อ' && $prevChar) {
                    $result .= 'o';
                } elseif ($char === 'แ' && $nextChar === 'ะ') {
                    $result .= 'ae';
                    $characters[$index + 1] = '';
                } elseif ($char === 'โ' && $nextChar === 'ะ') {
                    $result .= 'o';
                    $characters[$index + 1] = '';
                } elseif ($char === 'เ' && $nextChar === 'าะ') {
                    $result .= 'o';
                    $characters[$index + 1] = '';
                } elseif ($char === 'ำ' && $prevChar && (self::THAI_TO_ENG[$prevChar] ?? '') !== '') {
                    $result .= 'am';
                } else {
                    $result .= self::THAI_TO_ENG[$char];
                }
            } else {
                $result .= $char;
            }

            $prevChar = $char;
        }

        $result = strtolower($result);
        $result = preg_replace('/[^a-z0-9]+/u', '-', $result);
        $result = preg_replace('/-+/', '-', $result);
        $result = trim($result, '-');

        if ($unique) {
            $result .= '-'.substr(md5(uniqid('', true)), 0, 6);
        }

        return $result;
    }

    /**
     * ตัด substring ภาษาไทยให้ถูกต้อง (จัดการสระลอย/วรรณยุกต์)
     *
     * ภาษาไทยมีสระลอย (ิ, ี, ุ, ู) และวรรณยุกต์ (่, ้, ๊, ๋) ที่ไม่นับเป็น 1 ตัวอักษร
     * method นี้จะเลื่อน length ให้รวมอักขระเหล่านี้อัตโนมัติ
     *
     * @param  string  $string  string ภาษาไทย
     * @param  int  $start  ตำแหน่งเริ่มต้น
     * @param  int  $length  จำนวนตัวอักษรที่ต้องการ (ไม่นับสระลอย/วรรณยุกต์)
     * @return string substring
     */
    public function getSubStrTH(string $string, int $start, int $length): string
    {
        $length = $length + $start - 1;
        $array = mb_str_split($string);
        $return = '';

        for ($i = $start; $i < count($array); $i++) {
            $ascii = ord(@iconv('UTF-8', 'TIS-620', $array[$i]));

            // สระลอยและวรรณยุกต์ไม่นับเป็นตัวอักษร — เลื่อน length ออก
            if (
                $ascii === 209 ||
                ($ascii >= 212 && $ascii <= 218) ||
                ($ascii >= 231 && $ascii <= 238)
            ) {
                $length++;
            }

            if ($i >= $start) {
                $return .= $array[$i];
            }

            if ($i >= $length) {
                break;
            }
        }

        return $return;
    }

    /**
     * แปลงตัวเลขเป็นข้อความบาทไทย
     *
     * เช่น 1234.50 → "หนึ่งพันสองร้อยสามสิบสี่บาทห้าสิบสตางค์"
     * เช่น 100 → "หนึ่งร้อยบาทถ้วน"
     *
     * @param  float|string  $priceNumber  จำนวนเงิน
     * @return string ข้อความบาทไทย
     */
    public function setBahtText(float|string $priceNumber): string
    {
        $countDigit = [];
        $numberSet = [];
        $convertInteger = [];
        $hideZero = true;

        $digitText = ['ศูนย์', 'หนึ่ง', 'สอง', 'สาม', 'สี่', 'ห้า', 'หก', 'เจ็ด', 'แปด', 'เก้า'];
        $digitText2 = ['เอ็ด', 'ยี่', 'สิบ', 'ร้อย', 'พัน', 'หมื่น', 'แสน', 'ล้าน'];

        $priceNumber = str_replace(',', '', (string) $priceNumber);
        $priceNumber = explode('.', $priceNumber);

        // จัดการทศนิยม (ปัดเศษ 2 ตำแหน่ง)
        if (! empty($priceNumber[1])) {
            if (strlen($priceNumber[1]) === 1) {
                $priceNumber[1] .= '0';
            } elseif (strlen($priceNumber[1]) > 2) {
                if ($priceNumber[1][2] < 5) {
                    $priceNumber[1] = substr($priceNumber[1], 0, 2);
                } else {
                    $priceNumber[1] = $priceNumber[1][0].($priceNumber[1][1] + 1);
                }
            }
        }

        $lengthPrice = count($priceNumber);
        for ($indexPrice = 0; $indexPrice < $lengthPrice; $indexPrice++) {
            $countDigit[$indexPrice] = strlen($priceNumber[$indexPrice]);

            if ($countDigit[$indexPrice] <= 7) {
                $numberSet[$indexPrice][] = $priceNumber[$indexPrice];
            } else {
                $cntSet = ceil($countDigit[$indexPrice] / 6);
                for ($cnt = 1; $cnt <= $cntSet; $cnt++) {
                    if ($cnt === 1) {
                        $start = 0;
                        $setLen = $countDigit[$indexPrice] - ($cntSet - 1) * 6;
                    } else {
                        $start = $countDigit[$indexPrice] - ($cntSet + 1 - $cnt) * 6;
                        $setLen = 6;
                    }
                    $numberSet[$indexPrice][] = substr($priceNumber[$indexPrice], (int) $start, (int) $setLen);
                }
            }

            $convertInteger[$indexPrice] = '';
            $cntSetDigit = count($numberSet[$indexPrice]);

            for ($indexSet = 0; $indexSet < $cntSetDigit; $indexSet++) {
                if ($indexSet > 0) {
                    $convertInteger[$indexPrice] .= $digitText2[7];
                }

                $setNumber = $numberSet[$indexPrice][$indexSet];
                $numLength = strlen($setNumber);
                $numberText = '';

                for ($indexNum = 7; $indexNum >= 2; $indexNum--) {
                    if ($numLength >= $indexNum) {
                        $num = (int) substr($setNumber, -$indexNum, 1);
                        if ($num > 0) {
                            if ($indexNum === 2 && $num === 1) {
                                $numberText .= $digitText2[$indexNum];
                            } elseif ($indexNum === 2 && $num === 2) {
                                $numberText .= $digitText2[1].$digitText2[$indexNum];
                            } else {
                                $numberText .= $digitText[$num].$digitText2[$indexNum];
                            }
                        }
                    }
                }

                if ($numLength >= 1) {
                    $num = (int) substr($setNumber, -1, 1);
                    if ($num > 0) {
                        if ($num === 1 && $numLength > 1 && (int) substr($setNumber, -2, 1) > 0) {
                            $numberText .= $digitText2[0];
                        } else {
                            $numberText .= $digitText[$num];
                        }
                    }
                }

                $convertInteger[$indexPrice] .= $numberText;
            }
        }

        $convertText = '';
        if (! empty($convertInteger[0]) || $hideZero !== true || empty($convertInteger[1])) {
            if ($convertInteger[0] === '') {
                $convertInteger[0] = $digitText[0];
            }
            $convertText .= $convertInteger[0].'บาท';
        }

        if (count($priceNumber) === 1 || empty($convertInteger[1])) {
            $convertText .= 'ถ้วน';
        } else {
            $convertText .= $convertInteger[1].'สตางค์';
        }

        return $convertText;
    }

    // ─── Backward Compatibility Alias ────────────────────────────────

    /** @deprecated ใช้ setBahtText() แทน */
    public function set_baht_text(float|string $price_number): string
    {
        return $this->setBahtText($price_number);
    }
}
