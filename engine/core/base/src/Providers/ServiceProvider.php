<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Traits\LoadAndPublishDataTrait;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;

/**
 * ServiceProvider — คลาสฐานสำหรับ Module Service Providers ของ pppportal
 *
 * ให้ trait LoadAndPublishDataTrait พร้อมใช้งานสำหรับ module ทุกตัว
 * เพื่อโหลด config, translations, helpers ตามมาตรฐาน engine
 *
 * การใช้งาน:
 * ```php
 * class MyModuleServiceProvider extends ServiceProvider
 * {
 *     public function boot(): void
 *     {
 *         $this->setNamespace('My\Module');
 *         $this->loadAndPublishConfigurations(['myapp']);
 *     }
 * }
 * ```
 */
abstract class ServiceProvider extends BaseServiceProvider
{
    use LoadAndPublishDataTrait;
}
