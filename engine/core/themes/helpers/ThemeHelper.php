<?php

declare(strict_types=1);

use Illuminate\Contracts\View\View;

/*
|--------------------------------------------------------------------------
| ThemeHelper — helper functions สำหรับ Module Context และ Theme
|--------------------------------------------------------------------------
*/

if (! function_exists('current_module')) {
    /**
     * ดึงชื่อ module ที่ active อยู่ในขณะนั้น
     *
     * @return string|null ชื่อ module หรือ null ถ้าไม่มี context
     */
    function current_module(): ?string
    {
        if (! app()->bound('module.context')) {
            return null;
        }

        return app('module.context')->getCurrentModule();
    }
}

if (! function_exists('is_current_module')) {
    /**
     * ตรวจสอบว่า module ที่ระบุเป็น module ปัจจุบันหรือไม่
     *
     * @param  string  $moduleName  ชื่อ module
     */
    function is_current_module(string $moduleName): bool
    {
        if (! app()->bound('module.context')) {
            return false;
        }

        return app('module.context')->isCurrentModule($moduleName);
    }
}

if (! function_exists('getCurrentModuleInfo')) {
    /**
     * ดึง metadata ของ module ปัจจุบัน
     *
     * @return array{name: string, path: string, enabled: bool}|null
     */
    function getCurrentModuleInfo(): ?array
    {
        if (! app()->bound('module.context')) {
            return null;
        }

        return app('module.context')->getCurrentModuleInfo();
    }
}

if (! function_exists('setThemes')) {
    /**
     * กำหนด theme ให้กับ module context
     *
     * @param  string|null  $names  ชื่อ theme
     * @param  string|null  $type  ประเภท theme ('frontend', 'backend', 'auth')
     */
    function setThemes(?string $names, ?string $type): bool
    {
        if (! app()->bound('module.context')) {
            return false;
        }

        return app('module.context')->setThemes($names, $type);
    }
}

if (! function_exists('module_views')) {
    /**
     * เรียก view โดย prefix ด้วยชื่อ module ปัจจุบันอัตโนมัติ
     *
     * ถ้ามี module context : "{module}::{view}"
     * ถ้าไม่มี             : "{view}" (global view)
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $mergeData
     */
    function module_views(string $view, array $data = [], array $mergeData = []): View
    {
        $module = current_module();
        $viewPath = $module ? "{$module}::{$view}" : $view;

        return view($viewPath, $data, $mergeData);
    }
}

if (! function_exists('module_assets')) {
    /**
     * สร้าง URL สำหรับ asset ของ module
     *
     * path รูปแบบ: vendor/core/{module-tag}/{path}
     * เช่น module = 'Core\Base' → vendor/core/core-base/css/app.css
     *
     * @param  string  $path  เส้นทางไฟล์ใน module assets
     * @param  string|null  $module  ชื่อ module (null = ใช้ current module)
     * @param  bool|null  $secure  บังคับ https
     */
    function module_assets(string $path, ?string $module = null, ?bool $secure = null): string
    {
        $module = $module ?: current_module();

        if (! $module) {
            return asset($path, $secure);
        }

        $moduleTag = str_replace(['/', '\\'], '-', strtolower($module));

        return asset('vendor/core/'.$moduleTag.'/'.ltrim($path, '/'), $secure);
    }
}
