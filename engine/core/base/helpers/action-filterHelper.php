<?php

declare(strict_types=1);

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
     * @param  int  $priority  ลำดับการรัน (สูงกว่า = รันก่อน, default: 20)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ (default: 1)
     * @param  bool  $once  true = รันครั้งเดียวแล้วถอด listener
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'frontend'
     * @param  string|null  $id  ID ของ listener สำหรับการถอดทีหลัง
     */
    function add_filters(
        string|array $hook,
        string|array|Closure|callable $callback,
        int $priority = 20,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
        ?string $id = null,
    ): void {
        Filter::addListener($hook, $callback, $priority, $arguments, $once, $scope, $id);
    }
}

if (! function_exists('remove_filter')) {
    /**
     * ถอด filter listeners ออกจาก hook ที่ระบุ
     *
     * @param  array|string|null  $hook  ชื่อ hook (null = ถอดทุก hook)
     * @param  string|null  $id  ID ของ listener ที่ต้องการถอด
     * @param  string|null  $scope  scope ที่ต้องการถอด
     */
    function remove_filter(string|array|null $hook = null, ?string $id = null, ?string $scope = null): void
    {
        Filter::removeListener($hook, $id, $scope);
    }
}

if (! function_exists('apply_filters')) {
    /**
     * รัน filter listeners ทั้งหมดของ hook และคืนค่าที่ผ่านการ transform แล้ว
     *
     * Calling convention: apply_filters($hook, $scope, $value1, $value2, ...)
     *
     * @param  mixed  ...$args  arg แรก = hook name, arg สอง = scope, ที่เหลือ = values
     * @return mixed ค่าที่ผ่านการ transform โดย listeners
     */
    function apply_filters(mixed ...$args): mixed
    {
        $hook = array_shift($args);
        $scope = array_shift($args) ?? null;
        $value = $args ?: [];

        return Filter::fire($hook, $scope, $value);
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
     * @param  array|string|null  $hook  ชื่อ hook (null = global)
     * @param  array|callable|Closure|string  $callback  callback ที่จะรัน
     * @param  int  $priority  ลำดับการรัน (สูงกว่า = รันก่อน, default: 20)
     * @param  int  $arguments  จำนวน argument ที่ callback รับ
     * @param  bool  $once  true = รันครั้งเดียวแล้วถอด listener
     * @param  string|null  $scope  จำกัด scope เช่น 'admin', 'frontend'
     * @param  string|null  $id  ID ของ listener
     */
    function add_action(
        string|array|null $hook,
        string|array|Closure|callable $callback,
        int $priority = 20,
        int $arguments = 1,
        bool $once = false,
        ?string $scope = null,
        ?string $id = null,
    ): void {
        Action::addListener($hook, $callback, $priority, $arguments, $once, $scope, $id);
    }
}

if (! function_exists('do_action')) {
    /**
     * รัน action listeners ทั้งหมดของ hook (ไม่คืนค่า)
     *
     * Calling convention: do_action($hook, $scope, $arg1, $arg2, ...)
     *
     * @param  mixed  ...$args  arg แรก = hook name, arg สอง = scope, ที่เหลือ = args
     */
    function do_action(mixed ...$args): void
    {
        $hook = array_shift($args);
        $scope = array_shift($args) ?? null;

        Action::fire($hook, $scope, ...$args);
    }
}

// =========================================================================
// Inspection Helpers
// =========================================================================

if (! function_exists('get_hooks')) {
    /**
     * ดึงรายการ listeners ของ hook ที่ระบุ
     *
     * @param  array|string  $name  ชื่อ hook ที่ต้องการ ([] = ทุก hook)
     * @param  string|null  $id  filter ตาม ID
     * @param  string|null  $scope  filter ตาม scope
     * @param  bool  $isFilter  true = filters, false = actions
     * @return array รายการ listeners
     */
    function get_hooks(
        string|array $name = [],
        ?string $id = null,
        ?string $scope = null,
        bool $isFilter = true,
    ): array {
        return $isFilter
            ? Filter::getListeners($name, $id, $scope)
            : Action::getListeners($name, $id, $scope);
    }
}
