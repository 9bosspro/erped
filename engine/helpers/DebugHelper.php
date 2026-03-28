<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Debug Helper Functions
|--------------------------------------------------------------------------
|
| ฟังก์ชันช่วยเหลือสำหรับการ Debug
|
*/

if (! function_exists('tt')) {
    /**
     * ฟังก์ชันครอบจักรวาลสำหรับ Debug
     *
     * @param  mixed  $var
     * @param  bool  $return
     */
    function tt($var = '', $return = false): void
    {
        is_bool($return) or ($return = true);
        $_var = '';

        if (is_null($var)) {
            $_var = ' var เป็น  คัวแปร ค่าว่าง ประเภท   null ';
        } elseif (is_numeric($var)) {
            $_var = 'var  เป็น ชนิด ตัวเลข';

            if (is_string($var)) {
                $_var = $_var.'  แบบข้อความ   ซึ่งเป็นไปตามชนิด คือ   ';
            } else {
                $_var = $_var.'  แบบ ตัวเลข  ซึ่งเป็นไปตามชนิด คือ   ';
            }

            $var = $var + 0;
            if (is_int($var)) {
                $_var = $_var.'  เป็น   จำนวนเต็ม ';
            } elseif (is_float($var)) {
                $_var = $_var.'  เป็น   จำนวนทศนิยม ';
            }
        } elseif (is_bool($var)) {
            $_var = 'var เป็น  ค่า บูลีน    คือ ';
        } elseif (is_object($var)) {
            $_var = 'var  เป็น  ออฟเจ็ค   คือ ';
            if (empty($var)) {
                $_var = $_var.' เป็น  ออฟเจ็ค  ค่าว่าง  ';
            } else {
                $_var = $_var.' เป็น  ออฟเจ็ค  ไม่ค่าว่าง    ';
            }
        } elseif (is_array($var)) {
            $_var = 'var  เป็น  array';
            if (empty($var)) {
                $_var = $_var.' เป็น  array  ค่าว่าง  ';
            } else {
                $_var = $_var.' เป็น  array  ไม่ค่าว่าง    ';
            }
        } elseif (is_string($var)) {
            $_var = 'var เป็น  ข้อความ  string ';
            if (empty($var)) {
                $_var = $_var.' เป็น    ค่าว่าง  ';
            }
            if (is_jsons($var)) {
                $_var = $_var.' แบบ   json ';
            }
        } else {
            $_var = 'var นอกกฏ   ต้องศึกษา เร่งด่วน';
        }

        $tt = gettype($var);

        if ($return) {
            $uu = '';
            $uu .= $_var."\n";
            $uu .= 'ชนิดตัวแปร   ค่าตามระบบ คือ   '.$tt."  มีค่าดังนี้  \n";
            $uu .= print_r($var, true);
            dd($uu);
        }

        $uu = '<pre>';
        $uu .= $_var."\n";
        $uu .= 'ชนิดตัวแปร   ค่าตามระบบ คือ   '.$tt."  มีค่าดังนี้  \n";
        $uu .= print_r($var, true);
        $uu .= '</pre>';

        echo $uu;
        exit();
    }
}

if (! function_exists('ttt')) {
    /**
     * แสดงข้อมูลตัวแปรอย่างสวยงาม + ชนิดข้อมูลภาษาไทย
     * ใช้แทน dd() หรือ var_dump() ในช่วงพัฒนา/ดีบัก
     *
     * @param  mixed  $var  ตัวแปรที่ต้องการตรวจสอบ
     * @param  bool  $die  หยุดการทำงานหลังแสดงผลหรือไม่ (default: true)
     */
    function ttt(mixed $var = null, bool $die = true): void
    {
        // กำหนดประเภทเป็นภาษาไทย
        $typeLabels = [
            'NULL' => 'ค่าว่าง (null)',
            'boolean' => 'บูลีน (boolean)',
            'integer' => 'จำนวนเต็ม (integer)',
            'double' => 'จำนวนทศนิยม (float/double)',
            'string' => 'ข้อความ (string)',
            'array' => 'อาเรย์ (array)',
            'object' => 'อ็อบเจกต์ (object)',
            'resource' => 'รีซอร์ส (resource)',
            'unknown type' => 'ไม่ทราบชนิด (unknown)',
        ];

        $type = gettype($var);
        $thaiType = $typeLabels[$type] ?? $typeLabels['unknown type'];

        // ตรวจสอบเพิ่มเติม
        $extra = [];
        if ($type === 'string') {
            if ($var === '') {
                $extra[] = 'ข้อความว่าง';
            }
            if (json_decode($var) !== null && json_last_error() === JSON_ERROR_NONE) {
                $extra[] = 'เป็น JSON ที่ถูกต้อง';
            }
        }
        if ($type === 'array' && empty($var)) {
            $extra[] = 'อาเรย์ว่าง';
        }
        if ($type === 'object') {
            $extra[] = 'คลาส: '.get_class($var);
        }

        $extraText = $extra ? ' → '.implode(', ', $extra) : '';

        // สร้างข้อความแสดงผล
        $output = '<pre style="background:#1e1e1e; color:#d4d4d4; padding:15px; border-radius:8px; font-size:14px; margin:20px; line-height:1.5;">';
        $output .= "<strong style=\"color:#ff79c6;\">ตัวแปรที่ตรวจสอบ:</strong>\n";
        $output .= "<span style=\"color:#8be9fd;\">{$thaiType}{$extraText}</span>\n\n";
        $output .= "<strong style=\"color:#ff79c6;\">ค่าที่ได้จาก gettype():</strong> <span style=\"color:#f1fa8c;\">{$type}</span>\n";
        $output .= "<strong style=\"color:#ff79c6;\">ค่าจริงของตัวแปร:</strong>\n\n";
        $output .= htmlspecialchars(print_r($var, true), ENT_QUOTES, 'UTF-8');
        $output .= "\n\n<small style=\"color:#6272a4;\">[ttt()] แสดงผลเมื่อ ".now()->format('d/m/Y H:i:s').'</small>';
        $output .= '</pre>';

        echo $output;

        if ($die) {
            exit();
        }
    }
}
