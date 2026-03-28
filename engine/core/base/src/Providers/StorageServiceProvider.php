<?php

declare(strict_types=1);

namespace Core\Base\Providers;

use Core\Base\Services\Storage\DriverResolverService;
use Core\Base\Services\Storage\FileStorageService;
use Illuminate\Support\ServiceProvider;

/**
 * StorageServiceProvider — ลงทะเบียน Service สำหรับ File Storage
 *
 * ลงทะเบียนเฉพาะ Service layer ที่เป็น singleton:
 * - FileStorageService    — orchestrator หลักสำหรับ upload/delete/read
 * - DriverResolverService — resolve StorageDriverInterface ตาม disk name
 *
 * หมายเหตุ: Repository bindings (StorageFileInterface, StorageDiskInterface)
 * จัดการโดย RepositoryServiceProvider เพื่อป้องกัน binding ซ้ำ
 */
class StorageServiceProvider extends ServiceProvider
{
    /**
     * ลงทะเบียน services เข้า container
     */
    public function register(): void
    {
        $this->app->singleton(FileStorageService::class);
        $this->app->singleton(DriverResolverService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void {}
}
