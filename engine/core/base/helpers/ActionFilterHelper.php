<?php

declare(strict_types=1);

use Closure;
use Core\Base\Facades\Action;
use Core\Base\Facades\Filter;

/*
|--------------------------------------------------------------------------
| action-filterHelper — WordPress-style Hook System
|
| Filters: แก้ไขค่าและคืนผลลัพธ์กลับ (apply_filters / add_filters)
| Actions: ทำ side-effects โดยไม่คืนค่า (do_action / add_action)
|
| Calling convention:
|   apply_filters('hook_name', 'scope', $value1, ...)
|   do_action('hook_name', 'scope', $arg1, ...)
|
|   arg ที่ 1 = hook name (string)
|   arg ที่ 2 = scope (string|null) — ใช้ null ถ้าไม่ต้องการจำกัด scope
|   arg ที่ 3+ = ค่า/arguments ที่ส่งให้ listeners
|--------------------------------------------------------------------------
*/

// =========================================================================
// Filter Helpers
// =========================================================================

if (! function_exists('add_filters')) {
    /**
     * ลงทะเบียน filter listener สำหรับ hook ที่ระบุ
     *
     * Filter ใช้สำหรับ transform ค่าและคืนผลลัพธ์ที่แก้ไขแล้ว
     *
     * @param  array|string  $hook  ชื่อ hook (หรือ array ของ hook หลายตัว)
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับการรัน (น้อย = รันก่อน, default: 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (default: 1)
     * @param  bool  $once  true = รันครั้งเดียวแล้วถอด listener
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'frontend'
     */
    function add_filters(
        string|array $hook,
        string|array|Closure|callable $callback,
        int $priority = 10,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
    ): void {
        Filter::addListener($hook, $callback, $priority, $arguments, $once, $scope);
    }
}

if (! function_exists('remove_filter')) {
    /**
     * ถอด filter listeners ออกจาก hook ที่ระบุ
     *
     * @param  array|string|null  $hook  ชื่อ hook (null = ถอดทุก hook)
     * @param  string|null  $id  UUID ของ listener ที่ต้องการถอด (null = ถอดทั้งหมดของ hook)
     */
    function remove_filter(string|array|null $hook = null, ?string $id = null): void
    {
        Filter::removeListener($hook, $id);
    }
}

if (! function_exists('apply_filters')) {
    /**
     * รัน filter listeners ทั้งหมดของ hook และคืนค่าที่ผ่านการ transform แล้ว
     *
     * Calling convention: apply_filters($hook, $scope, $value1, $value2, ...)
     *
     * ลำดับ arguments:
     *  - arg ที่ 1: hook name (string)
     *  - arg ที่ 2: scope (string|null) — null ถ้าไม่จำกัด scope
     *  - arg ที่ 3+: ค่าที่จะ filter ($args[0] = ค่าหลัก, ที่เหลือ = context)
     *
     * @param  mixed  ...$args  arg แรก = hook, arg สอง = scope, ที่เหลือ = values
     * @return mixed ค่าที่ผ่านการ transform โดย listeners (หรือค่าเดิมถ้าไม่มี listener)
     */
    function apply_filters(mixed ...$args): mixed
    {
        $hook = (string) array_shift($args);
        $scope = is_string($args[0] ?? null) || ($args[0] ?? null) === null
            ? array_shift($args)
            : null;

        /** @var array<mixed> $values */
        $values = array_values($args);

        return Filter::fire($hook, $values, $scope);
    }
}

// =========================================================================
// Action Helpers
// =========================================================================

if (! function_exists('add_action')) {
    /**
     * ลงทะเบียน action listener สำหรับ hook ที่ระบุ
     *
     * Action ใช้สำหรับ side-effects (logging, notifications) — ไม่คืนค่า
     *
     * @param  array|string  $hook  ชื่อ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับการรัน (น้อย = รันก่อน, default: 10)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ
     * @param  bool  $once  true = รันครั้งเดียวแล้วถอด listener
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'frontend'
     */
    function add_action(
        string|array $hook,
        string|array|Closure|callable $callback,
        int $priority = 10,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
    ): void {
        Action::addListener($hook, $callback, $priority, $arguments, $once, $scope);
    }
}

if (! function_exists('do_action')) {
    /**
     * รัน action listeners ทั้งหมดของ hook (ไม่คืนค่า)
     *
     * Calling convention: do_action($hook, $scope, $arg1, $arg2, ...)
     *
     * ลำดับ arguments:
     *  - arg ที่ 1: hook name (string)
     *  - arg ที่ 2: scope (string|null)
     *  - arg ที่ 3+: arguments ที่ส่งให้ listeners
     *
     * @param  mixed  ...$args  arg แรก = hook, arg สอง = scope, ที่เหลือ = args
     */
    function do_action(mixed ...$args): void
    {
        $hook = (string) array_shift($args);
        $scope = is_string($args[0] ?? null) || ($args[0] ?? null) === null
            ? array_shift($args)
            : null;

        /** @var array<mixed> $actionArgs */
        $actionArgs = array_values($args);

        Action::fire($hook, $actionArgs, $scope);
    }
}

// =========================================================================
// Once Helpers
// =========================================================================

if (! function_exists('add_action_once')) {
    /**
     * ลงทะเบียน action listener แบบ one-shot (รันครั้งเดียวแล้วถอดอัตโนมัติ)
     *
     * @param  array|string  $hook  ชื่อ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (default: 10)
     * @param  int  $arguments  จำนวน argument ที่รับ
     * @param  string|null  $scope  scope
     */
    function add_action_once(
        string|array $hook,
        string|array|Closure|callable $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): void {
        Action::addOnceListener($hook, $callback, $priority, $arguments, $scope);
    }
}

if (! function_exists('add_filter_once')) {
    /**
     * ลงทะเบียน filter listener แบบ one-shot (รันครั้งเดียวแล้วถอดอัตโนมัติ)
     *
     * @param  array|string  $hook  ชื่อ hook
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับ (default: 10)
     * @param  int  $arguments  จำนวน argument ที่รับ
     * @param  string|null  $scope  scope
     */
    function add_filter_once(
        string|array $hook,
        string|array|Closure|callable $callback,
        int $priority = 10,
        int $arguments = 1,
        ?string $scope = null,
    ): void {
        Filter::addOnceListener($hook, $callback, $priority, $arguments, $scope);
    }
}

// =========================================================================
// Inspection Helpers
// =========================================================================

if (! function_exists('has_action')) {
    /**
     * ตรวจสอบว่า hook มี action listeners หรือไม่
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = ทุก scope)
     * @return bool true ถ้ามี listeners
     */
    function has_action(string $hook, ?string $scope = null): bool
    {
        return Action::hasListeners($hook, $scope);
    }
}

if (! function_exists('has_filter')) {
    /**
     * ตรวจสอบว่า hook มี filter listeners หรือไม่
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = ทุก scope)
     * @return bool true ถ้ามี listeners
     */
    function has_filter(string $hook, ?string $scope = null): bool
    {
        return Filter::hasListeners($hook, $scope);
    }
}

if (! function_exists('get_hooks')) {
    /**
     * ดึงรายการ listeners ของ hook ที่ระบุ
     *
     * @param  string  $hook  ชื่อ hook
     * @param  string|null  $scope  กรอง scope (null = ทุก scope)
     * @param  bool  $isFilter  true = filters, false = actions
     * @return list<array<string, mixed>> รายการ listeners
     */
    function get_hooks(
        string $hook,
        ?string $scope = null,
        bool $isFilter = true,
    ): array {
        return $isFilter
            ? Filter::getListeners($hook, $scope)
            : Action::getListeners($hook, $scope);
    }
}
