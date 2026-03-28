<?php

declare(strict_types=1);

namespace Core\Base\Support\Helpers\Module;

use Nwidart\Modules\Laravel\Module as ModuleInstance;

/**
 * ModuleResolver — Backward-compatible wrapper สำหรับ ModuleHelper
 *
 * @deprecated ใช้ ModuleHelper แทน (เพิ่มความสามารถและ DI-friendly)
 *             class นี้คงไว้เพื่อไม่ให้โค้ดเดิมพัง
 *
 * Migration:
 *   เดิม: ModuleResolver::current()
 *   ใหม่: app(ModuleHelper::class)->current()
 *         หรือ app(ModuleHelperInterface::class)->current()
 */
final class ModuleResolver
{
    /**
     * ตรวจหา module ปัจจุบันจาก caller context
     *
     * @deprecated ใช้ app(ModuleHelper::class)->current() แทน
     *
     * @return ModuleInstance|null
     */
    public static function current(): ?ModuleInstance
    {
        return app(ModuleHelper::class)->current();
    }

    /**
     * ล้าง cache
     *
     * @deprecated ใช้ app(ModuleHelper::class)->flushCache() แทน
     */
    public static function flushCache(): void
    {
        app(ModuleHelper::class)->flushCache();
    }
}
