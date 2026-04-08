<?php

declare(strict_types=1);

namespace Core\Base\Support\Contracts;

use Closure;

/**
 * ActionInterface — สัญญาสำหรับ Action Hook
 *
 * Action Hook รัน callbacks เพื่อ side effects เท่านั้น (ไม่คืนค่า)
 * เหมาะกับ events เช่น logging, notifications, cache clearing
 *
 * หลักการ:
 *  - fire() รัน listeners ทั้งหมดตามลำดับ priority แล้ว return void
 *  - ลำดับการรัน: priority น้อย → มาก (10 รันก่อน 20)
 *  - once listeners ถูกลบอัตโนมัติหลังรันครั้งแรก
 *
 * ตัวอย่าง:
 * ```php
 * $action->addListener('user.registered', fn(User $u) => Mail::send(new WelcomeMail($u)));
 * $action->addListener('user.registered', fn(User $u) => Log::info("User: {$u->id}"), priority: 20);
 * $action->fire('user.registered', [$user]);
 * ```
 */
interface ActionInterface
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
     */
    public function addListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
    ): void;

    /**
     * ลงทะเบียน callback แบบ one-shot (รันครั้งเดียวแล้วลบอัตโนมัติ)
     *
     * @param  array<string>|string  $hook  ชื่อ hook หรือ array ของ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (default 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (min 1)
     * @param  string|null  $scope  จำกัด scope
     */
    public function addOnceListener(
        string|array $hook,
        callable|Closure|array|string $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): void;

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
     * รัน listeners ทั้งหมดที่ลงทะเบียนกับ hook
     *
     * ไม่มีค่า return — เป็น pure side effects เท่านั้น
     *
     * @param  string  $hook  ชื่อ hook
     * @param  array<mixed>  $args  arguments ที่ส่งให้แต่ละ callback
     * @param  string|null  $scope  จำกัด scope (null = ทั้งหมด)
     */
    public function fire(string $hook, array $args = [], ?string $scope = null): void;
}
