<?php

declare(strict_types=1);

namespace Core\Base\Support\Contracts;

use Closure;

/**
 * FilterInterface — สัญญาสำหรับ Filter Hook
 *
 * Filter Hook รัน callbacks แบบ pipeline เพื่อแปลงค่า
 * แต่ละ listener รับค่าจาก listener ก่อนหน้า แล้วส่งต่อให้ listener ถัดไป
 *
 * หลักการ:
 *  - $args[0] คือค่าเริ่มต้นที่จะถูก filter
 *  - ค่าไหลผ่าน pipeline ตามลำดับ priority (น้อย → มาก)
 *  - fire() คืนค่าหลังผ่าน pipeline ทั้งหมด
 *  - ถ้าไม่มี listener → คืน $args[0] โดยไม่เปลี่ยนแปลง
 *
 * ตัวอย่าง:
 * ```php
 * $filter->addListener('post.content', fn(string $v) => strip_tags($v));
 * $filter->addListener('post.content', fn(string $v) => nl2br($v), priority: 20);
 * $result = $filter->fire('post.content', [$rawContent]);
 * // $rawContent → strip_tags → nl2br → $result
 * ```
 */
interface FilterInterface
{
    /**
     * ลงทะเบียน callback สำหรับ hook(s)
     *
     * @param  array<string>|string  $hook  ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (น้อย = รันก่อน, default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  bool  $once  true = รันครั้งเดียวแล้วลบออกอัตโนมัติ
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'api', 'web'
     * @return string UUID ของ listener — ใช้ removeListener() เพื่อลบ
     */
    public function addListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
    ): string;

    /**
     * ลงทะเบียน callback แบบ one-shot (รันครั้งเดียวแล้วลบอัตโนมัติ)
     *
     * @param  array<string>|string  $hook  ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  string|null  $scope  จำกัด scope
     * @return string UUID ของ listener
     */
    public function addOnceListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): string;

    /**
     * ลบ listeners ของ hook หรือลบเฉพาะ listener ที่ระบุ id
     *
     * @param  array<string>|string|null  $hook  null = ลบทั้งหมดทุก hook
     * @param  string|null  $id  UUID ของ listener
     */
    public function removeListener(string|array|null $hook = null, ?string $id = null): static;

    /**
     * ตรวจสอบว่า hook มี listeners หรือไม่
     *
     * @param  string|null  $hook  null = ตรวจสอบทุก hook
     * @param  string|null  $scope  กรอง scope
     */
    public function hasListeners(?string $hook = null, ?string $scope = null): bool;

    /**
     * คืน listeners สำหรับ hook ที่ระบุ
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = ทั้งหมด)
     * @return list<array<string, mixed>>
     */
    public function getListeners(string $hook, ?string $scope = null): array;

    /**
     * นับจำนวน listeners
     *
     * @param  string|null  $hook  null = นับทั้งหมดทุก hook
     * @param  string|null  $scope  กรอง scope
     */
    public function getListenerCount(?string $hook = null, ?string $scope = null): int;

    /**
     * รัน listeners แบบ pipeline เพื่อแปลงค่า
     *
     * - ค่าเริ่มต้นคือ $args[0] (null ถ้าไม่ส่ง)
     * - แต่ละ listener รับค่าปัจจุบัน + args ที่เหลือเป็น context
     * - ถ้าไม่มี listener → คืน $args[0] โดยไม่เปลี่ยนแปลง
     *
     * @param  string  $hook  ชื่อ filter hook
     * @param  array<mixed>  $args  $args[0] = ค่าที่ต้องการ filter, ที่เหลือ = context
     * @param  string|null  $scope  จำกัด scope (null = ทั้งหมด)
     * @return mixed ค่าหลังผ่าน filter pipeline ทั้งหมด
     */
    public function fire(string $hook, array $args = [], ?string $scope = null): mixed;
}
