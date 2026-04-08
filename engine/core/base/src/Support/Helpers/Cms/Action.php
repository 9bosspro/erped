<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Cms;

use Core\Base\Support\Contracts\ActionHookEvent;
use Core\Base\Support\Contracts\ActionInterface;

/**
 * Action Hook — รัน callbacks โดยไม่คืนค่า (side effects เท่านั้น)
 *
 * ใช้สำหรับ events ที่ต้องการทำงาน เช่น logging, notifications, cache clearing
 * โดยไม่ต้องการแก้ไขหรือส่งคืนค่าใดๆ
 *
 * ตัวอย่างการใช้งาน:
 * ```php
 * $action->addListener('order.paid', fn(Order $order) => Log::info("Paid: {$order->id}"));
 * $action->addListener('order.paid', fn(Order $order) => Mail::send(new OrderPaidMail($order)), priority: 20);
 * $action->addOnceListener('app.boot', fn() => Cache::flush());  // รันครั้งเดียวเมื่อ boot
 * $action->fire('order.paid', [$order]);
 * ```
 *
 * @see ActionInterface
 * @see ActionHookEvent
 */
final class Action extends ActionHookEvent implements ActionInterface
{
    /**
     * รัน listeners ทั้งหมดที่ลงทะเบียนกับ hook
     *
     * - ไม่มีค่า return (pure side effects)
     * - รองรับ scope filtering: null = รัน listeners ทุก scope
     * - จัดการ once=true listeners อัตโนมัติหลังรัน
     * - เรียงลำดับตาม priority (น้อย → มาก)
     *
     * @param  string  $hook  ชื่อ hook เช่น 'user.created', 'order.paid'
     * @param  array<mixed>  $args  arguments ที่ส่งให้แต่ละ callback
     * @param  string|null  $scope  จำกัด scope เช่น 'api', 'web' (null = ทั้งหมด)
     */
    public function fire(string $hook, array $args = [], ?string $scope = null): void
    {
        if ($hook === '' || ! $this->hasListeners($hook, $scope)) {
            return;
        }

        foreach ($this->getListeners($hook, $scope) as $listener) {
            $parameters = array_slice($args, 0, $listener['arguments']);
            call_user_func_array($listener['callback'], $parameters);

            if ($listener['once']) {
                $this->removeOnceListener($hook, $listener['id']);
            }
        }
    }
}
