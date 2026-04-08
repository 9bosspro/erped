<?php

declare(strict_types=1);

namespace Core\Base\Support;

use Core\Base\Support\Contracts\ActionHookEvent;
use Core\Base\Support\Contracts\FilterInterface;

/**
 * Filter Hook — รัน callbacks แบบ pipeline เพื่อแปลงค่า
 *
 * แต่ละ listener รับค่าจาก listener ก่อนหน้า แล้วส่งต่อให้ listener ถัดไป
 * ผลลัพธ์สุดท้ายคือค่าที่ผ่าน pipeline ทั้งหมด
 *
 * หลักการสำคัญ:
 * - $args[0] คือค่าเริ่มต้นที่จะถูก filter
 * - แต่ละ callback รับค่าปัจจุบันที่ $args[0] + args ที่เหลือเป็น context
 * - ถ้าไม่มี listener → คืน $args[0] โดยตรง
 *
 * ตัวอย่างการใช้งาน:
 * ```php
 * $filter->addListener('post.content', fn(string $v) => strip_tags($v));
 * $filter->addListener('post.content', fn(string $v) => nl2br($v), priority: 20);
 * $result = $filter->fire('post.content', [$rawContent]);
 * // $rawContent → strip_tags → nl2br → $result
 *
 * // รับ context arguments เพิ่มเติม
 * $filter->addListener('price.format', fn(float $price, string $currency) => ..., arguments: 2);
 * $result = $filter->fire('price.format', [$price, $currency]);
 * ```
 *
 * @see FilterInterface
 * @see ActionHookEvent
 */
final class Filter extends ActionHookEvent implements FilterInterface
{
    /**
     * รัน listeners แบบ pipeline เพื่อแปลงค่า
     *
     * - ค่าเริ่มต้นคือ $args[0] (null ถ้าไม่ส่ง)
     * - แต่ละ listener รับค่าปัจจุบันที่ $args[0] + args ที่เหลือเป็น context
     * - ค่าที่ return จาก listener จะกลายเป็น $args[0] สำหรับ listener ถัดไป
     * - ถ้าไม่มี listener → คืน $args[0] โดยไม่เปลี่ยนแปลง
     * - จัดการ once=true listeners อัตโนมัติหลังรัน
     * - รองรับ falsy values: "", 0, false, [] ไม่ทำให้ pipeline หยุด
     *
     * @param  string  $hook  ชื่อ filter hook เช่น 'post.content', 'price.format'
     * @param  array<mixed>  $args  $args[0] = ค่าที่ต้องการ filter, ที่เหลือ = context
     * @param  string|null  $scope  จำกัด scope (null = ทั้งหมด)
     * @return mixed ค่าหลังผ่าน filter pipeline ทั้งหมด
     */
    public function fire(string $hook, array $args = [], ?string $scope = null): mixed
    {
        $value = $args[0] ?? null;

        if ($hook === '' || ! $this->hasListeners($hook, $scope)) {
            return $value;
        }

        foreach ($this->getListeners($hook, $scope) as $listener) {
            // ส่งค่าล่าสุดเป็น $args[0] เสมอ — pipeline pattern
            // ใช้ $args[0] = $value เพื่อรักษา falsy values (0, '', false, [])
            $args[0] = $value;
            $parameters = array_slice($args, 0, $listener['arguments']);

            $value = call_user_func_array($listener['callback'], $parameters);

            if ($listener['once']) {
                $this->removeOnceListener($hook, $listener['id']);
            }
        }

        return $value;
    }
}
